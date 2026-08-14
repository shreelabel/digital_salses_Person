<?php
declare(strict_types=1);

namespace SLC\Services\Providers;

/**
 * Central facade over the provider layer. Exposes readiness status, free-mode
 * semantics, connection tests, and the discovery/enrichment/AI provider
 * instances. Used by the controller and the discovery service.
 */
final class ProviderManager
{
    public function __construct(
        private ProviderConfigRepository $config = new ProviderConfigRepository(),
        private ProviderContext $ctx = new ProviderContext(),
    ) {
    }

    public function ctx(): ProviderContext
    {
        return $this->ctx;
    }

    public function config(): ProviderConfigRepository
    {
        return $this->config;
    }

    /** Browser-safe status of every provider (no raw keys). */
    public function status(): array
    {
        $providers = $this->config->forBrowser();
        $aiReady = $this->config->isAnyAiConfigured();
        $discoveryReady = $this->config->isAnyDataConfigured();
        return [
            'providers'        => $providers,
            'ai_available'     => $aiReady,
            'discovery_available' => $discoveryReady,
            'free_mode'        => true, // free-first is the default operating mode
            'primary_ai'       => (new AIProviderRouter($this->config))->primaryName(),
            'primary_discovery'=> $this->config->isReady('hunter') ? 'Hunter' : ($this->config->isReady('apollo') ? 'Apollo' : 'none'),
        ];
    }

    public function hunter(): HunterProvider
    {
        return new HunterProvider($this->config);
    }

    public function apollo(): ApolloProvider
    {
        return new ApolloProvider($this->config);
    }

    public function aiRouter(): AIProviderRouter
    {
        return new AIProviderRouter($this->config);
    }

    /**
     * Run ONE connection test for a single provider. Never retries.
     * Returns a normalised status array.
     */
    public function testConnection(string $slug): array
    {
        $ctx = $this->ctx;
        $result = match ($slug) {
            'hunter'     => $this->hunter()->ping($ctx),
            'apollo'     => $this->apollo()->ping($ctx),
            'freellmapi' => $this->pingAi(new FreeLlmApiProvider($this->config)),
            '9router'    => $this->pingAi(new NineRouterProvider($this->config)),
            'gemini'     => $this->pingAi($this->buildGemini()),
            default      => ['ok' => false, 'message' => 'Unknown provider.'],
        };

        $status = ($result['ok'] ?? false) ? 'Connected' : (($result['configured'] ?? true) ? 'Error' : 'Not Configured');
        $this->config->markTested($slug, $status);

        return [
            'provider'  => $slug,
            'connected' => (bool) ($result['ok'] ?? false),
            'configured'=> (bool) ($result['configured'] ?? $this->config->isReady($slug)),
            'message'   => $result['message'] ?? ($result['error'] ?? 'Unknown'),
            'status'    => $status,
            'latency_ms'=> $result['latency_ms'] ?? null,
            'remaining' => $result['remaining'] ?? null,
        ];
    }

    private function pingAi(\SLC\Services\AI\AIProviderInterface $p): array
    {
        if (!$p->isConfigured()) {
            return ['ok' => false, 'configured' => false, 'message' => 'Not Configured'];
        }
        $r = $p->ping();
        return ['ok' => $r->ok, 'configured' => true, 'message' => $r->ok ? 'Connected' : ($r->error ?? 'Error'), 'latency_ms' => $r->latencyMs];
    }

    private function buildGemini(): \SLC\Services\AI\GeminiProvider
    {
        $key = $this->config->getKey('gemini') ?: null;
        $model = $this->config->get('gemini')?->model ?: 'gemini-3.6-flash';
        return new \SLC\Services\AI\GeminiProvider($key, $model);
    }
}
