<?php

namespace App\Core;

class Router
{
    private array $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'PATCH' => [],
        'DELETE' => [],
    ];

    public function get(string $uri, callable $action): void
    {
        $this->register('GET', $uri, $action);
    }

    public function post(string $uri, callable $action): void
    {
        $this->register('POST', $uri, $action);
    }

    public function put(string $uri, callable $action): void
    {
        $this->register('PUT', $uri, $action);
    }

    public function patch(string $uri, callable $action): void
    {
        $this->register('PATCH', $uri, $action);
    }

    public function delete(string $uri, callable $action): void
    {
        $this->register('DELETE', $uri, $action);
    }

    private function register(string $method, string $uri, callable $action): void
    {
        $this->routes[$method][$this->normalizeUri($uri)] = $action;
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        $uri = $this->normalizeUri(parse_url($uri, PHP_URL_PATH) ?? '/');

        $action = $this->routes[$method][$uri] ?? null;

        if (!$action) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Route not found']);
            return;
        }

        call_user_func($action);
    }

    private function normalizeUri(string $uri): string
    {
        return '/' . trim($uri, '/');
    }
}
