<?php
declare(strict_types=1);

/**
 * Router for PHP's built-in dev server (testing only).
 * `php -S 127.0.0.1:8765 -t . server.php`
 * Serves real static assets; routes everything else to the front controller.
 */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
// Serve existing static files directly (assets, etc.)
if ($path !== '/' && preg_match('#\.(css|js|png|jpe?g|gif|svg|ico|woff2?|ttf|map)$#', $path)) {
    $candidate = __DIR__ . $path;
    if (is_file($candidate)) {
        return false; // built-in server serves it
    }
}
// Everything else -> front controller
require __DIR__ . '/index.php';
