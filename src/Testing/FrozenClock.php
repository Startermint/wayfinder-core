<?php

declare(strict_types=1);

namespace Wayfinder\Testing;

use DateTimeImmutable;
use DateTimeZone;
use Wayfinder\Support\Clock;

final class FrozenClock implements Clock
{
    public function __construct(
        private readonly DateTimeImmutable $now,
    ) {
    }

    public function now(?DateTimeZone $timezone = null): DateTimeImmutable
    {
        if ($timezone !== null) {
            return $this->now->setTimezone($timezone);
        }

        return $this->now;
    }
}
