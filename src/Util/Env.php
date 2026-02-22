<?php

declare(strict_types=1);

namespace App\Util;

final class Env
{
    private static array $vars = [];
    private static bool $loaded = false;

    /** @param list<string> $required */
    public static function load(string $path, array $required = []): void
    {
        if ($path !== '' && file_exists($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                if (!str_contains($line, '=')) {
                    continue;
                }
                [$key, $value] = explode('=', $line, 2);
                $key   = trim($key);
                $value = trim($value);
                self::$vars[$key] = $value;
                if (!isset($_ENV[$key])) {
                    $_ENV[$key] = $value;
                }
            }
        }

        // Pull in actual environment variables (Docker env vars take precedence)
        foreach ($_ENV as $key => $value) {
            if (is_string($value)) {
                self::$vars[$key] = $value;
            }
        }
        foreach (getenv() ?: [] as $key => $value) {
            self::$vars[$key] = $value;
        }

        foreach ($required as $key) {
            if (empty(self::$vars[$key])) {
                throw new \RuntimeException(
                    "Required environment variable '{$key}' is missing or empty. " .
                    "Check your .env file."
                );
            }
        }

        self::$loaded = true;
    }

    public static function get(string $key): string
    {
        if (!self::$loaded) {
            throw new \RuntimeException('Env::load() has not been called.');
        }
        if (!array_key_exists($key, self::$vars)) {
            throw new \RuntimeException("Environment variable '{$key}' is not defined.");
        }
        return self::$vars[$key];
    }
}
