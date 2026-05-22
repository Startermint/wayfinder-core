<?php

declare(strict_types=1);

namespace Wayfinder\Transport;

use DateInterval;
use DateTimeInterface;
use Wayfinder\Contracts\Container;
use Wayfinder\Contracts\Queue\QueueBus;
use Wayfinder\Queue\JobHandler;
use Wayfinder\Scenario\EventRecorder;

final class FakeQueueDispatcher implements QueueBus
{
    /**
     * @var list<array{job: object, queue: string|null, delay: DateTimeInterface|DateInterval|int|null}>
     */
    private array $dispatched = [];

    public function __construct(
        private readonly ?EventRecorder $events = null,
        private readonly bool $executeImmediately = false,
        private readonly ?Container $container = null,
    ) {
    }

    public function dispatch(object $job): mixed
    {
        return $this->record($job, null, null);
    }

    public function dispatchTo(string $queue, object $job): mixed
    {
        return $this->record($job, $queue, null);
    }

    public function dispatchLater(DateTimeInterface|DateInterval|int $delay, object $job): mixed
    {
        return $this->record($job, null, $delay);
    }

    public function dispatchLaterTo(string $queue, DateTimeInterface|DateInterval|int $delay, object $job): mixed
    {
        return $this->record($job, $queue, $delay);
    }

    /**
     * @return list<array{job: object, queue: string|null, delay: DateTimeInterface|DateInterval|int|null}>
     */
    public function dispatched(): array
    {
        return $this->dispatched;
    }

    private function record(object $job, ?string $queue, DateTimeInterface|DateInterval|int|null $delay): mixed
    {
        $this->dispatched[] = [
            'job' => $job,
            'queue' => $queue,
            'delay' => $delay,
        ];

        $this->events?->record('job.dispatched', [
            'job' => $job::class,
            'queue' => $queue,
            'delayed' => $delay !== null,
        ]);

        if ($this->executeImmediately) {
            return (new JobHandler($this->container))->handle($job);
        }

        return $job;
    }
}
