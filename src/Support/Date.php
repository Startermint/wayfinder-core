<?php

declare(strict_types=1);

namespace Wayfinder\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;

final class Date
{
    public static function now(Clock|DateTimeZone|string|null $clockOrTimezone = null, DateTimeZone|string|null $timezone = null): CarbonImmutable
    {
        if ($clockOrTimezone instanceof Clock) {
            return self::parse($clockOrTimezone->now(self::timezone($timezone)));
        }

        return CarbonImmutable::now($timezone ?? $clockOrTimezone);
    }

    public static function today(Clock|DateTimeZone|string|null $clockOrTimezone = null, DateTimeZone|string|null $timezone = null): CarbonImmutable
    {
        return self::now($clockOrTimezone, $timezone)->startOfDay();
    }

    public static function yesterday(DateTimeZone|string|null $timezone = null): CarbonImmutable
    {
        return self::today($timezone)->subDay();
    }

    public static function tomorrow(DateTimeZone|string|null $timezone = null): CarbonImmutable
    {
        return self::today($timezone)->addDay();
    }

    public static function parse(string|DateTimeInterface $value, DateTimeZone|string|null $timezone = null): CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->setTimezone(self::timezone($timezone) ?? $value->getTimezone());
        }

        return CarbonImmutable::parse($value, $timezone);
    }

    public static function tryParse(mixed $value, DateTimeZone|string|null $timezone = null): ?CarbonImmutable
    {
        if (! is_string($value) && ! $value instanceof DateTimeInterface) {
            return null;
        }

        try {
            return self::parse($value, $timezone);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function fromTimestamp(int $timestamp, DateTimeZone|string|null $timezone = null): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestamp($timestamp, $timezone);
    }

    public static function toUtc(DateTimeInterface $value): CarbonImmutable
    {
        return self::parse($value, 'UTC');
    }

    public static function startOfDay(DateTimeInterface $value): CarbonImmutable
    {
        return self::parse($value)->startOfDay();
    }

    public static function endOfDay(DateTimeInterface $value): CarbonImmutable
    {
        return self::parse($value)->endOfDay();
    }

    public static function addDays(DateTimeInterface $value, int $days): CarbonImmutable
    {
        return self::parse($value)->addDays($days);
    }

    public static function subDays(DateTimeInterface $value, int $days): CarbonImmutable
    {
        return self::parse($value)->subDays($days);
    }

    private static function timezone(DateTimeZone|string|null $timezone): ?DateTimeZone
    {
        if ($timezone === null || $timezone instanceof DateTimeZone) {
            return $timezone;
        }

        return new DateTimeZone($timezone);
    }
}
