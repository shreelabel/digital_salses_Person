<?php
declare(strict_types=1);

namespace SLC\Core;

/**
 * File logger writing under storage/logs. Never writes secrets.
 */
final class Logger
{
    public static function log(string $channel, string $message, array $context = []): void
    {
        $dir = SLC_ROOT . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = sprintf(
            "[%s] %s.%s: %s %s\n",
            date('Y-m-d H:i:s'),
            strtoupper((string) Config::get('APP_ENV', 'production')),
            $channel,
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : ''
        );
        @file_put_contents($dir . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }
}
