<?php

declare(strict_types=1);

namespace Wayfinder\Queue;

use DateInterval;
use DateTimeInterface;

interface QueueConnection
{
    public function push(object $job, ?string $queue = null): mixed;

    public function later(DateTimeInterface|DateInterval|int $delay, object $job, ?string $queue = null): mixed;

    public function pop(?string $queue = null): ?QueuedJob;

    public function delete(QueuedJob $job): void;

    public function release(QueuedJob $job, DateTimeInterface|DateInterval|int $delay = 0): void;
}

