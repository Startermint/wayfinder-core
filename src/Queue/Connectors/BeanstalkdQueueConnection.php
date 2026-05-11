<?php

declare(strict_types=1);

namespace Wayfinder\Queue\Connectors;

use DateInterval;
use DateTimeInterface;
use Pheanstalk\Pheanstalk;
use Pheanstalk\Values\TubeName;
use Wayfinder\Queue\PayloadSerializer;
use Wayfinder\Queue\QueueConnection;
use Wayfinder\Queue\QueuedJob;
use Wayfinder\Queue\Support\InteractsWithTime;

final class BeanstalkdQueueConnection implements QueueConnection
{
    use InteractsWithTime;

    public function __construct(
        private readonly string $name,
        private readonly Pheanstalk $pheanstalk,
        private readonly PayloadSerializer $serializer,
        private readonly string $defaultQueue = 'default',
        private readonly int $retryAfter = 90,
    ) {
    }

    public function push(object $job, ?string $queue = null): mixed
    {
        return $this->later(0, $job, $queue);
    }

    public function later(DateTimeInterface|DateInterval|int $delay, object $job, ?string $queue = null): mixed
    {
        $payload = $this->serializer->create($job);
        $this->pheanstalk->useTube(new TubeName($this->queueName($queue)));
        $id = $this->pheanstalk->put(
            $this->serializer->encode($payload),
            delay: $this->secondsUntil($delay),
            timeToRelease: $this->retryAfter,
        );

        return $id->getId();
    }

    public function pop(?string $queue = null): ?QueuedJob
    {
        $queue = $this->queueName($queue);
        $this->pheanstalk->watch(new TubeName($queue));
        $job = $this->pheanstalk->reserveWithTimeout(0);

        if ($job === null) {
            return null;
        }

        $stats = $this->pheanstalk->statsJob($job);
        $payload = $this->serializer->decode($job->getData());

        return new QueuedJob(
            id: $job->getId(),
            connection: $this->name,
            queue: $queue,
            payload: $payload,
            attempts: max(1, $stats->reserves),
            raw: $job,
        );
    }

    public function delete(QueuedJob $job): void
    {
        if ($job->raw !== null) {
            $this->pheanstalk->delete($job->raw);
        }
    }

    public function release(QueuedJob $job, DateTimeInterface|DateInterval|int $delay = 0): void
    {
        if ($job->raw !== null) {
            $this->pheanstalk->release($job->raw, delay: $this->secondsUntil($delay));
        }
    }

    private function queueName(?string $queue): string
    {
        return $queue !== null && $queue !== '' ? $queue : $this->defaultQueue;
    }
}

