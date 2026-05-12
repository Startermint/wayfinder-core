<?php

declare(strict_types=1);

namespace Wayfinder\Tests\Logging;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Wayfinder\Logging\LogManager;
use Wayfinder\Logging\Logger;
use Wayfinder\Tests\Concerns\UsesTempDirectory;

final class LogManagerTest extends TestCase
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

    public function testSingleChannelWritesToFile(): void
    {
        $path = $this->tempDir . '/logs/app.log';
        $manager = new LogManager([
            'default' => 'single',
            'channels' => [
                'single' => ['driver' => 'single', 'path' => $path, 'level' => 'debug'],
            ],
        ]);

        $manager->logger()->info('Order stored', ['order_id' => 42]);

        self::assertFileExists($path);
        $contents = (string) file_get_contents($path);
        self::assertStringContainsString('INFO: Order stored', $contents);
        self::assertStringContainsString('"order_id":42', $contents);
    }

    public function testFileDriverAliasStillWorks(): void
    {
        $path = $this->tempDir . '/logs/app.log';
        $manager = new LogManager([
            'default' => 'file',
            'channels' => [
                'file' => ['driver' => 'file', 'path' => $path, 'level' => 'warning'],
            ],
        ]);

        $manager->logger()->info('Dropped');
        $manager->logger()->error('Kept');

        $contents = (string) file_get_contents($path);
        self::assertStringNotContainsString('Dropped', $contents);
        self::assertStringContainsString('ERROR: Kept', $contents);
    }

    public function testDailyChannelWritesRotatedFile(): void
    {
        $path = $this->tempDir . '/logs/app.log';
        $manager = new LogManager([
            'default' => 'daily',
            'channels' => [
                'daily' => ['driver' => 'daily', 'path' => $path, 'level' => 'debug', 'days' => 7],
            ],
        ]);

        $manager->logger()->warning('Daily warning');

        $files = glob($this->tempDir . '/logs/app-*.log') ?: [];
        self::assertCount(1, $files);
        self::assertStringContainsString('WARNING: Daily warning', (string) file_get_contents($files[0]));
    }

    public function testNullChannelReturnsPsrNullLogger(): void
    {
        $manager = new LogManager([
            'default' => 'null',
            'channels' => [
                'null' => ['driver' => 'null'],
            ],
        ]);

        self::assertInstanceOf(NullLogger::class, $manager->logger());
    }

    public function testWayfinderLoggerAdapterImplementsFrameworkContract(): void
    {
        $path = $this->tempDir . '/logs/app.log';
        $manager = new LogManager([
            'default' => 'single',
            'channels' => [
                'single' => ['driver' => 'single', 'path' => $path],
            ],
        ]);

        $logger = $manager->wayfinderLogger();
        $logger->error('Framework error');

        self::assertInstanceOf(Logger::class, $logger);
        self::assertStringContainsString('ERROR: Framework error', (string) file_get_contents($path));
    }

    public function testPsrLoggerBindingTypeIsSupported(): void
    {
        $manager = new LogManager([
            'default' => 'null',
            'channels' => [
                'null' => ['driver' => 'null'],
            ],
        ]);

        self::assertInstanceOf(LoggerInterface::class, $manager->logger());
    }

    public function testMissingChannelFailsClearly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Logging channel [missing] is not configured.');

        (new LogManager(['default' => 'missing', 'channels' => []]))->logger();
    }

    public function testUnsupportedDriverFailsClearly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Logging driver [syslog] is not supported.');

        (new LogManager([
            'default' => 'bad',
            'channels' => [
                'bad' => ['driver' => 'syslog'],
            ],
        ]))->logger();
    }
}
