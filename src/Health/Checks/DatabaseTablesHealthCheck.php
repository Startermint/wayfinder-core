<?php

declare(strict_types=1);

namespace Wayfinder\Health\Checks;

use Wayfinder\Database\Database;
use Wayfinder\Database\Schema;
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

        Schema::setResolver(fn (): Database => $this->database);

        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
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
