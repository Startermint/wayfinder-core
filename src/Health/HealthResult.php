<?php

declare(strict_types=1);

namespace Wayfinder\Health;

final readonly class HealthResult
{
    public const OK = 'ok';
    public const WARN = 'warn';
    public const FAIL = 'fail';

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $name,
        public string $status,
        public string $message,
        public array $context = [],
    ) {
        if (! in_array($status, [self::OK, self::WARN, self::FAIL], true)) {
            throw new \InvalidArgumentException(sprintf('Invalid health status [%s].', $status));
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function ok(string $name, string $message, array $context = []): self
    {
        return new self($name, self::OK, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function warn(string $name, string $message, array $context = []): self
    {
        return new self($name, self::WARN, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function fail(string $name, string $message, array $context = []): self
    {
        return new self($name, self::FAIL, $message, $context);
    }

    /**
     * @return array{name: string, status: string, message: string, context: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'status' => $this->status,
            'message' => $this->message,
            'context' => $this->context,
        ];
    }
}
