<?php

declare(strict_types=1);

namespace Wayfinder\Filesystem;

use League\Flysystem\FilesystemOperator;

final class Storage
{
    /**
     * @var callable(?string): FilesystemOperator|null
     */
    private static $resolver = null;

    /**
     * @var callable(string, ?string): string|null
     */
    private static $urlResolver = null;

    /**
     * @param callable(?string): FilesystemOperator $resolver
     */
    public static function setResolver(callable $resolver): void
    {
        self::$resolver = $resolver;
    }

    /**
     * @param callable(string, ?string): string $resolver
     */
    public static function setUrlResolver(callable $resolver): void
    {
        self::$urlResolver = $resolver;
    }

    public static function clearResolver(): void
    {
        self::$resolver = null;
        self::$urlResolver = null;
    }

    public static function disk(?string $name = null): FilesystemOperator
    {
        if (self::$resolver === null) {
            throw new \RuntimeException('No filesystem resolver has been configured.');
        }

        return (self::$resolver)($name);
    }

    public static function url(string $path, ?string $disk = null): string
    {
        if (self::$urlResolver === null) {
            throw new \RuntimeException('No filesystem URL resolver has been configured.');
        }

        return (self::$urlResolver)($path, $disk);
    }

    public static function __callStatic(string $method, array $arguments): mixed
    {
        return self::disk()->{$method}(...$arguments);
    }
}
