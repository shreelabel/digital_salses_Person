<?php
declare(strict_types=1);

namespace SLC\Core;

/**
 * Minimal REST-style router. Routes are registered as
 * [METHOD, 'pattern/with/{id}', [Controller::class, 'method']].
 * Patterns support {param} segments captured into the handler args.
 */
final class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, array $handler): void
    {
        $this->routes[] = [strtoupper($method), trim($pattern, '/'), $handler];
    }

    public function dispatch(string $route, string $method): void
    {
        $route = trim($route, '/');
        $method = strtoupper($method);

        foreach ($this->routes as [$m, $pattern, $handler]) {
            if ($m !== $method && $m !== 'ANY') {
                continue;
            }
            $params = $this->match($pattern, $route);
            if ($params !== false) {
                [$class, $action] = $handler;
                if (!class_exists($class)) {
                    Response::error("Controller $class not found.", 500);
                    return;
                }
                $controller = new $class();
                if (!method_exists($controller, $action)) {
                    Response::error("Action $action not found on $class.", 500);
                    return;
                }
                try {
                    $controller->$action(...array_values($params));
                } catch (\Throwable $e) {
                    Logger::error('Route handler failed', [
                        'route' => $route, 'msg' => $e->getMessage(),
                    ]);
                    Response::error(
                        Config::debug() ? $e->getMessage() : 'Server error processing request.',
                        500
                    );
                }
                return;
            }
        }
        Response::notFound("No route for {$method} /{$route}");
    }

    /** @return array<string,string>|false */
    private function match(string $pattern, string $route): array|false
    {
        $pSeg = explode('/', $pattern);
        $rSeg = explode('/', $route);
        if (count($pSeg) !== count($rSeg)) {
            return false;
        }
        $params = [];
        for ($i = 0, $n = count($pSeg); $i < $n; $i++) {
            $ps = $pSeg[$i];
            $rs = $rSeg[$i];
            if (preg_match('/^\{(\w+)\}$/', $ps, $m)) {
                $params[$m[1]] = $rs;
            } elseif ($ps !== $rs) {
                return false;
            }
        }
        return $params;
    }
}
