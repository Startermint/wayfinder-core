<?php

declare(strict_types=1);

namespace Wayfinder\Tests\Queue;

use PHPUnit\Framework\TestCase;
use Wayfinder\Console\QueueWorkCommand;
use Wayfinder\Database\Database;
use Wayfinder\Queue\DatabaseFailedJobRepository;
use Wayfinder\Queue\JobHandler;
use Wayfinder\Queue\NativeQueueBus;
use Wayfinder\Queue\PayloadSerializer;
use Wayfinder\Queue\Queue;
use Wayfinder\Queue\QueueManager;
use Wayfinder\Queue\Worker;
use Wayfinder\Queue\WorkerOptions;

final class NativeQueueTest extends TestCase
{
    protected function setUp(): void
    {
        NativeQueueHandledJob::$handled = 0;
        NativeQueueFailingJob::$handled = 0;
        Queue::setResolver(static fn (?string $connection = null) => throw new \RuntimeException('Queue resolver not configured.'));
    }

    protected function tearDown(): void
    {
        Queue::setResolver(static fn (?string $connection = null) => throw new \RuntimeException('Queue resolver not configured.'));
    }

    public function testSyncQueueDispatchesImmediately(): void
    {
        $manager = new QueueManager([
            'default' => 'sync',
            'connections' => [
                'sync' => ['driver' => 'sync'],
            ],
        ]);

        $bus = new NativeQueueBus($manager);
        $id = $bus->dispatch(new NativeQueueHandledJob());

        self::assertIsString($id);
        self::assertSame(1, NativeQueueHandledJob::$handled);
    }

    public function testQueueHelperUsesConfiguredResolver(): void
    {
        $manager = new QueueManager([
            'default' => 'sync',
            'connections' => [
                'sync' => ['driver' => 'sync'],
            ],
        ]);
        Queue::setResolver(static fn (?string $connection = null) => $manager->bus($connection));

        queue()->dispatch(new NativeQueueHandledJob());

        self::assertSame(1, NativeQueueHandledJob::$handled);
    }

    public function testDatabaseQueueStoresAndProcessesJob(): void
    {
        $database = $this->database();
        $manager = $this->databaseQueueManager($database);
        $bus = new NativeQueueBus($manager, 'database');

        $id = $bus->dispatch(new NativeQueueHandledJob());

        self::assertNotSame('', (string) $id);
        self::assertSame(0, NativeQueueHandledJob::$handled);

        $worker = new Worker($manager);
        $processed = $worker->run(new WorkerOptions(connection: 'database', once: true));

        self::assertSame(1, $processed);
        self::assertSame(1, NativeQueueHandledJob::$handled);
        self::assertSame([], $database->query('SELECT * FROM jobs'));
    }

    public function testDatabaseQueueDoesNotPopDelayedJobEarly(): void
    {
        $database = $this->database();
        $manager = $this->databaseQueueManager($database);
        $bus = new NativeQueueBus($manager, 'database');

        $bus->dispatchLater(60, new NativeQueueHandledJob());

        $job = $manager->connection('database')->reserve();

        self::assertNull($job);
        self::assertSame(0, NativeQueueHandledJob::$handled);
    }

    public function testDatabaseQueueHonorsNamedQueues(): void
    {
        $database = $this->database();
        $manager = $this->databaseQueueManager($database);
        $bus = new NativeQueueBus($manager, 'database');

        $bus->dispatchTo('emails', new NativeQueueHandledJob());

        $worker = new Worker($manager);
        self::assertSame(0, $worker->run(new WorkerOptions(connection: 'database', queue: 'default', once: true)));
        self::assertSame(0, NativeQueueHandledJob::$handled);

        self::assertSame(1, $worker->run(new WorkerOptions(connection: 'database', queue: 'emails', once: true)));
        self::assertSame(1, NativeQueueHandledJob::$handled);
    }

    public function testWorkerStopsAfterMaxJobs(): void
    {
        $database = $this->database();
        $manager = $this->databaseQueueManager($database);
        $bus = new NativeQueueBus($manager, 'database');

        $bus->dispatch(new NativeQueueHandledJob());
        $bus->dispatch(new NativeQueueHandledJob());

        $worker = new Worker($manager);
        $processed = $worker->run(new WorkerOptions(connection: 'database', sleep: 0, maxJobs: 1));

        self::assertSame(1, $processed);
        self::assertSame(1, NativeQueueHandledJob::$handled);
        self::assertCount(1, $database->query('SELECT * FROM jobs'));
    }

