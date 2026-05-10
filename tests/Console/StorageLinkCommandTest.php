<?php

declare(strict_types=1);

namespace Wayfinder\Tests\Console;

use PHPUnit\Framework\TestCase;
use Wayfinder\Console\StorageLinkCommand;
use Wayfinder\Tests\Concerns\UsesTempDirectory;

final class StorageLinkCommandTest extends TestCase
{
    use UsesTempDirectory;

    protected function setUp(): void
    {
        $this->setUpTempDirectory();
        mkdir($this->tempDir . '/public', 0775, true);
        mkdir($this->tempDir . '/storage', 0775, true);
    }

    protected function tearDown(): void
    {
        $link = $this->tempDir . '/public/storage';
        if (is_link($link)) {
            unlink($link);
        }

        $this->tearDownTempDirectory();
    }

    public function testItCreatesPublicStorageLinkAndTargetDirectory(): void
    {
        $out = fopen('php://temp', 'w+');
        $err = fopen('php://temp', 'w+');

        $code = (new StorageLinkCommand($this->tempDir . '/public', $this->tempDir . '/storage', $out, $err))->handle();

        self::assertSame(0, $code);
        self::assertDirectoryExists($this->tempDir . '/storage/app/public');
        self::assertTrue(is_link($this->tempDir . '/public/storage'));
        self::assertSame($this->tempDir . '/storage/app/public', readlink($this->tempDir . '/public/storage'));
    }

    public function testItIsSuccessfulWhenCorrectLinkAlreadyExists(): void
    {
        mkdir($this->tempDir . '/storage/app/public', 0775, true);
        symlink($this->tempDir . '/storage/app/public', $this->tempDir . '/public/storage');
        $out = fopen('php://temp', 'w+');
        $err = fopen('php://temp', 'w+');

        $code = (new StorageLinkCommand($this->tempDir . '/public', $this->tempDir . '/storage', $out, $err))->handle();

        self::assertSame(0, $code);
    }

    public function testItRefusesToOverwriteExistingRealDirectory(): void
    {
        mkdir($this->tempDir . '/public/storage', 0775, true);
        $out = fopen('php://temp', 'w+');
        $err = fopen('php://temp', 'w+');

        $code = (new StorageLinkCommand($this->tempDir . '/public', $this->tempDir . '/storage', $out, $err))->handle();

        self::assertSame(1, $code);
        self::assertFalse(is_link($this->tempDir . '/public/storage'));
    }
}
