<?php

declare(strict_types=1);

namespace App\Tests\Auth;

use App\Auth\Password;
use PHPUnit\Framework\TestCase;

final class PasswordTest extends TestCase
{
    public function testHashAndVerify(): void
    {
        $hash = Password::hash('super-secret-password');

        $this->assertTrue(Password::verify('super-secret-password', $hash));
        $this->assertFalse(Password::verify('wrong-password', $hash));
    }
}
