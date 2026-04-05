<?php

declare(strict_types=1);

namespace App\Tests\Util;

use App\Util\Validate;
use PHPUnit\Framework\TestCase;

final class ValidateTest extends TestCase
{
    public function testRequiredRejectsEmptyValues(): void
    {
        $this->assertSame(['Email is required.'], Validate::required('', 'Email'));
        $this->assertSame(['Email is required.'], Validate::required('   ', 'Email'));
    }

    public function testEmailValidation(): void
    {
        $this->assertSame([], Validate::email('user@example.com'));
        $this->assertSame(['Email must be a valid email address.'], Validate::email('not-an-email'));
    }

    public function testLengthRules(): void
    {
        $this->assertSame([], Validate::minLength('hello', 5, 'Password'));
        $this->assertSame(['Password must be at least 6 characters.'], Validate::minLength('hello', 6, 'Password'));
        $this->assertSame([], Validate::maxLength('hello', 5, 'Password'));
        $this->assertSame(['Password must not exceed 4 characters.'], Validate::maxLength('hello', 4, 'Password'));
    }
}
