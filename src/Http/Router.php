<?php

declare(strict_types=1);

namespace App\Http;

enum Method: string
{
    case GET    = 'GET';
    case POST   = 'POST';
    case PATCH  = 'PATCH';
    case DELETE = 'DELETE';
}

final class Router
{
    /** @var list<array{method: Method, pattern: string, handler: callable, params: list<string>}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add(Method::GET, $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add(Method::POST, $pattern, $handler);
    }

    public function patch(string $pattern, callable $handler): void
    {
        $this->add(Method::PATCH, $pattern, $handler);
    }

    public function delete(string $pattern, callable $handler): void
    {
        $this->add(Method::DELETE, $pattern, $handler);
    }

    private function add(Method $method, string $pattern, callable $handler): void
    {
        preg_match_all('/\{(\w+)\}/', $pattern, $matches);
        $params  = $matches[1];
        $regex   = preg_replace('/\{(\w+)\}/', '([^/]+)', $pattern);
        $this->routes[] = [
            'method'  => $method,
            'pattern' => '#^' . $regex . '$#',
            'handler' => $handler,
            'params'  => $params,
        ];
    }

    public function dispatch(Request $request): never
    {
        $method = $request->method();
        $uri    = $request->uri();

        foreach ($this->routes as $route) {
            if ($route['method']->value !== $method) {
                continue;
            }
            if (preg_match($route['pattern'], $uri, $matches) !== 1) {
                continue;
            }
            array_shift($matches);
            $args = array_combine($route['params'], $matches) ?: [];
            ($route['handler'])($request, $args);
            exit;
        }

        http_response_code(404);
        echo '<!DOCTYPE html><html><body><h1>404 Not Found</h1></body></html>';
        exit;
    }
}
