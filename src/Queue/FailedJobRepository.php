<?php

declare(strict_types=1);

namespace Wayfinder\Queue;

interface FailedJobRepository
{
    public function log(QueuedJob $job, \Throwable $exception): mixed;

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array;

    public function find(mixed $id): ?array;

    public function forget(mixed $id): void;

    public function flush(): int;
}

