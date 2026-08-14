<?php
declare(strict_types=1);

namespace SLC\Services\Providers;

use SLC\Core\Database;

/**
 * Provider response cache. Prevents repeating the same lookup (domain, person,
 * email) within a TTL. Used for cost/credit protection.
 */
final class ProviderCache
{
    public function get(string $provider, string $operation, string $key): ?array
    {
        $row = Database::fetch(
            'SELECT * FROM slc_provider_cache
             WHERE provider = :p AND operation = :o AND cache_key = :k
             AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1',
            ['p' => $provider, 'o' => $operation, 'k' => $key]
        );
        if (!$row) {
            return null;
        }
        // increment hit counter (best effort)
        try {
            Database::query(
                'UPDATE slc_provider_cache SET hits = hits + 1 WHERE id = :id',
                ['id' => $row['id']]
            );
        } catch (\Throwable $e) {
        }
        $payload = json_decode((string) $row['payload'], true);
        return is_array($payload) ? $payload : null;
    }

    public function put(string $provider, string $operation, string $key, array $payload, int $ttlSeconds = 86400): void
    {
        $expires = $ttlSeconds > 0 ? date('Y-m-d H:i:s', time() + $ttlSeconds) : null;
        $existing = Database::fetch(
            'SELECT id FROM slc_provider_cache WHERE provider = :p AND operation = :o AND cache_key = :k',
            ['p' => $provider, 'o' => $operation, 'k' => $key]
        );
        $blob = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($existing) {
            Database::query(
                'UPDATE slc_provider_cache SET payload = :b, expires_at = :e WHERE id = :id',
                ['b' => $blob, 'e' => $expires, 'id' => $existing['id']]
            );
        } else {
            Database::insert('slc_provider_cache', [
                'provider' => $provider, 'operation' => $operation,
                'cache_key' => $key, 'payload' => $blob, 'expires_at' => $expires,
            ]);
        }
    }

    public function clear(): int
    {
        return Database::query('DELETE FROM slc_provider_cache')->rowCount();
    }
}
