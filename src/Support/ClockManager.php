<?php

declare(strict_types=1);

namespace Wayfinder\Support;

use Carbon\CarbonImmutable;
use DateInterval;
use DateTimeInterface;
use DateTimeZone;

final class ClockManager implements Clock
{
    private ?CarbonImmutable $frozenAt = null;

    public function now(?DateTimeZone $timezone = null): CarbonImmutable
    {
        $now = $this->frozenAt ?? CarbonImmutable::now();

        if ($timezone !== null) {
            return $now->setTimezone($timezone);
        }

        return $now;
    }

    public function freeze(string|DateTimeInterface|null $at = null, DateTimeZone|string|null $timezone = null): self
    {
        $this->frozenAt = $this->resolve($at, $timezone);

        return $this;
    }

    public function travel(string|DateInterval|int $modify): self
    {
        $current = $this->frozenAt ?? CarbonImmutable::now();

        if ($modify instanceof DateInterval) {
            $this->frozenAt = $current->add($modify);

            return $this;
        }

        if (is_int($modify)) {
            $this->frozenAt = $current->addSeconds($modify);

            return $this;
        }

        $travelled = $current->modify($modify);

        if (! $travelled instanceof CarbonImmutable) {
            throw new \InvalidArgumentException(sprintf('Invalid clock travel modifier [%s].', $modify));
        }

        $this->frozenAt = $travelled;

        return $this;
    }

    public function reset(): self
    {
        $this->frozenAt = null;

        return $this;
    }

    public function isFrozen(): bool
    {
        return $this->frozenAt !== null;
    }

    public function scoped(string|DateTimeInterface|null $at, callable $callback): mixed
    {
        $previous = $this->frozenAt;
        $this->freeze($at);

        try {
            return $callback($this);
        } finally {
            $this->frozenAt = $previous;
        }
    }

    private function resolve(string|DateTimeInterface|null $at, DateTimeZone|string|null $timezone): CarbonImmutable
    {
        if ($at instanceof DateTimeInterface) {
            return Date::parse($at, $timezone);
        }

        if (is_string($at)) {
            return CarbonImmutable::parse($at, $timezone);
        }

        return CarbonImmutable::now($timezone);
    }
}
