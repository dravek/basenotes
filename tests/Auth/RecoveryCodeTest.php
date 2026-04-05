<?php

declare(strict_types=1);

namespace App\Tests\Auth;

use App\Auth\RecoveryCode;
use PHPUnit\Framework\TestCase;

final class RecoveryCodeTest extends TestCase
{
    public function testGenerateFormatNormalizeAndHash(): void
    {
        $codes = RecoveryCode::generateBatch(3);

        $this->assertCount(3, $codes);
        $this->assertSame([], array_diff($codes, array_unique($codes)));

        $raw = 'ab cd-12';
        $normalized = RecoveryCode::normalize($raw);
        $formatted = RecoveryCode::format($raw);

        $this->assertSame('ABCD12', $normalized);
        $this->assertSame('ABCD1-2', $formatted);

        $pepper = 'pepper-value';
        $this->assertSame(hash_hmac('sha256', $normalized, $pepper), RecoveryCode::hash($normalized, $pepper));
    }
}
