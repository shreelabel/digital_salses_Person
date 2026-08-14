<?php
declare(strict_types=1);

namespace SLC\Services\AI;

use SLC\Core\Database;

/**
 * Persists AI request audits to slc_ai_requests. NEVER stores API keys.
 */
final class AiRequestLogger
{
    public static function log(string $type, AiResult $r, ?int $userId = null, string $endpoint = '', string $promptSummary = ''): void
    {
        try {
            Database::insert('slc_ai_requests', [
                'type'        => $type,
                'endpoint'    => $endpoint ?: 'gemini:interactions',
                'model'       => null,
                'status'      => $r->ok ? 'success' : 'error',
                'latency_ms'  => $r->latencyMs,
                'prompt_summary' => self::truncate($promptSummary, 480),
                'response_summary' => self::truncate($r->text, 480),
                'error'       => self::truncate($r->error ?? '', 480),
                'user_id'     => $userId,
            ]);
        } catch (\Throwable $e) {
            // audit logging must never break the request
        }
    }

    private static function truncate(?string $s, int $max): ?string
    {
        if ($s === null) {
            return null;
        }
        $s = trim($s);
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max) : ($s === '' ? null : $s);
    }
}
