<?php

declare(strict_types=1);

namespace Wayfinder\Queue;

use Wayfinder\Database\Database;

final class DatabaseFailedJobRepository implements FailedJobRepository
{
    public function __construct(
        private readonly Database $database,
        private readonly string $table = 'failed_jobs',
    ) {
    }

    public function log(QueuedJob $job, \Throwable $exception): mixed
    {
        $this->database->insert($this->table, [
            'uuid' => $job->uuid(),
            'connection' => $job->connection,
            'queue' => $job->queue,
            'payload' => json_encode($job->payload) ?: '{}',
            'exception' => $exception::class . ': ' . $exception->getMessage() . "\n" . $exception->getTraceAsString(),
            'failed_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->database->lastInsertId();
    }

    public function all(): array
    {
        return $this->database->query(
            sprintf('SELECT * FROM %s ORDER BY id DESC', $this->database->qualifyIdentifier($this->table)),
        );
    }

    public function find(mixed $id): ?array
    {
        $row = $this->database->firstResult(
            sprintf('SELECT * FROM %s WHERE id = ? OR uuid = ? LIMIT 1', $this->database->qualifyIdentifier($this->table)),
            [$id, $id],
        );

        return $row === false ? null : $row;
    }

    public function forget(mixed $id): void
    {
        $this->database->statement(
            sprintf('DELETE FROM %s WHERE id = ? OR uuid = ?', $this->database->qualifyIdentifier($this->table)),
            [$id, $id],
        );
    }

    public function flush(): int
    {
        return $this->database->statement(sprintf('DELETE FROM %s', $this->database->qualifyIdentifier($this->table)));
    }
}

