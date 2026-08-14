<?php
declare(strict_types=1);

namespace SLC\Services\Providers;

use SLC\Core\HttpClient;
use SLC\Services\AI\AIProviderInterface;
use SLC\Services\AI\AiResult;

/**
 * OpenAI-compatible chat-completions provider. Used for FreeLLMAPI and 9Router.
 * No web grounding / no search tool — pure text generation (free-first).
 * Implements the existing AIProviderInterface so it plugs into the AI chain.
 *
 * The `$grounded` flag is accepted for interface compatibility but is IGNORED:
 * we deliberately never use paid search grounding in the free-first chain.
 */
class OpenAiCompatibleProvider implements AIProviderInterface
{
    public function __construct(
        private string $slug,
        private ProviderConfigRepository $config = new ProviderConfigRepository()
    ) {
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function isConfigured(): bool
    {
        return $this->config->isReady($this->slug);
    }

    public function getModel(): string
    {
        return $this->config->get($this->slug)?->model ?: 'gpt-4o-mini';
    }

    public function generate(string $prompt, bool $grounded = true, array $options = []): AiResult
    {
        if (!$this->isConfigured()) {
            return new AiResult(false, error: ucfirst($this->slug) . ' is not configured/enabled.');
        }
        $cfg = $this->config->get($this->slug);
        $key = $this->config->getKey($this->slug);
        $base = rtrim((string) $cfg?->baseUrl, '/');
        $base = str_replace('freellmapis.com', 'freellmapi.com', $base);
        if (empty($base)) {
            $base = $this->slug === 'freellmapi' ? 'https://api.freellmapi.com/v1' : ($this->slug === '9router' ? 'https://api.9router.com/v1' : $base);
        }
        $model = $cfg?->model ?: ($this->slug === 'freellmapi' ? 'auto' : 'gpt-4o-mini');

        $url = $base . '/chat/completions';
        $body = [
            'model'       => $model,
            'messages'    => [['role' => 'user', 'content' => $prompt]],
            'temperature' => $options['temperature'] ?? 0.3,
            'stream'      => false,
        ];
        $headers = [
            'Content-Type' => 'application/json',
        ];
        if ($key !== null && trim($key) !== '' && trim($key) !== '****' && !str_starts_with(strtolower(trim($key)), 'current:')) {
            $headers['Authorization'] = 'Bearer ' . trim($key);
        }
        $resp = HttpClient::post($url, $headers, $body, (int) ($options['timeout'] ?? 90));
        return $this->parse($resp, $model);
    }

    public function ping(): AiResult
    {
        return $this->generate('Reply with the single word: OK', false, ['timeout' => 30]);
    }

    private function parse(array $resp, string $model): AiResult
    {
        $status = (int) $resp['status'];
        $latency = (int) ($resp['latency_ms'] ?? 0);
        if ($status === 0) {
            return new AiResult(false, httpStatus: 0, latencyMs: $latency, error: $resp['error'] ?: 'Network error.');
        }
        $json = json_decode((string) $resp['body'], true);
        if (!is_array($json)) {
            $msg = $status >= 400 ? "HTTP {$status} (non-JSON response)" : 'Unparseable response.';
            return new AiResult(false, httpStatus: $status, latencyMs: $latency, error: $msg);
        }
        if ($status >= 400) {
            $msg = $json['error']['message'] ?? ($json['error'] ?? null);
            $msg = is_array($msg) ? json_encode($msg) : (string) $msg;
            return new AiResult(false, httpStatus: $status, latencyMs: $latency, error: $msg ?: "HTTP {$status}");
        }
        $text = $json['choices'][0]['message']['content']
            ?? $json['choices'][0]['text']
            ?? '';
        if ($text === '') {
            return new AiResult(false, httpStatus: $status, latencyMs: $latency, error: 'Empty response.', raw: $json);
        }
        return new AiResult(true, text: (string) $text, latencyMs: $latency, httpStatus: $status, raw: $json);
    }

    public static function extractJson(string $text): ?array
    {
        return \SLC\Services\AI\GeminiProvider::extractJson($text);
    }
}
