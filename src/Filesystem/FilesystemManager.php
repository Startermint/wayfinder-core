<?php

declare(strict_types=1);

namespace Wayfinder\Filesystem;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;

final class FilesystemManager
{
    /** @var array<string, FilesystemOperator> */
    private array $disks = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config,
    ) {
    }

    public function defaultDiskName(): string
    {
        return (string) ($this->config['default'] ?? 'local');
    }

    public function disk(?string $name = null): FilesystemOperator
    {
        $name ??= $this->defaultDiskName();

        if (isset($this->disks[$name])) {
            return $this->disks[$name];
        }

        return $this->disks[$name] = $this->buildDisk($name);
    }

    public function url(string $path, ?string $disk = null): string
    {
        $disk ??= $this->defaultDiskName();
        $config = $this->diskConfig($disk);
        $baseUrl = $config['url'] ?? null;

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new \InvalidArgumentException(sprintf('Filesystem disk [%s] does not define a public URL.', $disk));
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function diskConfig(string $name): array
    {
        $disks = $this->config['disks'] ?? [];

        if (! is_array($disks) || ! isset($disks[$name]) || ! is_array($disks[$name])) {
            throw new \InvalidArgumentException(sprintf('Filesystem disk [%s] is not configured.', $name));
        }

        return $disks[$name];
    }

    private function buildDisk(string $name): FilesystemOperator
    {
        $config = $this->diskConfig($name);
        $driver = (string) ($config['driver'] ?? 'local');

        return match ($driver) {
            'local' => $this->buildLocalDisk($config),
            default => throw new \InvalidArgumentException(sprintf('Filesystem driver [%s] is not supported.', $driver)),
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    private function buildLocalDisk(array $config): FilesystemOperator
    {
        $root = $config['root'] ?? null;

        if (! is_string($root) || $root === '') {
            throw new \InvalidArgumentException('Local filesystem disks require a non-empty [root] path.');
        }

        if (! is_dir($root) && ! @mkdir($root, 0775, true) && ! is_dir($root)) {
            throw new \RuntimeException(sprintf('Unable to create filesystem disk root [%s].', $root));
        }

        $filesystemConfig = [];

        if (isset($config['visibility']) && is_string($config['visibility']) && $config['visibility'] !== '') {
            $filesystemConfig['visibility'] = $config['visibility'];
        }

        return new Filesystem(new LocalFilesystemAdapter($root), $filesystemConfig);
    }
}
