<?php

declare(strict_types=1);

namespace Wayfinder\Database;

use Psr\Log\LoggerInterface;

final class DatabaseObservability
{
    /**
     * @var array<int, true>
     */
    private array $configured = [];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly int|float|null $slowQueryMilliseconds = null,
    ) {
    }

    public function configure(Database $database): Database
    {
        $id = spl_object_id($database);

        if (isset($this->configured[$id])) {
            return $database;
        }

        $this->configured[$id] = true;

        if ($this->slowQueryMilliseconds === null || $this->slowQueryMilliseconds < 0) {
            return $database;
        }

        $database->whenQueryingForLongerThan($this->slowQueryMilliseconds, function (QueryExecuted $query): void {
            $this->logger->warning('Slow database query detected.', [
                'connection' => $query->connection,
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'milliseconds' => $query->milliseconds,
            ]);
        });

        return $database;
    }
}
