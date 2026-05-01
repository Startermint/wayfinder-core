<?php

declare(strict_types=1);

namespace Wayfinder\Support;

final class PathResolver
{
    /**
     * @var callable(): self|null
     */
    private static $resolver = null;

    public function __construct(
        private readonly string $basePath,
    ) {
    }

    /**
     * @param callable(): self $resolver
     */
    public static function setResolver(callable $resolver): void
    {
        self::$resolver = $resolver;
    }

    public static function resolve(): self
    {
        if (self::$resolver === null) {
            throw new \RuntimeException('No path resolver has been configured.');
        }

        return (self::$resolver)();
    }

    public function base(?string $path = null): string
    {
        return $this->join($this->basePath, $path);
    }

    public function app(?string $path = null): string
    {
        return $this->join($this->base('app'), $path);
    }

    public function config(?string $path = null): string
    {
        return $this->join($this->base('config'), $path);
    }

    public function database(?string $path = null): string
    {
        return $this->join($this->base('database'), $path);
    }

    public function lang(?string $path = null): string
    {
        return $this->join($this->base('lang'), $path);
    }

    public function public(?string $path = null): string
    {
        return $this->join($this->base('public'), $path);
    }

    public function resource(?string $path = null): string
    {
        return $this->join($this->base('resources'), $path);
    }

    public function storage(?string $path = null): string
    {
        return $this->join($this->base('storage'), $path);
    }

    private function join(string $base, ?string $path): string
    {
        $base = rtrim($base, '/');

        if ($path === null || trim($path) === '') {
            return $base;
        }

        return $base . '/' . ltrim($path, '/');
    }
}