    public function testWorkerCountsFailedAttemptsTowardMaxJobs(): void
    {
        $database = $this->database();
        $manager = $this->databaseQueueManager($database);
        $bus = new NativeQueueBus($manager, 'database');
        $bus->dispatch(new NativeQueueFailingJob());

        $worker = new Worker($manager);
        $processed = $worker->run(new WorkerOptions(connection: 'database', tries: 2, sleep: 0, maxJobs: 1));

        self::assertSame(1, $processed);
        self::assertSame(1, NativeQueueFailingJob::$handled);

        $jobs = $database->query('SELECT * FROM jobs');
        self::assertCount(1, $jobs);
        self::assertSame(1, (int) $jobs[0]['attempts']);
    }

    public function testWorkerReleasesFailedJobUntilAttemptsAreExceeded(): void
    {
        $database = $this->database();
        $manager = $this->databaseQueueManager($database);
        $failed = new DatabaseFailedJobRepository($database);
        $bus = new NativeQueueBus($manager, 'database');
        $bus->dispatch(new NativeQueueFailingJob());

        $worker = new Worker($manager, new PayloadSerializer(), new JobHandler(), $failed);

        try {
            $worker->runNextJob(new WorkerOptions(connection: 'database', tries: 2));
            self::fail('Expected the first failed attempt to throw.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Native queue job failed.', $exception->getMessage());
        }

        $jobs = $database->query('SELECT * FROM jobs');
        self::assertCount(1, $jobs);
        self::assertSame(1, (int) $jobs[0]['attempts']);
        self::assertNull($jobs[0]['reserved_at']);
        self::assertSame([], $failed->all());

        $this->expectException(\RuntimeException::class);

        try {
            $worker->runNextJob(new WorkerOptions(connection: 'database', tries: 2));
        } finally {
            self::assertSame(2, NativeQueueFailingJob::$handled);
            self::assertCount(1, $failed->all());
            self::assertSame([], $database->query('SELECT * FROM jobs'));
        }
    }

    public function testWorkerLogsFailedJobWhenAttemptsAreExceeded(): void
    {
        $database = $this->database();
        $manager = $this->databaseQueueManager($database);
        $failed = new DatabaseFailedJobRepository($database);
        $bus = new NativeQueueBus($manager, 'database');
        $bus->dispatch(new NativeQueueFailingJob());

        $worker = new Worker($manager, new PayloadSerializer(), new JobHandler(), $failed);

        $this->expectException(\RuntimeException::class);

        try {
            $worker->run(new WorkerOptions(connection: 'database', tries: 1, once: true));
        } finally {
            self::assertSame(1, NativeQueueFailingJob::$handled);
            self::assertCount(1, $failed->all());
            self::assertSame([], $database->query('SELECT * FROM jobs'));
        }
    }

    public function testQueueWorkCommandProcessesOneJob(): void
    {
        $database = $this->database();
        $manager = $this->databaseQueueManager($database);
        (new NativeQueueBus($manager, 'database'))->dispatch(new NativeQueueHandledJob());

        $command = new QueueWorkCommand(new Worker($manager), 'database');
        $code = $command->handle(['--once', '--connection=database']);

        self::assertSame(0, $code);
        self::assertSame(1, NativeQueueHandledJob::$handled);
    }

    private function database(): Database
    {
        $database = new Database(['driver' => 'sqlite', 'path' => ':memory:']);
        $database->statement(<<<'SQL'
            CREATE TABLE jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                queue TEXT NOT NULL,
                payload TEXT NOT NULL,
                attempts INTEGER NOT NULL DEFAULT 0,
                reserved_at INTEGER NULL,
                available_at INTEGER NOT NULL,
                created_at INTEGER NOT NULL
            )
        SQL);
        $database->statement(<<<'SQL'
            CREATE TABLE failed_jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid TEXT NOT NULL,
                connection TEXT NOT NULL,
                queue TEXT NOT NULL,
                payload TEXT NOT NULL,
                exception TEXT NOT NULL,
                failed_at TEXT NOT NULL
            )
        SQL);

        return $database;
    }

    private function databaseQueueManager(Database $database): QueueManager
    {
        return new QueueManager(
            [
                'default' => 'database',
                'connections' => [
                    'database' => [
                        'driver' => 'database',
                        'queue' => 'default',
                        'table' => 'jobs',
                        'retry_after' => 90,
                    ],
                ],
            ],
            databases: ['default' => $database],
        );
    }
}

final class NativeQueueHandledJob
{
    public static int $handled = 0;

    public function handle(): void
    {
        self::$handled++;
    }
}

final class NativeQueueFailingJob
{
    public static int $handled = 0;

    public function handle(): void
    {
        self::$handled++;

        throw new \RuntimeException('Native queue job failed.');
    }
}
