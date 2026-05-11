<?php

declare(strict_types=1);

namespace Wayfinder\Queue;

final class Worker
{
    public function __construct(
        private readonly QueueManager $manager,
        private readonly PayloadSerializer $serializer = new PayloadSerializer(),
        private readonly JobHandler $handler = new JobHandler(),
        private readonly ?FailedJobRepository $failedJobs = null,
    ) {
    }

    public function run(WorkerOptions $options): int
    {
        $processed = 0;

        do {
            $current = $this->runNextJob($options);
            $processed += $current;

            if ($options->once) {
                break;
            }

            if ($current === 0 && $options->sleep > 0) {
                sleep($options->sleep);
            }
        } while (true);

        return $processed;
    }

    public function runNextJob(WorkerOptions $options): int
    {
        $connection = $this->manager->connection($options->connection);
        $queuedJob = $connection->pop($options->queue);

        if ($queuedJob === null) {
            return 0;
        }

        try {
            $job = $this->serializer->restore($queuedJob->payload);
            $this->handler->handle($job);
            $connection->delete($queuedJob);

            return 1;
        } catch (\Throwable $exception) {
            if ($queuedJob->attempts >= $options->tries) {
                $connection->delete($queuedJob);
                $this->failedJobs?->log($queuedJob, $exception);

                throw $exception;
            }

            $connection->release($queuedJob, $options->delay);

            throw $exception;
        }
    }
}
