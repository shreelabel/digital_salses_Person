<?php
declare(strict_types=1);

namespace SLC\Core;

/**
 * Security helpers: password hashing, secret masking, token generation.
 */
final class Security
{
    public static function hashPassword(string $plain): string
    {
        return password_hash($plain, PASSWORD_DEFAULT);
    }

    public static function verifyPassword(string $plain, string $hash): bool
    {
        // constant-time comparison inside password_verify
        return password_verify($plain, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }

    public static function randomToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /** Mask a secret so only the last few chars are visible. Never returns the full key. */
    public static function maskSecret(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $len = strlen($value);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }
        // show only first 3 + last 3, e.g. AIza***…***
        $head = substr($value, 0, 3);
        $tail = substr($value, -3);
        return $head . str_repeat('*', min($len - 6, 24)) . $tail;
    }

    public static function configured(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }
}
