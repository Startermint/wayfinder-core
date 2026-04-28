<?php

declare(strict_types=1);

namespace Wayfinder\Support;

use DateTimeImmutable;
use DateTimeZone;

interface Clock
{
    public function now(?DateTimeZone $timezone = null): DateTimeImmutable;
}
