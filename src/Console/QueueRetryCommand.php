<?php

declare(strict_types=1);

namespace Wayfinder\Console;

use Wayfinder\Queue\FailedJobRepository;
use Wayfinder\Queue\PayloadSerializer;
use Wayfinder\Queue\QueueManager;

final class QueueRetryCommand implements Command
{
    public function __construct(
        private readonly FailedJobRepository $failedJobs,
        private readonly QueueManager $manager,
        private readonly PayloadSerializer $serializer = new PayloadSerializer(),
    ) {
    }

    public function name(): string
    {
        return 'queue:retry';
    }

    public function description(): string
    {
        return 'Retry a failed native queue job by id or uuid.';
    }

    public function handle(array $arguments = []): int
    {
        $id = $arguments[0] ?? null;

        if ($id === null || $id === '') {
            fwrite(STDERR, "Usage: queue:retry <id|uuid>\n");

            return 2;
        }

        $failed = $this->failedJobs->find($id);

        if ($failed === null) {
            fwrite(STDERR, sprintf("Failed job [%s] was not found.\n", $id));

            return 1;
        }

        $payload = json_decode((string) ($failed['payload'] ?? ''), true);

        if (! is_array($payload)) {
            fwrite(STDERR, sprintf("Failed job [%s] has an invalid payload.\n", $id));

            return 1;
        }

        $job = $this->serializer->restore($payload);
        $connection = (string) ($failed['connection'] ?? $this->manager->defaultConnectionName());
        $queue = (string) ($failed['queue'] ?? 'default');
        $this->manager->connection($connection)->push($job, $queue);
        $this->failedJobs->forget($id);

        fwrite(STDOUT, sprintf("Retried failed job [%s].\n", $id));

        return 0;
    }
}

