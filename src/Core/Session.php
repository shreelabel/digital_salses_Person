<?php
declare(strict_types=1);

namespace SLC\Core;

/**
 * Hardened PHP session handling: secure cookie params, idle timeout, CSRF store.
 */
final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $lifetime = Config::sessionLifetime();
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => self::isHttps(),
        ]);
        ini_set('session.use_strict_mode', '1');
        session_name('SLC_SESS');

        session_start();
        self::$started = true;

        // Rotate session id periodically to harden against fixation.
        if (!isset($_SESSION['_created'])) {
            $_SESSION['_created'] = time();
        }
        if (time() - $_SESSION['_created'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['_created'] = time();
        }

        // Idle timeout
        if (isset($_SESSION['_last_activity']) &&
            (time() - $_SESSION['_last_activity']) > $lifetime) {
            self::destroy();
            return;
        }
        $_SESSION['_last_activity'] = time();
    }

    private static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') == 443)
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
        $_SESSION['_created'] = time();
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool) ($p['secure'] ?? false), (bool) ($p['httponly'] ?? false));
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        self::$started = false;
    }
}
