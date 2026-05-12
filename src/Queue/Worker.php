<?php

declare(strict_types=1);

namespace Wayfinder\Queue;

final class Worker
{
    private bool $shouldQuit = false;

    public function __construct(
        private readonly QueueManager $manager,
        private readonly PayloadSerializer $serializer = new PayloadSerializer(),
        private readonly JobHandler $handler = new JobHandler(),
        private readonly ?FailedJobRepository $failedJobs = null,
    ) {
    }

    public function run(WorkerOptions $options): int
    {
        $this->shouldQuit = false;
        $this->listenForSignals();

        $processed = 0;
        $startedAt = time();

        do {
            try {
                $current = $this->runNextJob($options);
            } catch (\Throwable $exception) {
                if ($options->once) {
                    throw $exception;
                }

                $current = 1;
            }

            $processed += $current;

            if ($options->once || $this->shouldQuit) {
                break;
            }

            if ($options->maxJobs !== null && $processed >= $options->maxJobs) {
                break;
            }

            if ($options->maxSeconds !== null && (time() - $startedAt) >= $options->maxSeconds) {
                break;
            }

            if ($options->memory !== null && $this->memoryExceeded($options->memory)) {
                break;
            }

            if ($current === 0 && $options->sleep > 0) {
                sleep($options->sleep);
            }
        } while (true);

        return $processed;
    }

    public function stop(): void
    {
        $this->shouldQuit = true;
    }

    public function runNextJob(WorkerOptions $options): int
    {
        $connection = $this->manager->connection($options->connection);
        $queuedJob = $connection->reserve($options->queue);

        if ($queuedJob === null) {
            return 0;
        }

        try {
            $job = $this->serializer->restore($queuedJob->payload);
            $this->handleJob($job, $options);
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

    private function listenForSignals(): void
    {
        if (! function_exists('pcntl_signal') || ! function_exists('pcntl_async_signals')) {
            return;
        }

        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, fn () => $this->stop());
        pcntl_signal(SIGINT, fn () => $this->stop());
    }

    private function memoryExceeded(int $megabytes): bool
    {
        return (memory_get_usage(true) / 1024 / 1024) >= $megabytes;
    }

    private function handleJob(object $job, WorkerOptions $options): void
    {
        if ($options->timeout === null || $options->timeout <= 0 || ! function_exists('pcntl_signal') || ! function_exists('pcntl_alarm')) {
            $this->handler->handle($job);

            return;
        }

        pcntl_signal(SIGALRM, static function () use ($options): void {
            throw new \RuntimeException(sprintf('Queued job timed out after %d seconds.', $options->timeout));
        });

        pcntl_alarm($options->timeout);

        try {
            $this->handler->handle($job);
        } finally {
            pcntl_alarm(0);
        }
    }
}
