<?php
declare(strict_types=1);

/**
 * Bootstrap: PSR-4-ish autoloader + environment load + error handling.
 * No Composer required (works on plain XAMPP). Composer is also supported
 * (see composer.json) — if composer dumpautoload was run, that autoloader
 * is used instead.
 */
define('SLC_ROOT', dirname(__DIR__));

// Prefer Composer's autoloader when present.
$composer = SLC_ROOT . '/vendor/autoload.php';
if (is_file($composer)) {
    require $composer;
}

spl_autoload_register(function (string $class): void {
    $prefix = 'SLC\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $relative = str_replace('\\', '/', $relative);
    // The Installer lives under the top-level database/ directory (per the
    // requested project structure), not src/Database/.
    if (str_starts_with($relative, 'Database/')) {
        $file = SLC_ROOT . '/' . strtolower(substr($relative, 0, 8)) . substr($relative, 8) . '.php';
    } else {
        $file = SLC_ROOT . '/src/' . $relative . '.php';
    }
    if (is_file($file)) {
        require $file;
    }
});

require SLC_ROOT . '/src/Core/Env.php';
require SLC_ROOT . '/src/Core/Config.php';
SLC\Core\Env::load();
require SLC_ROOT . '/src/helpers.php';

// In CLI/test runs, PHP cannot send session cookie headers, which generates
// noise but does not affect logic (the $_SESSION superglobal still works in
// process). Keep output focused on test results.
if (defined('SLC_TESTING')) {
    error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
    ini_set('display_errors', '0');
}

// Error handling — never leak internals in production.
if (SLC\Core\Config::debug()) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
}

date_default_timezone_set('Asia/Kolkata');
