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

namespace App\Auth;

final class RecoveryCode
{
    private const string ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const int DEFAULT_LENGTH = 10;
    private const int GROUP_SIZE = 5;
    private const int MAX_BATCH = 10;

    /** @return list<string> Raw codes (ungrouped). */
    public static function generateBatch(int $count = self::MAX_BATCH): array
    {
        if ($count > self::MAX_BATCH) {
            $count = self::MAX_BATCH;
        }
        if ($count < 1) {
            $count = 1;
        }

        $codes = [];
        while (count($codes) < $count) {
            $raw = self::generateRaw(self::DEFAULT_LENGTH);
            if (!in_array($raw, $codes, true)) {
                $codes[] = $raw;
            }
        }
        return $codes;
    }

    public static function format(string $raw): string
    {
        $normalized = self::normalize($raw);
        if ($normalized === '') {
            return '';
        }
        return implode('-', str_split($normalized, self::GROUP_SIZE));
    }

    public static function normalize(string $input): string
    {
        $upper = strtoupper($input);
        return preg_replace('/[^A-Z0-9]/', '', $upper) ?? '';
    }

    public static function hash(string $raw, string $pepper): string
    {
        return hash_hmac('sha256', $raw, $pepper);
    }

    private static function generateRaw(int $length): string
    {
        $alphabet = self::ALPHABET;
        $max = strlen($alphabet) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }
        return $out;
    }
}
