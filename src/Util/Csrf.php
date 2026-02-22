<?php

declare(strict_types=1);

namespace App\Util;

final class Csrf
{
    private const string SESSION_KEY = 'csrf_token';
    private const int TOKEN_BYTES = 32;

    public static function generate(): string
    {
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $_SESSION[self::SESSION_KEY] = $token;
        return $token;
    }

    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            return self::generate();
        }
        return $_SESSION[self::SESSION_KEY];
    }

    public static function validate(string $submitted): bool
    {
        $stored = $_SESSION[self::SESSION_KEY] ?? '';
        if ($stored === '') {
            return false;
        }
        return hash_equals($stored, $submitted);
    }
}
