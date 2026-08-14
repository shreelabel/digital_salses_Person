<?php
declare(strict_types=1);

namespace SLC\Core;

/**
 * Authentication: login verification, current user, guard helpers.
 * Sessions are the only auth mechanism — there is NO auto-login and NO
 * hardcoded user id. Every protected request must pass Auth::check().
 */
final class Auth
{
    private const USER_KEY = '_auth_user_id';

    public static function attempt(string $email, string $password): array
    {
        $email = strtolower(trim($email));

        if (RateLimiter::isLockedOut($email)) {
            return ['ok' => false, 'error' => 'Too many failed attempts. Try again later.'];
        }

        $user = Database::fetch(
            'SELECT * FROM slc_users WHERE email = :email AND deleted_at IS NULL LIMIT 1',
            ['email' => $email]
        );

        if (!$user || !Security::verifyPassword($password, $user['password_hash'])) {
            RateLimiter::recordFailure($email);
            return ['ok' => false, 'error' => 'Invalid email or password.'];
        }

        if ((int) $user['is_active'] !== 1) {
            return ['ok' => false, 'error' => 'Account is disabled. Contact an administrator.'];
        }

        RateLimiter::clear($email);

        // Regenerate session id after successful login (fixation protection)
        Session::regenerate();
        Session::set(self::USER_KEY, (int) $user['id']);
        Session::set('_auth_login_at', time());

        // refresh last_login
        Database::update('slc_users', (int) $user['id'], [
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);

        self::logActivity('login', 'Signed in');

        return ['ok' => true, 'user' => self::current()];
    }

    public static function check(): bool
    {
        return Session::has(self::USER_KEY);
    }

    public static function id(): ?int
    {
        $v = Session::get(self::USER_KEY);
        return $v === null ? null : (int) $v;
    }

    public static function current(): ?array
    {
        $id = self::id();
        if ($id === null) {
            return null;
        }
        $user = Database::fetch(
            'SELECT id, name, email, role, permissions, is_active, created_at, last_login_at
             FROM slc_users WHERE id = :id AND deleted_at IS NULL LIMIT 1',
            ['id' => $id]
        );
        if (!$user || (int) $user['is_active'] !== 1) {
            self::logout();
            return null;
        }
        return $user;
    }

    /** Check if currently authenticated user has a specific permission. */
    public static function can(string $permission, ?array $user = null): bool
    {
        $user ??= self::current();
        return Permissions::check($user, $permission);
    }

    /** Check if current user has administrator role. */
    public static function isAdmin(?array $user = null): bool
    {
        $user ??= self::current();
        return $user !== null && strtolower((string) ($user['role'] ?? '')) === 'admin';
    }

    /**
     * Get the active data scope for the current user.
     * If user is Admin, returns null (all records).
     * If user is Normal User / Sales Person, returns their user ID (isolated records).
     */
    public static function scopedUserId(): ?int
    {
        if (self::isAdmin()) {
            return null;
        }
        return self::id();
    }

    public static function logout(): void
    {
        if (self::check()) {
            self::logActivity('logout', 'Signed out');
        }
        Session::forget(self::USER_KEY);
        Session::destroy();
    }

    public static function changePassword(int $userId, string $current, string $new): array
    {
        $user = Database::fetch('SELECT * FROM slc_users WHERE id = :id', ['id' => $userId]);
        if (!$user || !Security::verifyPassword($current, $user['password_hash'])) {
            return ['ok' => false, 'error' => 'Current password is incorrect.'];
        }
        if (strlen($new) < 8) {
            return ['ok' => false, 'error' => 'New password must be at least 8 characters.'];
        }
        Database::update('slc_users', $userId, ['password_hash' => Security::hashPassword($new)]);
        return ['ok' => true];
    }

    public static function logActivity(string $type, string $description, ?int $companyId = null, ?int $leadId = null): void
    {
        try {
            Database::insert('slc_activities', [
                'user_id'      => self::id(),
                'company_id'   => $companyId,
                'lead_id'      => $leadId,
                'type'         => $type,
                'description'  => $description,
            ]);
        } catch (\Throwable $e) {
            // activity logging must never break the request
        }
    }

    /** Guard for web pages: redirect to login. */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            $base = Config::basePath();
            header('Location: ' . $base . '/login.php');
            exit;
        }
    }

    /** Guard for API endpoints: respond 401 JSON. */
    public static function requireApiAuth(): void
    {
        if (!self::check()) {
            Response::json(['error' => 'Unauthenticated.'], 401);
            exit;
        }
    }

    /** Guard for API endpoints: require specific permission or respond 403 Forbidden. */
    public static function requirePermission(string $permission): void
    {
        self::requireApiAuth();
        if (!self::can($permission)) {
            Response::error('Access denied. You do not have permission to perform this action.', 403);
            exit;
        }
    }
}
