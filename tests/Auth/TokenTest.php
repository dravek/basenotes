<?php

declare(strict_types=1);

namespace App\Tests\Auth;

use App\Auth\Token;
use PHPUnit\Framework\TestCase;

final class TokenTest extends TestCase
{
    public function testGenerateAndVerify(): void
    {
        $raw = Token::generate();
        $pepper = '0123456789abcdef';
        $hash = Token::hash($raw, $pepper);

        $this->assertStringStartsWith('nt_', $raw);
        $this->assertTrue(Token::verify($raw, $hash, $pepper));
        $this->assertFalse(Token::verify($raw . 'x', $hash, $pepper));
    }
}
