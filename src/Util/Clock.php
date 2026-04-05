<?php

declare(strict_types=1);

namespace App\Util;

final class Clock
{
    public static function now(): int
    {
        return time();
    }
}
