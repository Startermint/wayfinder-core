<?php

declare(strict_types=1);

namespace Wayfinder\Queue;

use Wayfinder\Queue\Exception\QueueException;

final class PayloadSerializer
{
    /**
     * @return array<string, mixed>
     */
    public function create(object $job): array
    {
        return [
            'uuid' => $this->uuid(),
            'display_name' => $job::class,
            'job' => base64_encode(serialize($job)),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function restore(array $payload): object
    {
        $encoded = $payload['job'] ?? null;

        if (! is_string($encoded) || $encoded === '') {
            throw new QueueException('Queued payload is missing serialized job data.');
        }

        $serialized = base64_decode($encoded, true);

        if ($serialized === false) {
            throw new QueueException('Queued payload contains invalid serialized job data.');
        }

        $job = unserialize($serialized, ['allowed_classes' => true]);

        if (! is_object($job)) {
            throw new QueueException('Queued payload did not unserialize to an object.');
        }

        return $job;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function encode(array $payload): string
    {
        $json = json_encode($payload);

        if (! is_string($json)) {
            throw new QueueException('Unable to encode queued payload.');
        }

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    public function decode(string $payload): array
    {
        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            throw new QueueException('Unable to decode queued payload.');
        }

        return $decoded;
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20),
        );
    }
}

