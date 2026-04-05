<?php

declare(strict_types=1);

namespace App\Util;

final class Slug
{
    public static function from(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        return $slug;
    }
}
