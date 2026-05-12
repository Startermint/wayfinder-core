<?php

declare(strict_types=1);

namespace Wayfinder\Tests\Database;

use PDO;
use PHPUnit\Framework\TestCase;
use Wayfinder\Database\Database;
use Wayfinder\Database\QueryExecuted;

final class DatabaseTest extends TestCase
{
    public function testDispatchesQueryEventsWithConnectionNameAndBindings(): void
    {
        $database = new Database(['driver' => 'sqlite', 'path' => ':memory:'], 'testing');
        $events = [];

        $database->listen(static function (QueryExecuted $query) use (&$events): void {
            $events[] = $query;
        });

        $database->query('SELECT ? AS value', ['example']);

        self::assertCount(1, $events);
        self::assertSame('SELECT ? AS value', $events[0]->sql);
        self::assertSame(['example'], $events[0]->bindings);
        self::assertSame('testing', $events[0]->connection);
        self::assertGreaterThanOrEqual(0, $events[0]->milliseconds);
    }

    public function testDispatchesSlowQueryEventsOnlyAfterThreshold(): void
    {
        $database = new Database(['driver' => 'sqlite', 'path' => ':memory:']);
        $events = [];

        $database->whenQueryingForLongerThan(0, static function (QueryExecuted $query) use (&$events): void {
            $events[] = $query;
        });

        $database->query('SELECT 1');

        self::assertCount(1, $events);
    }

    public function testMergesSafePdoOptionsFromConfig(): void
    {
        $database = new Database([
            'driver' => 'sqlite',
            'path' => ':memory:',
            'timeout' => 5,
            'options' => [
                PDO::ATTR_CASE => PDO::CASE_LOWER,
                'ignored' => 'value',
            ],
        ]);

        self::assertSame(PDO::CASE_LOWER, $database->getConnection()->getAttribute(PDO::ATTR_CASE));
    }
}
