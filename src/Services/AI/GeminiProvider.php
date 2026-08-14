<?php
declare(strict_types=1);

namespace SLC\Services\AI;

use SLC\Core\Config;
use SLC\Core\HttpClient;
use SLC\Core\Logger;

/**
 * Gemini provider using Google's CURRENT Interactions API architecture:
 *   POST {base}/interactions
 *   headers: x-goog-api-key, Content-Type: application/json
 *   body:    { "model": "...", "input": "...", "tools": [{"type":"google_search"}] }
 *
 * Grounding is performed with the modern `google_search` tool (NOT the
 * obsolete google_search_retrieval). Citations are extracted from the
 * `url_citation` annotations in the response steps.
 *
 * The API key is read SERVER-SIDE only and never returned to the browser.
 */
final class GeminiProvider implements AIProviderInterface
{
    private string $apiKey;
    private string $model;

    public function __construct(?string $apiKey = null, ?string $model = null)
    {
        $this->apiKey = $apiKey ?? $this->resolveKey();
        $this->model  = $model  ?? Config::geminiModel() ?: 'gemini-3.6-flash';
    }

    private function resolveKey(): string
    {
        // DB-stored key wins over .env when set from AI Settings.
        try {
            $db = (new \SLC\Repositories\SettingsRepository())->get('gemini_api_key');
            if ($db) {
                return $db;
            }
        } catch (\Throwable $e) {
        }
        return Config::geminiApiKey();
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function withModel(string $model): self
    {
        return new self($this->apiKey, $model);
    }

    public function generate(string $prompt, bool $grounded = true, array $options = []): AiResult
    {
        if (!$this->isConfigured()) {
            return new AiResult(false, error: 'Gemini API key is not configured.');
        }

        $url = Config::geminiApiBase() . '/interactions';
        $body = [
            'model' => $this->model,
            'input' => $prompt,
        ];
        if ($grounded) {
            $body['tools'] = [['type' => 'google_search']];
        }
        if (isset($options['temperature'])) {
            $body['temperature'] = (float) $options['temperature'];
        }

        $headers = [
            'x-goog-api-key' => $this->apiKey,
            'Content-Type'   => 'application/json',
        ];

        $resp = HttpClient::post($url, $headers, $body, (int) ($options['timeout'] ?? 90));
        return $this->parseResponse($resp);
    }

    public function ping(): AiResult
    {
        return $this->generate(
            'Reply with the single word: OK',
            false,
            ['timeout' => 30]
        );
    }

    /**
     * @param array{status:int,body:string,latency_ms:int,error:?string} $resp
     */
    private function parseResponse(array $resp): AiResult
    {
        $status = $resp['status'];
        $latency = $resp['latency_ms'];
        $raw = $resp['body'];

        if ($status === 0) {
            return new AiResult(false, httpStatus: 0, latencyMs: $latency, error: $resp['error'] ?: 'Network error contacting Gemini.');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return new AiResult(false, httpStatus: $status, latencyMs: $latency, error: 'Unparseable response from Gemini.');
        }

        if ($status >= 400) {
            $msg = $decoded['error']['message']
                ?? $decoded['error']
                ?? ('Gemini HTTP ' . $status);
            return new AiResult(false, httpStatus: $status, latencyMs: $latency, error: is_array($msg) ? json_encode($msg) : (string) $msg);
        }

        // Prefer convenience field, else assemble from steps
        $text = '';
        if (is_string($decoded['output_text'] ?? null)) {
            $text = $decoded['output_text'];
        }
        $citations = [];
        $queries = [];
        $steps = $decoded['steps'] ?? $decoded['candidates'][0]['steps'] ?? [];
        if (is_array($steps)) {
            foreach ($steps as $step) {
                $type = $step['type'] ?? '';
                if ($type === 'google_search_call' && isset($step['arguments']['queries'])) {
                    foreach ($step['arguments']['queries'] as $q) {
                        $queries[] = $q;
                    }
                }
                $content = $step['content'] ?? null;
                if (is_array($content)) {
                    foreach ($content as $block) {
                        if (($block['type'] ?? '') === 'text' && !empty($block['text'])) {
                            if ($text === '') {
                                $text = $block['text'];
                            }
                            foreach ($block['annotations'] ?? [] as $ann) {
                                if (($ann['type'] ?? '') === 'url_citation' && !empty($ann['url'])) {
                                    $citations[] = [
                                        'url'   => $ann['url'],
                                        'title' => $ann['title'] ?? parse_url($ann['url'], PHP_URL_HOST) ?? $ann['url'],
                                    ];
                                }
                            }
                        }
                    }
                }
            }
        }

        // Dedupe citations by URL
        $seen = [];
        $citations = array_values(array_filter($citations, function ($c) use (&$seen) {
            if (isset($seen[$c['url']])) {
                return false;
            }
            $seen[$c['url']] = true;
            return true;
        }));

        if ($text === '') {
            return new AiResult(false, httpStatus: $status, latencyMs: $latency, error: 'Empty response from Gemini.', raw: $decoded);
        }

        return new AiResult(true, text: $text, citations: $citations, queries: $queries, latencyMs: $latency, httpStatus: $status, raw: $decoded);
    }

    /** Extract and repair a JSON object/array from a model text response. */
    public static function extractJson(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        // 1. Direct decode attempt
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // 2. Strip markdown fences if present
        if (preg_match('/```(?:json)?\s*([\s\S]+?)(?:```|$)/i', $text, $m)) {
            $stripped = trim($m[1]);
            $decoded = json_decode($stripped, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            $text = $stripped;
        }

        // 3. Locate the first { or [
        $start = -1;
        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            if ($text[$i] === '{' || $text[$i] === '[') {
                $start = $i;
                break;
            }
        }
        if ($start === -1) {
            return null;
        }

        $text = substr($text, $start);

        // 4. Trim to last matching } or ]
        $lastBrace = strrpos($text, '}');
        $lastBracket = strrpos($text, ']');
        $end = max($lastBrace !== false ? $lastBrace : -1, $lastBracket !== false ? $lastBracket : -1);
        if ($end !== -1) {
            $candidate = substr($text, 0, $end + 1);
            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            $text = $candidate;
        }

        // 5. Clean common JSON syntax issues (trailing commas, control chars)
        $cleaned = preg_replace('/,\s*([\}\]])/', '$1', $text);
        $decoded = json_decode($cleaned, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $cleaned = preg_replace_callback('/"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"/s', function ($matches) {
            return '"' . str_replace(["\n", "\r", "\t"], ['\n', '\r', '\t'], $matches[1]) . '"';
        }, $cleaned);
        $decoded = json_decode($cleaned, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // 6. Truncation repair (if model cut off in middle of array of candidates)
        $lastCloseObj = strrpos($cleaned, '}');
        if ($lastCloseObj !== false) {
            $tryTruncated = substr($cleaned, 0, $lastCloseObj + 1);
            $openBrackets = substr_count($tryTruncated, '[') - substr_count($tryTruncated, ']');
            $openBraces = substr_count($tryTruncated, '{') - substr_count($tryTruncated, '}');
            $tryTruncated .= str_repeat(']', max(0, $openBrackets));
            $tryTruncated .= str_repeat('}', max(0, $openBraces));
            $tryTruncated = preg_replace('/,\s*([\}\]])/', '$1', $tryTruncated);
            $decoded = json_decode($tryTruncated, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
