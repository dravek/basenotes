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
