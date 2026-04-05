<?php

declare(strict_types=1);

namespace App\Tests\Util;

use App\Util\Slug;
use PHPUnit\Framework\TestCase;

final class SlugTest extends TestCase
{
    public function testSlugFromNormalizesText(): void
    {
        $this->assertSame('work-stuff', Slug::from(' Work Stuff '));
        $this->assertSame('hello-world', Slug::from('Hello, World!'));
        $this->assertSame('', Slug::from('   '));
    }
}
