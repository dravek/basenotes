<?php

declare(strict_types=1);

namespace App\Util;

final class Id
{
    // ULID encoding alphabet (Crockford Base32)
    private const string ENCODING = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    private const int ENCODING_LEN = 32;
    private const int TIME_LEN = 10;
    private const int RANDOM_LEN = 16;

    public static function ulid(): string
    {
        $timeMs = (int)(microtime(true) * 1000);

        // Encode timestamp (48 bits → 10 chars)
        $timePart = '';
        $t = $timeMs;
        for ($i = self::TIME_LEN - 1; $i >= 0; $i--) {
            $timePart = self::ENCODING[$t % self::ENCODING_LEN] . $timePart;
            $t = intdiv($t, self::ENCODING_LEN);
        }

        // Encode random (80 bits → 16 chars)
        $randomBytes = random_bytes(10);
        $randomPart  = '';
        $value = 0;
        $bits  = 0;
        foreach (str_split($randomBytes) as $byte) {
            $value = ($value << 8) | ord($byte);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $randomPart .= self::ENCODING[($value >> $bits) & 0x1F];
            }
        }
        // Pad to 16 chars if needed
        while (strlen($randomPart) < self::RANDOM_LEN) {
            $randomPart .= self::ENCODING[0];
        }

        return $timePart . $randomPart;
    }
}
