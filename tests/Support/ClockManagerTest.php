<?php

declare(strict_types=1);

namespace Wayfinder\Tests\Support;

use Carbon\CarbonImmutable;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Wayfinder\Support\Clock;
use Wayfinder\Support\ClockManager;
use Wayfinder\Support\Date;

final class ClockManagerTest extends TestCase
{
    public function testFreezeLocksCurrentTimeUsingCarbon(): void
    {
        $clock = new ClockManager();
        $clock->freeze('2026-05-01 09:30:00', 'UTC');

        $now = $clock->now();

        self::assertInstanceOf(Clock::class, $clock);
        self::assertInstanceOf(CarbonImmutable::class, $now);
        self::assertSame('2026-05-01 09:30:00', $now->format('Y-m-d H:i:s'));
        self::assertTrue($clock->isFrozen());
    }

    public function testTravelUsesCarbonRelativeModifiers(): void
    {
        $clock = (new ClockManager())->freeze('2026-05-01 09:30:00', 'UTC');

        $clock->travel('+30 days');

        self::assertSame('2026-05-31 09:30:00', $clock->now()->format('Y-m-d H:i:s'));
    }

    public function testTravelAcceptsIntervalsAndSeconds(): void
    {
        $clock = (new ClockManager())->freeze('2026-05-01 09:30:00', 'UTC');

        $clock->travel(new DateInterval('P1D'))->travel(90);

        self::assertSame('2026-05-02 09:31:30', $clock->now()->format('Y-m-d H:i:s'));
    }

    public function testNowCanProjectFrozenTimeIntoTimezone(): void
    {
        $clock = (new ClockManager())->freeze(new DateTimeImmutable('2026-05-01 12:00:00', new DateTimeZone('UTC')));

        $now = $clock->now(new DateTimeZone('America/New_York'));

        self::assertSame('America/New_York', $now->getTimezone()->getName());
        self::assertSame('2026-05-01 08:00:00', $now->format('Y-m-d H:i:s'));
    }

    public function testResetReturnsToSystemTime(): void
    {
        $clock = (new ClockManager())->freeze('2026-05-01 09:30:00', 'UTC');

        $clock->reset();

        self::assertFalse($clock->isFrozen());
        self::assertNotSame('2026-05-01 09:30:00', $clock->now(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
    }

    public function testScopedFreezeRestoresPreviousTime(): void
    {
        $clock = (new ClockManager())->freeze('2026-05-01 09:30:00', 'UTC');

        $inside = $clock->scoped('2026-06-01 10:00:00', static fn (ClockManager $clock): string => $clock->now()->format('Y-m-d H:i:s'));

        self::assertSame('2026-06-01 10:00:00', $inside);
        self::assertSame('2026-05-01 09:30:00', $clock->now()->format('Y-m-d H:i:s'));
    }

    public function testDateNowCanUseClockManager(): void
    {
        $clock = (new ClockManager())->freeze('2026-05-01 09:30:00', 'UTC');

        self::assertSame('2026-05-01 09:30:00', Date::now($clock)->format('Y-m-d H:i:s'));
    }
}
