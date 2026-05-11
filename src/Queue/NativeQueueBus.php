<?php

declare(strict_types=1);

namespace Wayfinder\Queue;

use DateInterval;
use DateTimeInterface;
use Wayfinder\Contracts\Queue\QueueBus;
use Wayfinder\Queue\Exception\QueueException;

final class NativeQueueBus implements QueueBus
{
    public function __construct(
        private readonly QueueManager $manager,
        private readonly ?string $connection = null,
    ) {
    }

    public function dispatch(object $job): mixed
    {
        try {
            return $this->manager->connection($this->connection)->push($job);
        } catch (\Throwable $exception) {
            throw new QueueException('Unable to dispatch queued job.', 0, $exception);
        }
    }

    public function dispatchTo(string $queue, object $job): mixed
    {
        try {
            return $this->manager->connection($this->connection)->push($job, $queue);
        } catch (\Throwable $exception) {
            throw new QueueException(sprintf('Unable to dispatch queued job to [%s].', $queue), 0, $exception);
        }
    }

    public function dispatchLater(DateTimeInterface|DateInterval|int $delay, object $job): mixed
    {
        try {
            return $this->manager->connection($this->connection)->later($delay, $job);
        } catch (\Throwable $exception) {
            throw new QueueException('Unable to dispatch delayed queued job.', 0, $exception);
        }
    }

    public function dispatchLaterTo(string $queue, DateTimeInterface|DateInterval|int $delay, object $job): mixed
    {
        try {
            return $this->manager->connection($this->connection)->later($delay, $job, $queue);
        } catch (\Throwable $exception) {
            throw new QueueException(sprintf('Unable to dispatch delayed queued job to [%s].', $queue), 0, $exception);
        }
    }
}

