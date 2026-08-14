<?php
declare(strict_types=1);

namespace SLC\Core;

/**
 * CSRF protection for state-changing requests.
 * Token stored in session; JS reads it via a meta tag and sends it back
 * in the X-CSRF-Token header for every unsafe method.
 */
final class CSRF
{
    public const HEADER = 'X-CSRF-Token';
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::KEY);
        if (!$token) {
            $token = Security::randomToken(32);
            Session::set(self::KEY, $token);
        }
        return $token;
    }

    public static function check(string $token): bool
    {
        $stored = Session::get(self::KEY);
        if (!$stored || !$token) {
            return false;
        }
        return hash_equals($stored, $token);
    }

    /**
     * Enforce CSRF on the current request for unsafe methods.
     * Call inside the API router for POST/PUT/PATCH/DELETE.
     */
    public static function guard(string $method): void
    {
        $method = strtoupper($method);
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }
        $token = $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_SERVER['HTTP_X_CSRFTOKEN']
            ?? $_POST['_csrf']
            ?? '';
        if (!self::check($token)) {
            Response::json(['error' => 'Invalid or missing CSRF token.'], 419);
            exit;
        }
    }
}
