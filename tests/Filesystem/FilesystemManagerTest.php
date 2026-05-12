<?php

declare(strict_types=1);

namespace Wayfinder\Tests\Filesystem;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Wayfinder\Filesystem\FilesystemManager;
use Wayfinder\Filesystem\Storage;
use Wayfinder\Tests\Concerns\UsesTempDirectory;

final class FilesystemManagerTest extends TestCase
{
    use UsesTempDirectory;

    protected function setUp(): void
    {
        $this->setUpTempDirectory();
    }

    protected function tearDown(): void
    {
        Storage::clearResolver();
        $this->tearDownTempDirectory();
    }

    public function testLocalDiskCanWriteReadCheckAndDeleteFiles(): void
    {
        $manager = $this->manager();
        $disk = $manager->disk('local');

        $disk->write('notes/example.txt', 'hello');

        self::assertTrue($disk->fileExists('notes/example.txt'));
        self::assertSame('hello', $disk->read('notes/example.txt'));

        $disk->delete('notes/example.txt');

        self::assertFalse($disk->fileExists('notes/example.txt'));
    }

    public function testDefaultDiskUsesConfiguredDefault(): void
    {
        $manager = $this->manager(default: 'public');

        $manager->disk()->write('avatar.txt', 'public');

        self::assertFileExists($this->tempDir . '/storage/app/public/avatar.txt');
        self::assertFileDoesNotExist($this->tempDir . '/storage/app/avatar.txt');
    }

    public function testStorageResolverDelegatesToManager(): void
    {
        $manager = $this->manager();
        Storage::setResolver(static fn (?string $disk = null): FilesystemOperator => $manager->disk($disk));
        Storage::setUrlResolver(static fn (string $path, ?string $disk = null): string => $manager->url($path, $disk));

        Storage::write('default.txt', 'default');
        Storage::disk('public')->write('public.txt', 'public');

        self::assertSame('default', Storage::read('default.txt'));
        self::assertSame('public', Storage::disk('public')->read('public.txt'));
        self::assertSame('/storage/public.txt', Storage::url('public.txt', 'public'));
    }

    public function testUrlRequiresConfiguredDiskUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Filesystem disk [local] does not define a public URL.');

        $this->manager()->url('private.txt', 'local');
    }

    public function testMissingDiskThrowsClearException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Filesystem disk [missing] is not configured.');

        $this->manager()->disk('missing');
    }

    public function testUnsupportedDriverThrowsClearException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Filesystem driver [s3] is not supported.');

        (new FilesystemManager([
            'default' => 'cloud',
            'disks' => [
                'cloud' => ['driver' => 's3'],
            ],
        ]))->disk('cloud');
    }

    private function manager(string $default = 'local'): FilesystemManager
    {
        return new FilesystemManager([
            'default' => $default,
            'disks' => [
                'local' => [
                    'driver' => 'local',
                    'root' => $this->tempDir . '/storage/app',
                ],
                'public' => [
                    'driver' => 'local',
                    'root' => $this->tempDir . '/storage/app/public',
                    'url' => '/storage',
                    'visibility' => 'public',
                ],
            ],
        ]);
    }
}
