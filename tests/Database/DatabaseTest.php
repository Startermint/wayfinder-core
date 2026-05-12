<?php

declare(strict_types=1);

namespace Wayfinder\Tests\Database;

use PDO;
use Psr\Log\AbstractLogger;
use PHPUnit\Framework\TestCase;
use Wayfinder\Database\Database;
use Wayfinder\Database\DatabaseObservability;
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

    public function testDatabaseObservabilityLogsSlowQueriesOncePerConnection(): void
    {
        $database = new Database(['driver' => 'sqlite', 'path' => ':memory:'], 'observed');
        $logger = new RecordingPsrLogger();
        $observability = new DatabaseObservability($logger, 0);

        $observability->configure($database);
        $observability->configure($database);
        $database->query('SELECT 1');

        self::assertCount(1, $logger->records);
        self::assertSame('warning', $logger->records[0]['level']);
        self::assertSame('Slow database query detected.', $logger->records[0]['message']);
        self::assertSame('observed', $logger->records[0]['context']['connection']);
        self::assertSame('SELECT 1', $logger->records[0]['context']['sql']);
    }
}

final class RecordingPsrLogger extends AbstractLogger
{
    /**
     * @var list<array{level: string, message: string, context: array<string, mixed>}>
     */
    public array $records = [];

    /**
     * @param array<string, mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
