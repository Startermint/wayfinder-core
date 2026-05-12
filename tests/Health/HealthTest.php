<?php

declare(strict_types=1);

namespace Wayfinder\Tests\Health;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Wayfinder\Cache\FileCache;
use Wayfinder\Console\HealthCommand;
use Wayfinder\Database\Database;
use Wayfinder\Health\Checks\AppConfigHealthCheck;
use Wayfinder\Health\Checks\CacheHealthCheck;
use Wayfinder\Health\Checks\DatabaseHealthCheck;
use Wayfinder\Health\Checks\StorageHealthCheck;
use Wayfinder\Health\HealthCheck;
use Wayfinder\Health\HealthReport;
use Wayfinder\Health\HealthResult;
use Wayfinder\Health\HealthRunner;
use Wayfinder\Support\Config;
use Wayfinder\Tests\Concerns\UsesTempDirectory;

final class HealthTest extends TestCase
{
    use UsesTempDirectory;

    protected function setUp(): void
    {
        $this->setUpTempDirectory();
    }

    protected function tearDown(): void
    {
        $this->tearDownTempDirectory();
    }

    public function testReportStatusReflectsWorstResult(): void
    {
        self::assertSame(HealthResult::OK, (new HealthReport([
            HealthResult::ok('one', 'ok'),
        ]))->status());

        self::assertSame(HealthResult::WARN, (new HealthReport([
            HealthResult::ok('one', 'ok'),
            HealthResult::warn('two', 'warn'),
        ]))->status());

        self::assertSame(HealthResult::FAIL, (new HealthReport([
            HealthResult::warn('one', 'warn'),
            HealthResult::fail('two', 'fail'),
        ]))->status());
    }

    public function testRunnerTurnsThrownExceptionsIntoFailedResults(): void
    {
        $report = (new HealthRunner([new ThrowingHealthCheck()]))->run();

        self::assertTrue($report->hasFailures());
        self::assertSame('throwing', $report->results[0]->name);
        self::assertSame(HealthResult::FAIL, $report->results[0]->status);
    }

    public function testBuiltInDatabaseCacheAndStorageChecksPass(): void
    {
        $database = new Database(['driver' => 'sqlite', 'path' => ':memory:']);
        $cache = new FileCache($this->tempDir . '/cache');
        $disk = new Filesystem(new LocalFilesystemAdapter($this->tempDir . '/storage'));

        $report = (new HealthRunner([
            new DatabaseHealthCheck($database),
            new CacheHealthCheck($cache),
            new StorageHealthCheck($disk),
            new AppConfigHealthCheck(new Config([
                'app' => [
                    'environment' => 'production',
                    'debug' => false,
                    'key' => 'base64:test',
                    'url' => 'https://example.com',
                    'trusted_hosts' => ['example.com'],
                ],
            ])),
        ]))->run();

        self::assertSame(HealthResult::OK, $report->status());
    }

    public function testAppConfigCheckWarnsForUnsafeProductionConfig(): void
    {
        $result = (new AppConfigHealthCheck(new Config([
            'app' => [
                'environment' => 'production',
                'debug' => true,
                'key' => '',
                'url' => '',
                'trusted_hosts' => [],
            ],
        ])))->check();

        self::assertSame(HealthResult::WARN, $result->status);
        self::assertStringContainsString('APP_KEY', $result->message);
        self::assertStringContainsString('APP_DEBUG', $result->message);
    }

    public function testHealthCommandWritesJsonAndReturnsFailureForFailedChecks(): void
    {
        $output = fopen('php://memory', 'r+');
        self::assertIsResource($output);

        $command = new HealthCommand(new HealthRunner([
            new StaticHealthCheck(HealthResult::fail('database', 'Connection failed.')),
        ]), output: $output);

        $code = $command->handle(['--json']);

        rewind($output);
        $json = stream_get_contents($output);
        fclose($output);

        self::assertSame(1, $code);
        self::assertIsString($json);
        $payload = json_decode($json, true);
        self::assertSame('fail', $payload['status']);
        self::assertSame('database', $payload['checks'][0]['name']);
    }
}

final class ThrowingHealthCheck implements HealthCheck
{
    public function name(): string
    {
        return 'throwing';
    }

    public function check(): HealthResult
    {
        throw new \RuntimeException('Exploded.');
    }
}

final readonly class StaticHealthCheck implements HealthCheck
{
    public function __construct(
        private HealthResult $result,
    ) {
    }

    public function name(): string
    {
        return $this->result->name;
    }

    public function check(): HealthResult
    {
        return $this->result;
    }
}
