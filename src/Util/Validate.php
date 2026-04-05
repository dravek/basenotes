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
