<?php

declare(strict_types=1);

namespace Wayfinder\Tests\Support;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Wayfinder\Support\Date;
use Wayfinder\Support\SystemClock;
use Wayfinder\Testing\FrozenClock;

final class DateTest extends TestCase
{
    public function testNowUsesProvidedClock(): void
    {
        $frozen = new DateTimeImmutable('2026-04-28 09:30:00', new DateTimeZone('America/New_York'));
        $clock = new FrozenClock($frozen);

        $now = Date::now($clock);

        self::assertInstanceOf(CarbonImmutable::class, $now);
        self::assertSame($frozen->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s'));
        self::assertSame('America/New_York', $now->getTimezone()->getName());
    }

    public function testTodayReturnsStartOfDayFromClock(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-04-28 18:45:12', new DateTimeZone('UTC')));

        $today = Date::today($clock);

        self::assertSame('2026-04-28 00:00:00.000000', $today->format('Y-m-d H:i:s.u'));
        self::assertSame('UTC', $today->getTimezone()->getName());
    }

    public function testNowKeepsLegacyNullableClockTimezoneArgument(): void
    {
        $now = Date::now(null, new DateTimeZone('America/New_York'));

        self::assertSame('America/New_York', $now->getTimezone()->getName());
    }

    public function testParseUsesExplicitTimezoneWhenProvided(): void
    {
        $parsed = Date::parse('2026-04-28 12:00:00', new DateTimeZone('America/Chicago'));

        self::assertInstanceOf(CarbonImmutable::class, $parsed);
        self::assertSame('America/Chicago', $parsed->getTimezone()->getName());
        self::assertSame('2026-04-28 12:00:00', $parsed->format('Y-m-d H:i:s'));
    }

    public function testFromTimestampUsesProvidedTimezone(): void
    {
        $date = Date::fromTimestamp(0, new DateTimeZone('America/New_York'));

        self::assertInstanceOf(CarbonImmutable::class, $date);
        self::assertSame('America/New_York', $date->getTimezone()->getName());
        self::assertSame('1969-12-31 19:00:00', $date->format('Y-m-d H:i:s'));
    }

    public function testTryParseReturnsNullForInvalidValues(): void
    {
        self::assertNull(Date::tryParse([]));
        self::assertNull(Date::tryParse('not-a-real-date-value-%%%'));
    }

    public function testToUtcConvertsMutableDateTimeToImmutableUtc(): void
    {
        $local = new \DateTime('2026-04-28 09:00:00', new DateTimeZone('America/Los_Angeles'));

        $utc = Date::toUtc($local);

        self::assertInstanceOf(DateTimeImmutable::class, $utc);
        self::assertSame('UTC', $utc->getTimezone()->getName());
        self::assertSame('2026-04-28 16:00:00', $utc->format('Y-m-d H:i:s'));
    }

    public function testStartAndEndOfDayPreserveDateTimezone(): void
    {
        $date = new DateTimeImmutable('2026-04-28 13:15:30', new DateTimeZone('America/New_York'));

        $start = Date::startOfDay($date);
        $end = Date::endOfDay($date);

        self::assertSame('2026-04-28 00:00:00.000000', $start->format('Y-m-d H:i:s.u'));
        self::assertSame('2026-04-28 23:59:59.999999', $end->format('Y-m-d H:i:s.u'));
        self::assertSame('America/New_York', $start->getTimezone()->getName());
        self::assertSame('America/New_York', $end->getTimezone()->getName());
    }

    public function testAddAndSubDaysReturnShiftedImmutableDates(): void
    {
        $date = new DateTimeImmutable('2026-04-28 13:15:30', new DateTimeZone('UTC'));

        self::assertSame('2026-05-01 13:15:30', Date::addDays($date, 3)->format('Y-m-d H:i:s'));
        self::assertSame('2026-04-25 13:15:30', Date::subDays($date, 3)->format('Y-m-d H:i:s'));
        self::assertSame('2026-04-28 13:15:30', $date->format('Y-m-d H:i:s'));
    }

    public function testSystemClockRespectsRequestedTimezone(): void
    {
        $now = (new SystemClock())->now(new DateTimeZone('UTC'));

        self::assertSame('UTC', $now->getTimezone()->getName());
    }
}
