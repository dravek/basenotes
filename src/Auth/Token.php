<?php

declare(strict_types=1);

namespace App\Auth;

final class Token
{
    private const string PREFIX     = 'nt_';
    private const int    TOKEN_BYTES = 32;

    public static function generate(): string
    {
        $raw = self::PREFIX . rtrim(strtr(base64_encode(random_bytes(self::TOKEN_BYTES)), '+/', '-_'), '=');
        return $raw;
    }

    public static function hash(string $raw, string $pepper): string
    {
        return hash_hmac('sha256', $raw, $pepper);
    }

    public static function verify(string $raw, string $storedHash, string $pepper): bool
    {
        return hash_equals($storedHash, self::hash($raw, $pepper));
    }
}
