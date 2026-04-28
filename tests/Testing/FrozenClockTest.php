<?php

declare(strict_types=1);

namespace Wayfinder\Tests\Testing;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Wayfinder\Testing\FrozenClock;

final class FrozenClockTest extends TestCase
{
    public function testNowReturnsFrozenInstant(): void
    {
        $frozen = new DateTimeImmutable('2026-04-28 12:00:00', new DateTimeZone('UTC'));
        $clock = new FrozenClock($frozen);

        self::assertSame($frozen, $clock->now());
    }

    public function testNowCanProjectFrozenInstantIntoAnotherTimezone(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-04-28 12:00:00', new DateTimeZone('UTC')));

        $shifted = $clock->now(new DateTimeZone('America/New_York'));

        self::assertSame('America/New_York', $shifted->getTimezone()->getName());
        self::assertSame('2026-04-28 08:00:00', $shifted->format('Y-m-d H:i:s'));
    }
}
