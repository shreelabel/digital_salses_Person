<?php
declare(strict_types=1);

namespace SLC\Core;

/**
 * Brute-force protection for login. Tracks failures per (ip + email) using a
 * file-backed store under storage/framework. Decoupled from the DB so it works
 * even during setup and never touches the ERP database.
 */
final class RateLimiter
{
    private static function dir(): string
    {
        $dir = SLC_ROOT . '/storage/framework/rate_limits';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        return $dir;
    }

    private static function file(string $email): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        $key = md5($ip . '|' . strtolower((string) $email));
        return self::dir() . '/' . $key . '.json';
    }

    private static function read(string $file): array
    {
        if (!is_file($file)) {
            return ['count' => 0, 'first' => time(), 'locked_until' => 0];
        }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data + ['count' => 0, 'first' => time(), 'locked_until' => 0]
                               : ['count' => 0, 'first' => time(), 'locked_until' => 0];
    }

    private static function write(string $file, array $data): void
    {
        $fp = fopen($file, 'c+');
        if (!$fp) {
            return;
        }
        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data));
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }

    public static function recordFailure(string $email): void
    {
        $file = self::file($email);
        $fp = fopen($file, 'c+');
        if (!$fp) {
            return;
        }
        if (flock($fp, LOCK_EX)) {
            // READ existing data FIRST, then truncate + write (order matters).
            rewind($fp);
            $raw = stream_get_contents($fp);
            $data = ['count' => 0, 'first' => time(), 'locked_until' => 0];
            if ($raw) {
                $tmp = json_decode($raw, true);
                if (is_array($tmp)) {
                    $data = $tmp + $data;
                }
            }
            // reset window after lockout period elapses
            if (time() > ($data['locked_until'] ?? 0) && (time() - ($data['first'] ?? time())) > Config::loginLockoutSeconds()) {
                $data = ['count' => 0, 'first' => time(), 'locked_until' => 0];
            }
            $data['count']++;
            if ($data['count'] >= Config::loginMaxAttempts()) {
                $data['locked_until'] = time() + Config::loginLockoutSeconds();
            }
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data));
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }

    public static function isLockedOut(string $email): bool
    {
        $data = self::read(self::file($email));
        return ($data['locked_until'] ?? 0) > time();
    }

    public static function remaining(string $email): int
    {
        $data = self::read(self::file($email));
        return max(0, Config::loginMaxAttempts() - (int) ($data['count'] ?? 0));
    }

    public static function clear(string $email): void
    {
        $file = self::file($email);
        if (is_file($file)) {
            @unlink($file);
        }
    }
}
