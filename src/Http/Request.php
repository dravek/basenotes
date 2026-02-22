<?php

declare(strict_types=1);

namespace App\Http;

final class Request
{
    private readonly string $method;
    private readonly string $uri;
    private readonly array  $query;
    private readonly array  $post;
    private readonly array  $server;
    private readonly array  $cookies;
    private ?string $rawBody = null;

    public function __construct()
    {
        $this->method  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri           = $_SERVER['REQUEST_URI'] ?? '/';
        // Strip query string from URI
        $this->uri     = parse_url($uri, PHP_URL_PATH) ?: '/';
        $this->query   = $_GET;
        $this->post    = $_POST;
        $this->server  = $_SERVER;
        $this->cookies = $_COOKIE;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function query(string $key, string $default = ''): string
    {
        return isset($this->query[$key]) ? (string)$this->query[$key] : $default;
    }

    public function post(string $key, string $default = ''): string
    {
        return isset($this->post[$key]) ? (string)$this->post[$key] : $default;
    }

    public function allPost(): array
    {
        return $this->post;
    }

    public function header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return (string)($this->server[$key] ?? '');
    }

    public function bearerToken(): string|null
    {
        $auth = $this->header('Authorization');
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }

    public function rawBody(): string
    {
        if ($this->rawBody === null) {
            $this->rawBody = (string)file_get_contents('php://input');
        }
        return $this->rawBody;
    }

    public function isJson(): bool
    {
        $ct = $this->server['CONTENT_TYPE'] ?? '';
        return str_contains($ct, 'application/json');
    }

    public function ip(): string
    {
        return (string)($this->server['REMOTE_ADDR'] ?? '');
    }
}
