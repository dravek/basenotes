<?php

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
