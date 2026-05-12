<?php

declare(strict_types=1);

namespace Wayfinder\Console;

use Wayfinder\Queue\Worker;
use Wayfinder\Queue\WorkerOptions;

final class QueueWorkCommand implements Command
{
    public function __construct(
        private readonly Worker $worker,
        private readonly string $defaultConnection = 'sync',
    ) {
    }

    public function name(): string
    {
        return 'queue:work';
    }

    public function description(): string
    {
        return 'Process jobs from a native Wayfinder queue.';
    }

    public function handle(array $arguments = []): int
    {
        $options = $this->options($arguments);

        $workerOptions = new WorkerOptions(
            connection: (string) ($options['connection'] ?? $this->defaultConnection),
            queue: (string) ($options['queue'] ?? 'default'),
            tries: max(1, (int) ($options['tries'] ?? 1)),
            sleep: max(0, (int) ($options['sleep'] ?? 3)),
            delay: max(0, (int) ($options['delay'] ?? 0)),
            once: (bool) ($options['once'] ?? false),
            maxJobs: isset($options['max-jobs']) ? max(1, (int) $options['max-jobs']) : null,
        );

        $processed = $this->worker->run($workerOptions);
        fwrite(STDOUT, sprintf("Processed %d queued %s.\n", $processed, $processed === 1 ? 'job' : 'jobs'));

        return 0;
    }

    /**
     * @param list<string> $arguments
     * @return array<string, mixed>
     */
    private function options(array $arguments): array
    {
        $options = [];

        foreach ($arguments as $argument) {
            if ($argument === '--once') {
                $options['once'] = true;
                continue;
            }

            if (str_starts_with($argument, '--') && str_contains($argument, '=')) {
                [$key, $value] = explode('=', substr($argument, 2), 2);
                $options[$key] = $value;
            }
        }

        return $options;
    }
}
