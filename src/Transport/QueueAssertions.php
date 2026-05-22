<?php

declare(strict_types=1);

namespace Wayfinder\Transport;

use Wayfinder\Contracts\Queue\QueueBus;

final class QueueAssertions
{
    public function __construct(
        private readonly QueueBus $queue,
    ) {
    }

    public function dispatchedCount(int $expected): void
    {
        $actual = count($this->fake()->dispatched());

        if ($actual !== $expected) {
            throw new \RuntimeException(sprintf('Expected %d queued job(s), saw %d.', $expected, $actual));
        }
    }

    public function dispatched(string $jobClass): void
    {
        foreach ($this->fake()->dispatched() as $record) {
            if ($record['job'] instanceof $jobClass) {
                return;
            }
        }

        throw new \RuntimeException(sprintf('Expected queued job [%s] to be dispatched.', $jobClass));
    }

    private function fake(): FakeQueueDispatcher
    {
        if (! $this->queue instanceof FakeQueueDispatcher) {
            throw new \RuntimeException('Queue assertions require FakeQueueDispatcher.');
        }

        return $this->queue;
    }
}
