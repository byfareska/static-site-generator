<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Tests\Unit;

use Byfareska\StaticSite\Endpoint\FormToken;
use Byfareska\StaticSite\Endpoint\RateLimits;
use PHPUnit\Framework\TestCase;

final class FormTokenTest extends TestCase
{
    public function testAToken(): void
    {
        $tokens = new FormToken('secret');
        $token = $tokens->issue(1000);

        self::assertNull($tokens->refusal($token, 3, 1010));
        self::assertSame('too_fast', $tokens->refusal($token, 3, 1001));
        self::assertSame('expired', $tokens->refusal($token, 3, 1000 + FormToken::MAX_AGE + 1));
        self::assertSame('forged', (new FormToken('other'))->refusal($token, 3, 1010));
        self::assertSame('missing', $tokens->refusal(null, 3));
        self::assertSame('missing', $tokens->refusal('garbage', 3));
    }

    public function testRateLimitNotation(): void
    {
        self::assertSame([5, '10 minutes'], RateLimits::parse('5/10min'));
        self::assertSame([100, '1 hours'], RateLimits::parse('100/1h'));
        self::assertSame([3, '1 days'], RateLimits::parse('3/d'));
        $this->expectException(\InvalidArgumentException::class);
        RateLimits::parse('five per minute');
    }
}
