<?php

declare(strict_types=1);

namespace Wayfinder\Support;

use DateTimeImmutable;
use DateTimeZone;

final class SystemClock implements Clock
{
    public function now(?DateTimeZone $timezone = null): DateTimeImmutable
    {
        if ($timezone !== null) {
            return new DateTimeImmutable('now', $timezone);
        }

        return new DateTimeImmutable();
    }
}
