<?php
declare(strict_types=1);

namespace SLC\Services\Providers;

use SLC\Core\Database;

/**
 * Cost / credit protection audit layer. Every external provider call is
 * recorded here: provider, operation, whether it was a cache hit, status,
 * latency, credits consumed (if the API exposes them), and any error.
 */
final class ProviderUsageLogger
{
    public static function log(array $entry): void
    {
        try {
            Database::insert('slc_provider_usage', [
                'provider'        => $entry['provider'] ?? 'unknown',
                'operation'       => $entry['operation'] ?? 'unknown',
                'cache_hit'       => !empty($entry['cache_hit']) ? 1 : 0,
                'status'          => $entry['status'] ?? 'unknown',
                'http_status'     => $entry['http_status'] ?? null,
                'latency_ms'      => $entry['latency_ms'] ?? null,
                'credit_used'     => $entry['credit_used'] ?? null,
                'rate_remaining'  => $entry['rate_remaining'] ?? null,
                'request_summary' => self::trunc($entry['request_summary'] ?? null, 250),
                'error'           => self::trunc($entry['error'] ?? null, 480),
                'user_id'         => $entry['user_id'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // audit must never break the request
        }
    }

    public static function recent(int $limit = 50): array
    {
        return Database::fetchAll(
            'SELECT * FROM slc_provider_usage ORDER BY id DESC LIMIT ' . max(1, min(500, $limit))
        );
    }

    private static function trunc(?string $s, int $max): ?string
    {
        if ($s === null) return null;
        $s = trim($s);
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max) : ($s === '' ? null : $s);
    }
}
