<?php

declare(strict_types=1);

namespace App\Util;

final class Validate
{
    /** @return list<string> */
    public static function required(mixed $value, string $field): array
    {
        if ($value === null || trim((string)$value) === '') {
            return ["{$field} is required."];
        }
        return [];
    }

    /** @return list<string> */
    public static function email(string $value, string $field = 'Email'): array
    {
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return ["{$field} must be a valid email address."];
        }
        return [];
    }

    /** @return list<string> */
    public static function minLength(string $value, int $min, string $field): array
    {
        if (strlen($value) < $min) {
            return ["{$field} must be at least {$min} characters."];
        }
        return [];
    }

    /** @return list<string> */
    public static function maxLength(string $value, int $max, string $field): array
    {
        if (strlen($value) > $max) {
            return ["{$field} must not exceed {$max} characters."];
        }
        return [];
    }

    /**
     * Merge multiple error arrays into one.
     * @param list<string> ...$results
     * @return list<string>
     */
    public static function merge(array ...$results): array
    {
        return array_merge(...$results);
    }
}
