<?php

declare(strict_types=1);

namespace Wayfinder\Support;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

final class Date
{
    public static function now(?Clock $clock = null, ?DateTimeZone $timezone = null): DateTimeImmutable
    {
        return ($clock ?? new SystemClock())->now($timezone);
    }

    public static function today(?Clock $clock = null, ?DateTimeZone $timezone = null): DateTimeImmutable
    {
        return self::now($clock, $timezone)->setTime(0, 0, 0);
    }

    public static function parse(string $value, ?DateTimeZone $timezone = null): DateTimeImmutable
    {
        if ($timezone !== null) {
            return new DateTimeImmutable($value, $timezone);
        }

        return new DateTimeImmutable($value);
    }

    public static function fromTimestamp(int $timestamp, ?DateTimeZone $timezone = null): DateTimeImmutable
    {
        $date = new DateTimeImmutable('@' . $timestamp);

        return $date->setTimezone($timezone ?? new DateTimeZone(date_default_timezone_get()));
    }

    public static function toUtc(DateTimeInterface $date): DateTimeImmutable
    {
        return self::immutable($date)->setTimezone(new DateTimeZone('UTC'));
    }

    public static function startOfDay(DateTimeInterface $date): DateTimeImmutable
    {
        return self::immutable($date)->setTime(0, 0, 0);
    }

    public static function endOfDay(DateTimeInterface $date): DateTimeImmutable
    {
        return self::immutable($date)->setTime(23, 59, 59, 999999);
    }

    public static function addDays(DateTimeInterface $date, int $days): DateTimeImmutable
    {
        return self::immutable($date)->add(new DateInterval('P' . $days . 'D'));
    }

    public static function subDays(DateTimeInterface $date, int $days): DateTimeImmutable
    {
        return self::immutable($date)->sub(new DateInterval('P' . $days . 'D'));
    }

    private static function immutable(DateTimeInterface $date): DateTimeImmutable
    {
        return $date instanceof DateTimeImmutable
            ? $date
            : DateTimeImmutable::createFromInterface($date);
    }
}
