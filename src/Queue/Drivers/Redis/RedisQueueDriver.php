<?php

declare(strict_types=1);

namespace Wayfinder\Queue\Drivers\Redis;

use DateInterval;
use DateTimeInterface;
use Predis\Client as PredisClient;
use Wayfinder\Queue\PayloadSerializer;
use Wayfinder\Queue\QueueConnection;
use Wayfinder\Queue\QueuedJob;
use Wayfinder\Queue\Support\InteractsWithTime;

final class RedisQueueDriver implements QueueConnection
{
    use InteractsWithTime;

    public function __construct(
        private readonly string $name,
        private readonly PredisClient $redis,
        private readonly PayloadSerializer $serializer,
        private readonly string $defaultQueue = 'default',
        private readonly int $retryAfter = 90,
        private readonly string $prefix = 'queues:',
        private readonly int $migrationBatchSize = 100,
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

    public function reserve(?string $queue = null): ?QueuedJob
    {
        $queue = $this->queueName($queue);
        $this->migrateExpired('MigrateDelayed.lua', $this->delayedKey($queue), $this->readyKey($queue));
        $this->migrateExpired('RestoreExpired.lua', $this->reservedKey($queue), $this->readyKey($queue));

        $raw = $this->redis->eval(
            $this->script('ReserveJob.lua'),
            2,
            $this->readyKey($queue),
            $this->reservedKey($queue),
            (string) ($this->currentTime() + $this->retryAfter),
        );

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $payload = $this->serializer->decode($raw);
        $attempts = (int) ($payload['attempts'] ?? 1);

        return new QueuedJob(
            id: (string) ($payload['uuid'] ?? sha1($raw)),
            connection: $this->name,
            queue: $queue,
            payload: $payload,
            attempts: $attempts,
            raw: $raw,
        );
    }

    public function pop(?string $queue = null): ?QueuedJob
    {
        return $this->reserve($queue);
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
            $this->redis->eval(
                $this->script('ReleaseJob.lua'),
                2,
                $this->reservedKey($job->queue),
                $this->delayedKey($job->queue),
                $job->raw,
                (string) $this->availableAt($delay),
            );
        }
    }

    private function migrateExpired(string $script, string $from, string $to): void
    {
        $this->redis->eval(
            $this->script($script),
            2,
            $from,
            $to,
            (string) $this->currentTime(),
            (string) $this->migrationBatchSize,
        );
    }

    private function script(string $name): string
    {
        $path = __DIR__ . '/Lua/' . $name;
        $script = file_get_contents($path);

        if ($script === false) {
            throw new \RuntimeException(sprintf('Unable to read Redis queue script [%s].', $path));
        }

        return $script;
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
