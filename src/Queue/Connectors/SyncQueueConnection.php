<?php

declare(strict_types=1);

namespace Wayfinder\Queue\Connectors;

use DateInterval;
use DateTimeInterface;
use Wayfinder\Queue\JobHandler;
use Wayfinder\Queue\PayloadSerializer;
use Wayfinder\Queue\QueueConnection;
use Wayfinder\Queue\QueuedJob;
use Wayfinder\Queue\Support\InteractsWithTime;

final class SyncQueueConnection implements QueueConnection
{
    use InteractsWithTime;

    public function __construct(
        private readonly string $name,
        private readonly PayloadSerializer $serializer,
        private readonly JobHandler $handler,
        private readonly string $defaultQueue = 'default',
    ) {
    }

    public function push(object $job, ?string $queue = null): mixed
    {
        $payload = $this->serializer->create($job);
        $this->handler->handle($job);

        return $payload['uuid'];
    }

    public function later(DateTimeInterface|DateInterval|int $delay, object $job, ?string $queue = null): mixed
    {
        return $this->push($job, $queue);
    }

    public function pop(?string $queue = null): ?QueuedJob
    {
        return null;
    }

    public function delete(QueuedJob $job): void
    {
    }

    public function release(QueuedJob $job, DateTimeInterface|DateInterval|int $delay = 0): void
    {
    }
}

