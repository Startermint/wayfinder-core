<?php

declare(strict_types=1);

namespace Wayfinder\Console;

use Wayfinder\Queue\FailedJobRepository;

final class QueueFlushFailedCommand implements Command
{
    public function __construct(
        private readonly FailedJobRepository $failedJobs,
    ) {
    }

    public function name(): string
    {
        return 'queue:flush-failed';
    }

    public function description(): string
    {
        return 'Delete all failed native queue jobs.';
    }

    public function handle(array $arguments = []): int
    {
        $count = $this->failedJobs->flush();
        fwrite(STDOUT, sprintf("Flushed %d failed %s.\n", $count, $count === 1 ? 'job' : 'jobs'));

        return 0;
    }
}

