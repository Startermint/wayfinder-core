<?php

declare(strict_types=1);

namespace Wayfinder\Queue\Connectors;

use DateInterval;
use DateTimeInterface;
use Predis\Client as PredisClient;
use Wayfinder\Queue\PayloadSerializer;
use Wayfinder\Queue\QueueConnection;
use Wayfinder\Queue\QueuedJob;
use Wayfinder\Queue\Support\InteractsWithTime;

final class RedisQueueConnection implements QueueConnection
{
    use InteractsWithTime;

    public function __construct(
        private readonly string $name,
        private readonly PredisClient $redis,
        private readonly PayloadSerializer $serializer,
        private readonly string $defaultQueue = 'default',
        private readonly int $retryAfter = 90,
        private readonly string $prefix = 'queues:',
    ) {
    }

    public function push(object $job, ?string $queue = null): mixed
    {
        $payload = $this->serializer->create($job);
        $this->redis->rpush($this->readyKey($this->queueName($queue)), [$this->serializer->encode($payload)]);

        return $payload['uuid'];
    }

    public function later(DateTimeInterface|DateInterval|int $delay, object $job, ?string $queue = null): mixed
    {
        $payload = $this->serializer->create($job);
        $this->redis->zadd(
            $this->delayedKey($this->queueName($queue)),
            [$this->serializer->encode($payload) => $this->availableAt($delay)],
        );

        return $payload['uuid'];
    }

    public function pop(?string $queue = null): ?QueuedJob
    {
        $queue = $this->queueName($queue);
        $this->migrateExpired($this->delayedKey($queue), $this->readyKey($queue));
        $this->migrateExpired($this->reservedKey($queue), $this->readyKey($queue));

        $raw = $this->redis->lpop($this->readyKey($queue));

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $payload = $this->serializer->decode($raw);
        $attempts = ((int) ($payload['attempts'] ?? 0)) + 1;
        $payload['attempts'] = $attempts;
        $reservedRaw = $this->serializer->encode($payload);
        $this->redis->zadd($this->reservedKey($queue), [$reservedRaw => $this->currentTime() + $this->retryAfter]);

        return new QueuedJob(
            id: (string) ($payload['uuid'] ?? sha1($reservedRaw)),
            connection: $this->name,
            queue: $queue,
            payload: $payload,
            attempts: $attempts,
            raw: $reservedRaw,
        );
    }

    public function delete(QueuedJob $job): void
    {
        if (is_string($job->raw)) {
            $this->redis->zrem($this->reservedKey($job->queue), $job->raw);
        }
    }

    public function release(QueuedJob $job, DateTimeInterface|DateInterval|int $delay = 0): void
    {
        if (is_string($job->raw)) {
            $this->redis->zrem($this->reservedKey($job->queue), $job->raw);
            $this->redis->zadd($this->delayedKey($job->queue), [$job->raw => $this->availableAt($delay)]);
        }
    }

    private function migrateExpired(string $from, string $to): void
    {
        $now = $this->currentTime();
        $expired = $this->redis->zrangebyscore($from, '-inf', (string) $now);

        foreach ($expired as $payload) {
            if (! is_string($payload)) {
                continue;
            }

            $this->redis->zrem($from, $payload);
            $this->redis->rpush($to, [$payload]);
        }
    }

    private function queueName(?string $queue): string
    {
        return $queue !== null && $queue !== '' ? $queue : $this->defaultQueue;
    }

    private function readyKey(string $queue): string
    {
        return $this->prefix . $queue;
    }

    private function delayedKey(string $queue): string
    {
        return $this->prefix . $queue . ':delayed';
    }

    private function reservedKey(string $queue): string
    {
        return $this->prefix . $queue . ':reserved';
    }
}

