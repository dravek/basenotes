<?php
/**
 * Copyright (c) 2026 David Carrillo <dravek@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

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
        if (isset($this->server[$key])) {
            return (string)$this->server[$key];
        }

        if ($name === 'Authorization' || $key === 'HTTP_AUTHORIZATION') {
            if (isset($this->server['REDIRECT_HTTP_AUTHORIZATION'])) {
                return (string)$this->server['REDIRECT_HTTP_AUTHORIZATION'];
            }
            if (isset($this->server['HTTP_X_AUTHORIZATION'])) {
                return (string)$this->server['HTTP_X_AUTHORIZATION'];
            }
        }

        return '';
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
