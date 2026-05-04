<?php
namespace App\Core;

class Router
{
    private array $routes = ['GET' => [], 'POST' => []];
    private array $middlewareStack = [];

    public function get(string $path, $handler, array $middleware = []): void
    {
        $this->routes['GET'][$this->normalize($path)] = compact('handler', 'middleware');
    }

    public function post(string $path, $handler, array $middleware = []): void
    {
        $this->routes['POST'][$this->normalize($path)] = compact('handler', 'middleware');
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

        // Strip the base subdirectory so routes work under /SSDACMIS/public
        // (or any other folder name like /schoolreg/public) on XAMPP and at
        // the document root in production.
        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
        if ($scriptDir !== '' && str_starts_with($uri, $scriptDir)) {
            $uri = substr($uri, strlen($scriptDir));
        }
        $uri = $this->normalize($uri);

        $routes = $this->routes[$method] ?? [];

        foreach ($routes as $pattern => $route) {
            $params = [];
            if ($this->match($pattern, $uri, $params)) {
                foreach ($route['middleware'] as $mw) {
                    if (is_callable($mw)) $mw();
                }
                $this->call($route['handler'], $params);
                return;
            }
        }

        http_response_code(404);
        echo View::render('errors/404');
    }

    private function normalize(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '' ? '/' : $path;
    }

    private function match(string $pattern, string $uri, array &$params): bool
    {
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';
        if (preg_match($regex, $uri, $m)) {
            foreach ($m as $k => $v) {
                if (!is_int($k)) $params[$k] = $v;
            }
            return true;
        }
        return false;
    }

    private function call($handler, array $params): void
    {
        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler);
            $fqcn = "App\\Controllers\\$class";
            $instance = new $fqcn();
            echo $instance->$method(...array_values($params));
            return;
        }
        if (is_callable($handler)) {
            echo $handler(...array_values($params));
        }
    }
}
