<?php

declare(strict_types=1);

namespace Wayfinder\Health\Checks;

use Wayfinder\Database\Database;
use Wayfinder\Database\SchemaGrammar;
use Wayfinder\Health\HealthCheck;
use Wayfinder\Health\HealthResult;

final readonly class DatabaseTablesHealthCheck implements HealthCheck
{
    /**
     * @param list<string> $tables
     */
    public function __construct(
        private Database $database,
        private array $tables,
        private string $name = 'database.tables',
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function check(): HealthResult
    {
        $missing = [];

        foreach ($this->tables as $table) {
            [$sql, $bindings] = (new SchemaGrammar($this->database->driver()))->compileHasTable($table);
            $result = $this->database->query($sql, $bindings);

            if ((int) ($result[0]['count'] ?? 0) < 1) {
                $missing[] = $table;
            }
        }

        if ($missing !== []) {
            return HealthResult::fail($this->name, 'Required database tables are missing.', [
                'missing' => $missing,
            ]);
        }

        return HealthResult::ok($this->name, 'Required database tables are present.', [
            'tables' => $this->tables,
        ]);
    }
}
