<?php

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, callable|array $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $uriPath = parse_url($uri, PHP_URL_PATH) ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $pattern = '#^' . preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '([a-zA-Z0-9\-_]+)', $route['path']) . '$#';

            if (!preg_match($pattern, $uriPath, $matches)) {
                continue;
            }

            array_shift($matches);

            $handler = $route['handler'];
            if (is_array($handler) && is_string($handler[0])) {
                $controller = new $handler[0]();
                $methodName = $handler[1];
                $controller->{$methodName}(...$matches);
                return;
            }

            $handler(...$matches);
            return;
        }

        http_response_code(404);
        echo '404 Not Found';
    }
}
