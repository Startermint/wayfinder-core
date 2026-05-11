<?php

declare(strict_types=1);

namespace Wayfinder\Queue\Connectors;

use DateInterval;
use DateTimeInterface;
use Wayfinder\Database\Database;
use Wayfinder\Queue\PayloadSerializer;
use Wayfinder\Queue\QueueConnection;
use Wayfinder\Queue\QueuedJob;
use Wayfinder\Queue\Support\InteractsWithTime;

final class DatabaseQueueConnection implements QueueConnection
{
    use InteractsWithTime;

    public function __construct(
        private readonly string $name,
        private readonly Database $database,
        private readonly PayloadSerializer $serializer,
        private readonly string $table = 'jobs',
        private readonly string $defaultQueue = 'default',
        private readonly int $retryAfter = 90,
    ) {
    }

    public function push(object $job, ?string $queue = null): mixed
    {
        return $this->later(0, $job, $queue);
    }

    public function later(DateTimeInterface|DateInterval|int $delay, object $job, ?string $queue = null): mixed
    {
        $payload = $this->serializer->create($job);
        $queue = $this->queueName($queue);
        $now = $this->currentTime();

        $this->database->insert($this->table, [
            'queue' => $queue,
            'payload' => $this->serializer->encode($payload),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => $this->availableAt($delay),
            'created_at' => $now,
        ]);

        return $this->database->lastInsertId();
    }

    public function pop(?string $queue = null): ?QueuedJob
    {
        $queue = $this->queueName($queue);
        $now = $this->currentTime();
        $reservedCutoff = $now - $this->retryAfter;

        $row = $this->database->firstResult(
            sprintf(
                'SELECT * FROM %s WHERE queue = ? AND available_at <= ? AND (reserved_at IS NULL OR reserved_at <= ?) ORDER BY id ASC LIMIT 1',
                $this->database->qualifyIdentifier($this->table),
            ),
            [$queue, $now, $reservedCutoff],
        );

        if ($row === false) {
            return null;
        }

        $id = $row['id'];
        $attempts = ((int) $row['attempts']) + 1;
        $updated = $this->database->statement(
            sprintf(
                'UPDATE %s SET reserved_at = ?, attempts = ? WHERE id = ? AND (reserved_at IS NULL OR reserved_at <= ?)',
                $this->database->qualifyIdentifier($this->table),
            ),
            [$now, $attempts, $id, $reservedCutoff],
        );

        if ($updated < 1) {
            return null;
        }

        return new QueuedJob(
            id: $id,
            connection: $this->name,
            queue: $queue,
            payload: $this->serializer->decode((string) $row['payload']),
            attempts: $attempts,
            raw: $row,
        );
    }

    public function delete(QueuedJob $job): void
    {
        $this->database->statement(
            sprintf('DELETE FROM %s WHERE id = ?', $this->database->qualifyIdentifier($this->table)),
            [$job->id],
        );
    }

    public function release(QueuedJob $job, DateTimeInterface|DateInterval|int $delay = 0): void
    {
        $this->database->statement(
            sprintf(
                'UPDATE %s SET reserved_at = NULL, available_at = ? WHERE id = ?',
                $this->database->qualifyIdentifier($this->table),
            ),
            [$this->availableAt($delay), $job->id],
        );
    }

    private function queueName(?string $queue): string
    {
        return $queue !== null && $queue !== '' ? $queue : $this->defaultQueue;
    }
}

