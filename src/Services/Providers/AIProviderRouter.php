<?php
declare(strict_types=1);

namespace SLC\Services\Providers;

use SLC\Services\AI\AIProviderInterface;
use SLC\Services\AI\AiResult;
use SLC\Services\AI\GeminiProvider;

/**
 * Free-first AI fallback chain. Tries providers in priority order and STOPS at
 * the first success — it never calls all providers for one request, and never
 * retries continuously.
 *
 *   FreeLLMAPI  →  9Router  →  Gemini (OPTIONAL)  →  AI unavailable
 *
 * Grounding/search is deliberately disabled for every provider in this chain
 * (no Google billing). $grounded is accepted for interface compatibility but
 * always passed as false to the underlying providers.
 */
final class AIProviderRouter implements AIProviderInterface
{
    private array $trace = [];

    /**
     * @param array|null $forced Optional test seam: an ordered list of
     *                           [AIProviderInterface, slug, name] tuples used
     *                           directly as the candidate chain (bypasses config).
     */
    public function __construct(
        private ProviderConfigRepository $config = new ProviderConfigRepository(),
        private ?array $forced = null,
    ) {
    }

    /** Build the ordered, enabled candidate list. */
    private function candidates(): array
    {
        if ($this->forced !== null) {
            return array_values(array_filter($this->forced, fn($x) => $x !== null));
        }
        $list = [];
        $ai = array_filter($this->config->all(), fn($c) => $c->role === 'ai');
        usort($ai, fn($a, $b) => $a->priority <=> $b->priority);

        foreach ($ai as $cfg) {
            if (!$cfg->isReady()) {
                continue;
            }
            $list[] = match ($cfg->slug) {
                'freellmapi' => [new FreeLlmApiProvider($this->config), $cfg->slug, $cfg->name],
                '9router'    => [new NineRouterProvider($this->config), $cfg->slug, $cfg->name],
                'gemini'     => [$this->buildGemini(), $cfg->slug, $cfg->name],
                default      => null,
            };
        }
        return array_values(array_filter($list, fn($x) => $x !== null));
    }

    private function buildGemini(): GeminiProvider
    {
        $key = $this->config->getKey('gemini') ?: null;
        $model = $this->config->get('gemini')?->model ?: 'gemini-3.6-flash';
        return new GeminiProvider($key, $model);
    }

    public function isConfigured(): bool
    {
        return $this->config->isAnyAiConfigured();
    }

    public function getModel(): string
    {
        foreach ($this->candidates() as [$provider, $slug, $name]) {
            return $provider->getModel() . ' (' . $name . ')';
        }
        return 'none configured';
    }

    public function generate(string $prompt, bool $grounded = true, array $options = []): AiResult
    {
        $this->trace = [];
        $candidates = $this->candidates();

        if (empty($candidates)) {
            return new AiResult(false, error: 'No AI provider is configured. Enable FreeLLMAPI or 9Router in AI Settings.');
        }

        foreach ($candidates as [$provider, $slug, $name]) {
            try {
                $result = $provider->generate($prompt, false, $options); // grounded=false always
            } catch (\Throwable $e) {
                $this->trace[] = ['provider' => $slug, 'ok' => false, 'error' => $e->getMessage()];
                continue;
            }
            $this->trace[] = [
                'provider' => $slug,
                'ok'       => $result->ok,
                'error'    => $result->error,
                'latency'  => $result->latencyMs,
            ];
            if ($result->ok) {
                if (!empty($options['require_json'])) {
                    $json = GeminiProvider::extractJson($result->text);
                    if ($json === null) {
                        $this->trace[count($this->trace) - 1]['ok'] = false;
                        $this->trace[count($this->trace) - 1]['error'] = 'Response was not valid JSON';
                        continue; // try next candidate in chain
                    }
                }
                return $result; // first success wins — stop
            }
        }

        return new AiResult(false, error: 'All AI providers failed: ' . implode(' | ', array_map(
            fn($t) => $t['provider'] . ':' . ($t['error'] ?? 'failed'),
            $this->trace
        )));
    }

    public function ping(): AiResult
    {
        $candidates = $this->candidates();
        if (empty($candidates)) {
            return new AiResult(false, error: 'No AI provider is configured.');
        }
        [$provider] = $candidates;
        return $provider->ping();
    }

    public function trace(): array
    {
        return $this->trace;
    }

    /** Which AI provider would be used first right now (for the UI). */
    public function primaryName(): string
    {
        foreach ($this->candidates() as [$_p, $slug, $name]) {
            return $name;
        }
        return 'none';
    }
}
