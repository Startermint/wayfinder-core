<?php

declare(strict_types=1);

namespace Wayfinder\Contracts\Queue;

use DateInterval;
use DateTimeInterface;

interface QueueBus
{
    public function dispatch(object $job): mixed;

    public function dispatchTo(string $queue, object $job): mixed;

    public function dispatchLater(DateTimeInterface|DateInterval|int $delay, object $job): mixed;

    public function dispatchLaterTo(string $queue, DateTimeInterface|DateInterval|int $delay, object $job): mixed;
}
