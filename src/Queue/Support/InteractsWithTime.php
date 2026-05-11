<?php

declare(strict_types=1);

namespace Wayfinder\Queue\Support;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

trait InteractsWithTime
{
    protected function currentTime(): int
    {
        return time();
    }

    protected function availableAt(DateTimeInterface|DateInterval|int $delay): int
    {
        if ($delay instanceof DateTimeInterface) {
            return $delay->getTimestamp();
        }

        if ($delay instanceof DateInterval) {
            return (new DateTimeImmutable())->add($delay)->getTimestamp();
        }

        return $this->currentTime() + max(0, $delay);
    }

    protected function secondsUntil(DateTimeInterface|DateInterval|int $delay): int
    {
        return max(0, $this->availableAt($delay) - $this->currentTime());
    }
}

