<?php

declare(strict_types=1);

namespace Wayfinder\Queue\Contracts;

use DateInterval;
use DateTimeInterface;
use Wayfinder\Queue\QueuedJob;

interface QueueDriver
{
    public function push(object $job, ?string $queue = null): mixed;

    public function later(DateTimeInterface|DateInterval|int $delay, object $job, ?string $queue = null): mixed;

    public function reserve(?string $queue = null): ?QueuedJob;

    public function delete(QueuedJob $job): void;

    public function release(QueuedJob $job, DateTimeInterface|DateInterval|int $delay = 0): void;
}

