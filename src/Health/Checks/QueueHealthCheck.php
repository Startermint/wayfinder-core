<?php

declare(strict_types=1);

namespace Wayfinder\Health\Checks;

use Wayfinder\Health\HealthCheck;
use Wayfinder\Health\HealthResult;
use Wayfinder\Queue\FailedJobRepository;
use Wayfinder\Queue\QueueManager;

final readonly class QueueHealthCheck implements HealthCheck
{
    public function __construct(
        private QueueManager $queues,
        private ?FailedJobRepository $failedJobs = null,
        private ?string $connection = null,
        private string $name = 'queue',
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function check(): HealthResult
    {
        $connection = $this->queues->connection($this->connection);
        $failedCount = null;

        if ($this->failedJobs !== null) {
            $failedCount = count($this->failedJobs->all());
        }

        $context = [
            'connection' => $connection::class,
        ];

        if ($failedCount !== null) {
            $context['failed_jobs'] = $failedCount;
        }

        if ($failedCount !== null && $failedCount > 0) {
            return HealthResult::warn($this->name, 'Queue is reachable but failed jobs are present.', $context);
        }

        return HealthResult::ok($this->name, 'Queue connection resolved successfully.', $context);
    }
}
