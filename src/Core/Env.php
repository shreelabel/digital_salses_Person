<?php
declare(strict_types=1);

namespace SLC\Core;

/**
 * Loads a .env file into the real environment (getenv / $_ENV / $_SERVER).
 * Lines beginning with # are comments. Supports KEY=VALUE and quotes.
 */
final class Env
{
    private static bool $loaded = false;

    public static function load(?string $path = null): void
    {
        if (self::$loaded) {
            return;
        }
        $path ??= dirname(__DIR__, 2) . '/.env';
        if (!is_file($path)) {
            self::$loaded = true;
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            // skip "export KEY=..."
            if (str_starts_with($line, 'export ')) {
                $line = substr($line, 7);
            }
            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }
            $key = trim(substr($line, 0, $eq));
            $value = trim(substr($line, $eq + 1));

            // strip inline comments outside quotes
            if (strlen($value) > 0 && $value[0] !== '"' && $value[0] !== "'") {
                $hashPos = strpos($value, ' #');
                if ($hashPos !== false) {
                    $value = substr($value, 0, $hashPos);
                }
                $value = trim($value);
            } elseif (strlen($value) > 0) {
                // value starts with a quote — remove surrounding quotes
                $quote = $value[0];
                $value = trim($value, $quote);
            } else {
                $value = trim($value);
            }

            if ($key === '') {
                continue;
            }
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }
        self::$loaded = true;
    }

    public static function set(string $key, mixed $value): void
    {
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv($key . '=' . (string)$value);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }
        $val = getenv($key);
        return $val === false ? $default : $val;
    }
}
