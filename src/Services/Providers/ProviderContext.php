<?php
declare(strict_types=1);

namespace SLC\Services\Providers;

/**
 * Shared execution context passed to every provider. Centralises the
 * cost/credit-protection rules:
 *   1. check cache
 *   2. run ONE outbound call (the callable does the actual HTTP)
 *   3. store result in cache
 *   4. audit-log the call
 * Never re-runs the same provider/operation/cache_key within TTL.
 */
final class ProviderContext
{
    public function __construct(
        private ProviderConfigRepository $config = new ProviderConfigRepository(),
        private ProviderCache $cache = new ProviderCache(),
        private ?int $userId = null,
    ) {
    }

    public function withUser(int $userId): self
    {
        return new self($this->config, $this->cache, $userId);
    }

    public function config(): ProviderConfigRepository
    {
        return $this->config;
    }

    /**
     * Run (or reuse from cache) one provider operation.
     *
     * @param callable():array $http  returns HttpClient::post()/get()-style
     *                                array: {status:int, body:string, latency_ms:int, error:?string}
     * @param callable(array):array $parser  decode + shape the JSON body into a payload
     * @return array{ok:bool, data:?array, cache_hit:bool, latency_ms:int, status:int, error:?string, credits:?float, remaining:?int}
     */
    public function call(
        string $provider,
        string $operation,
        string $cacheKey,
        callable $http,
        callable $parser,
        int $ttl = 86400,
        string $summary = '',
    ): array {
        // 1) cache check
        $cached = $this->cache->get($provider, $operation, $cacheKey);
        if ($cached !== null) {
            ProviderUsageLogger::log([
                'provider' => $provider, 'operation' => $operation, 'cache_hit' => true,
                'status' => 'success', 'request_summary' => $summary, 'user_id' => $this->userId,
            ]);
            return [
                'ok' => true, 'data' => $cached, 'cache_hit' => true,
                'latency_ms' => 0, 'status' => 200, 'error' => null,
                'credits' => null, 'remaining' => null,
            ];
        }

        // 2) one outbound call
        $resp = $http();
        $status = (int) ($resp['status'] ?? 0);
        $latency = (int) ($resp['latency_ms'] ?? 0);
        $error = $resp['error'] ?? null;

        if ($status === 0 || $status >= 400) {
            $errMsg = $error ?: ($status === 429 ? 'Rate limit reached (free tier exhausted).' : "Provider returned HTTP {$status}.");
            ProviderUsageLogger::log([
                'provider' => $provider, 'operation' => $operation, 'cache_hit' => false,
                'status' => 'error', 'http_status' => $status, 'latency_ms' => $latency,
                'request_summary' => $summary, 'error' => $errMsg, 'user_id' => $this->userId,
            ]);
            return [
                'ok' => false, 'data' => null, 'cache_hit' => false,
                'latency_ms' => $latency, 'status' => $status, 'error' => $errMsg,
                'credits' => null, 'remaining' => null,
            ];
        }

        // 3) parse
        try {
            $data = $parser(json_decode((string) $resp['body'], true) ?? [], $resp);
        } catch (\Throwable $e) {
            ProviderUsageLogger::log([
                'provider' => $provider, 'operation' => $operation, 'cache_hit' => false,
                'status' => 'error', 'http_status' => $status, 'latency_ms' => $latency,
                'request_summary' => $summary, 'error' => 'Parse error: ' . $e->getMessage(), 'user_id' => $this->userId,
            ]);
            return [
                'ok' => false, 'data' => null, 'cache_hit' => false,
                'latency_ms' => $latency, 'status' => $status, 'error' => 'Could not parse provider response.',
                'credits' => null, 'remaining' => null,
            ];
        }

        // 4) cache + audit
        $this->cache->put($provider, $operation, $cacheKey, $data, $ttl);
        ProviderUsageLogger::log([
            'provider' => $provider, 'operation' => $operation, 'cache_hit' => false,
            'status' => 'success', 'http_status' => $status, 'latency_ms' => $latency,
            'credit_used' => $data['__credits__'] ?? null,
            'rate_remaining' => $data['__remaining__'] ?? null,
            'request_summary' => $summary, 'user_id' => $this->userId,
        ]);

        return [
            'ok' => true, 'data' => $data, 'cache_hit' => false,
            'latency_ms' => $latency, 'status' => $status, 'error' => null,
            'credits' => $data['__credits__'] ?? null,
            'remaining' => $data['__remaining__'] ?? null,
        ];
    }

    public function usageRecent(int $limit = 50): array
    {
        return ProviderUsageLogger::recent($limit);
    }
}
