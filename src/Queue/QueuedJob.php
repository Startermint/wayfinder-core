<?php

declare(strict_types=1);

namespace Wayfinder\Queue;

final class QueuedJob
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly mixed $id,
        public readonly string $connection,
        public readonly string $queue,
        public readonly array $payload,
        public readonly int $attempts,
        public readonly mixed $raw = null,
    ) {
    }

    public function uuid(): string
    {
        return (string) ($this->payload['uuid'] ?? $this->id);
    }

    public function displayName(): string
    {
        return (string) ($this->payload['display_name'] ?? 'Queued job');
    }
}

