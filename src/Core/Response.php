<?php
declare(strict_types=1);

namespace SLC\Core;

/**
 * Consistent JSON responses for the API layer.
 */
final class Response
{
    public static function json(mixed $data, int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('Referrer-Policy: same-origin');
        }
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    public static function success(mixed $data = [], int $status = 200): void
    {
        self::json(array_merge(['ok' => true], is_array($data) ? $data : ['data' => $data]), $status);
    }

    public static function error(string $message, int $status = 400, array $extra = []): void
    {
        self::json(array_merge(['ok' => false, 'error' => $message], $extra), $status);
    }

    public static function notFound(string $message = 'Resource not found.'): void
    {
        self::error($message, 404);
    }

    public static function validationError(array $errors): void
    {
        self::json(['ok' => false, 'error' => 'Validation failed.', 'errors' => $errors], 422);
    }
}
