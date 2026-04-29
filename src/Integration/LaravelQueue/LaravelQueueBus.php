<?php

declare(strict_types=1);

namespace Wayfinder\Integration\LaravelQueue;

use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Queue\Factory as LaravelQueueFactoryContract;
use Throwable;
use Wayfinder\Contracts\Queue\QueueBus;
use Wayfinder\Queue\Exception\QueueException;

final class LaravelQueueBus implements QueueBus
{
    public function __construct(
        private readonly LaravelQueueFactoryContract $factory,
        private readonly ?string $connection = null,
    ) {
    }

    public function dispatch(object $job): mixed
    {
        try {
            return $this->factory->connection($this->connection)->push($job);
        } catch (Throwable $exception) {
            throw new QueueException('Unable to dispatch queued job.', 0, $exception);
        }
    }

    public function dispatchTo(string $queue, object $job): mixed
    {
        try {
            return $this->factory->connection($this->connection)->pushOn($queue, $job);
        } catch (Throwable $exception) {
            throw new QueueException(sprintf('Unable to dispatch queued job to [%s].', $queue), 0, $exception);
        }
    }

    public function dispatchLater(DateTimeInterface|DateInterval|int $delay, object $job): mixed
    {
        try {
            return $this->factory->connection($this->connection)->later($delay, $job);
        } catch (Throwable $exception) {
            throw new QueueException('Unable to dispatch delayed queued job.', 0, $exception);
        }
    }

    public function dispatchLaterTo(string $queue, DateTimeInterface|DateInterval|int $delay, object $job): mixed
    {
        try {
            return $this->factory->connection($this->connection)->laterOn($queue, $delay, $job);
        } catch (Throwable $exception) {
            throw new QueueException(sprintf('Unable to dispatch delayed queued job to [%s].', $queue), 0, $exception);
        }
    }
}
