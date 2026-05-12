<?php

declare(strict_types=1);

namespace Wayfinder\Health\Checks;

use Wayfinder\Database\Database;
use Wayfinder\Health\HealthCheck;
use Wayfinder\Health\HealthResult;

final readonly class DatabaseHealthCheck implements HealthCheck
{
    public function __construct(
        private Database $database,
        private string $name = 'database',
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function check(): HealthResult
    {
        $rows = $this->database->query('SELECT 1 AS ok');

        if ((int) ($rows[0]['ok'] ?? 0) !== 1) {
            return HealthResult::fail($this->name, 'Connection returned an unexpected result.');
        }

        return HealthResult::ok($this->name, 'Connection succeeded.', [
            'driver' => $this->database->driver(),
        ]);
    }
}
