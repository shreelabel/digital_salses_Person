<?php
declare(strict_types=1);

namespace SLC\Core;

/**
 * Centralised, typed access to environment configuration.
 * All values come from .env (or real server environment) — never from the browser.
 */
final class Config
{
    public static function get(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }

    public static function db(): array
    {
        return [
            'host' => self::get('DB_HOST', '127.0.0.1'),
            'port' => (int) self::get('DB_PORT', 3306),
            'name' => self::get('DB_NAME', 'slc_ai_sales'),
            'user' => self::get('DB_USER', 'root'),
            'pass' => self::get('DB_PASS', ''),
            'charset' => 'utf8mb4',
        ];
    }

    public static function appName(): string
    {
        return (string) self::get('APP_NAME', 'SLC AI Sales Agent');
    }

    public static function appKey(): string
    {
        return (string) self::get('APP_KEY', '');
    }

    public static function debug(): bool
    {
        return self::get('APP_DEBUG', 'false') === 'true';
    }

    public static function basePath(): string
    {
        $b = rtrim((string) self::get('APP_BASE_PATH', ''), '/');
        if ($b !== '') {
            return $b;
        }
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        if ($script !== '') {
            $dir = str_replace('\\', '/', dirname($script));
            return ($dir === '/' || $dir === '.') ? '' : $dir;
        }
        return '';
    }

    public static function sessionLifetime(): int
    {
        return (int) self::get('SESSION_LIFETIME', 7200);
    }

    public static function loginMaxAttempts(): int
    {
        return (int) self::get('LOGIN_MAX_ATTEMPTS', 5);
    }

    public static function loginLockoutSeconds(): int
    {
        return (int) self::get('LOGIN_LOCKOUT_SECONDS', 900);
    }

    public static function geminiApiKey(): string
    {
        return (string) self::get('GEMINI_API_KEY', '');
    }

    public static function geminiModel(): string
    {
        return (string) self::get('GEMINI_MODEL', 'gemini-3.6-flash');
    }

    public static function geminiApiBase(): string
    {
        return rtrim((string) self::get('GEMINI_API_BASE', 'https://generativelanguage.googleapis.com/v1beta'), '/');
    }
}
