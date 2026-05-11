<?php

declare(strict_types=1);

namespace Wayfinder\Console;

use Wayfinder\Queue\FailedJobRepository;

final class QueueFailedCommand implements Command
{
    public function __construct(
        private readonly FailedJobRepository $failedJobs,
    ) {
    }

    public function name(): string
    {
        return 'queue:failed';
    }

    public function description(): string
    {
        return 'List failed native queue jobs.';
    }

    public function handle(array $arguments = []): int
    {
        $jobs = $this->failedJobs->all();

        if ($jobs === []) {
            fwrite(STDOUT, "No failed jobs.\n");

            return 0;
        }

        foreach ($jobs as $job) {
            fwrite(STDOUT, sprintf(
                "%s  %s  %s  %s\n",
                $job['id'] ?? '',
                $job['connection'] ?? '',
                $job['queue'] ?? '',
                $job['failed_at'] ?? '',
            ));
        }

        return 0;
    }
}

