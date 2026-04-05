<?php
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
