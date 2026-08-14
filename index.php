<?php
declare(strict_types=1);

/**
 * SLC AI Sales Agent — Front Controller (entry point).
 * ----------------------------------------------------
 * Serves both the REST API (api/*) and the server-rendered pages.
 * All requests are routed here by .htaccess (mod_rewrite). If rewrite is
 * unavailable, this still works via index.php?r=<route>.
 *
 * Routes starting with "api/" are JSON endpoints. Everything else is a page
 * rendered through templates/* and requires an authenticated session.
 */
require __DIR__ . '/src/bootstrap.php';

use SLC\Core\Config;
use SLC\Core\Session;
use SLC\Core\Auth;
use SLC\Core\CSRF;
use SLC\Core\Response;
use SLC\Core\Database;
use SLC\Core\Router;
use SLC\Controllers\PageController;

Session::start();

// ---- Resolve the route relative to this script's directory ----
function slc_request_route(): string
{
    if (isset($_GET['r']) && is_string($_GET['r']) && $_GET['r'] !== '') {
        return trim($_GET['r'], '/');
    }
    $base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $base = rtrim($base, '/');
    $uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    if ($base !== '' && $base !== '/' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base));
    }
    return trim($uri, '/');
}

function slc_web_root(): string
{
    // Prefer the configured base path, fall back to the script directory.
    $configured = Config::basePath();
    if ($configured !== '') {
        return $configured;
    }
    $base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    return rtrim($base, '/');
}

$route = slc_request_route();

// ============ API ROUTES ============
if ($route !== '' && str_starts_with($route, 'api/')) {
    $apiRoute = substr($route, 4);

    // CORS / security headers
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');

    // Auth: every API endpoint requires a session EXCEPT login and logout
    $public = ($apiRoute === 'auth/login' || $apiRoute === 'auth/logout');
    if (!$public) {
        Auth::requireApiAuth();
    }
    // CSRF for state-changing requests (skip for login and logout)
    if (!$public) {
        CSRF::guard($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    if (!Database::isReady()) {
        Response::error('Database is not installed. Run setup.php first.', 503);
        exit;
    }

    $router = new Router();
    (require SLC_ROOT . '/routes/api.php')($router);
    $router->dispatch($apiRoute, $_SERVER['REQUEST_METHOD'] ?? 'GET');
    exit;
}

// ============ WEB PAGES ============
// login.php is a standalone file; the login route renders there.
if ($route === 'login') {
    require __DIR__ . '/login.php';
    exit;
}
if ($route === 'logout') {
    Auth::logout();
    header('Location: ' . slc_web_root() . '/login.php?logged_out=1');
    exit;
}

// Everything else requires authentication
Auth::requireLogin();

if (!Database::isReady()) {
    header('Location: ' . slc_web_root() . '/setup.php');
    exit;
}

$page = $route === '' ? 'dashboard' : $route;
$controller = new PageController(slc_web_root());
$controller->render($page);
