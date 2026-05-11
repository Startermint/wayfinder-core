<?php

declare(strict_types=1);

namespace Wayfinder\Queue;

use Predis\Client as PredisClient;
use Pheanstalk\Pheanstalk;
use Wayfinder\Contracts\Container;
use Wayfinder\Database\Database;
use Wayfinder\Queue\Connectors\BeanstalkdQueueConnection;
use Wayfinder\Queue\Connectors\DatabaseQueueConnection;
use Wayfinder\Queue\Connectors\RedisQueueConnection;
use Wayfinder\Queue\Connectors\SyncQueueConnection;
use Wayfinder\Queue\Exception\QueueException;

final class QueueManager
{
    /**
     * @var array<string, QueueConnection>
     */
    private array $connections = [];

    /**
     * @param array<string, mixed> $config
     * @param array<string, Database> $databases
     */
    public function __construct(
        private readonly array $config,
        private readonly ?Container $container = null,
        private readonly array $databases = [],
        private readonly ?PayloadSerializer $serializer = null,
        private readonly ?JobHandler $handler = null,
    ) {
    }

    public function defaultConnectionName(): string
    {
        $default = $this->config['default'] ?? 'sync';

        if (! is_string($default) || $default === '') {
            throw new QueueException('Queue config must define a non-empty default connection.');
        }

        return $default;
    }

    public function connection(?string $name = null): QueueConnection
    {
        $name ??= $this->defaultConnectionName();

        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        $connections = $this->config['connections'] ?? [];

        if (! is_array($connections) || ! isset($connections[$name]) || ! is_array($connections[$name])) {
            throw new QueueException(sprintf('Queue connection [%s] is not configured.', $name));
        }

        return $this->connections[$name] = $this->makeConnection($name, $connections[$name]);
    }

    public function bus(?string $connection = null): NativeQueueBus
    {
        return new NativeQueueBus($this, $connection);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function makeConnection(string $name, array $config): QueueConnection
    {
        $driver = $config['driver'] ?? $name;

        if (! is_string($driver) || $driver === '') {
            throw new QueueException(sprintf('Queue connection [%s] must define a driver.', $name));
        }

        return match ($driver) {
            'sync' => new SyncQueueConnection(
                $name,
                $this->serializer(),
                $this->handler(),
                $this->defaultQueue($config),
            ),
            'database' => new DatabaseQueueConnection(
                $name,
                $this->databaseFor($config),
                $this->serializer(),
                table: (string) ($config['table'] ?? 'jobs'),
                defaultQueue: $this->defaultQueue($config),
                retryAfter: (int) ($config['retry_after'] ?? 90),
            ),
            'redis' => new RedisQueueConnection(
                $name,
                $this->redisClient($config),
                $this->serializer(),
                defaultQueue: $this->defaultQueue($config),
                retryAfter: (int) ($config['retry_after'] ?? 90),
                prefix: (string) ($config['prefix'] ?? 'queues:'),
            ),
            'beanstalkd' => new BeanstalkdQueueConnection(
                $name,
                $this->beanstalkd($config),
                $this->serializer(),
                defaultQueue: $this->defaultQueue($config),
                retryAfter: (int) ($config['retry_after'] ?? 90),
            ),
            default => throw new QueueException(sprintf('Unsupported queue driver [%s].', $driver)),
        };
    }

    private function serializer(): PayloadSerializer
    {
        return $this->serializer ?? new PayloadSerializer();
    }

    private function handler(): JobHandler
    {
        return $this->handler ?? new JobHandler($this->container);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function defaultQueue(array $config): string
    {
        $queue = $config['queue'] ?? 'default';

        return is_string($queue) && $queue !== '' ? $queue : 'default';
    }

    /**
     * @param array<string, mixed> $config
     */
    private function databaseFor(array $config): Database
    {
        $connection = (string) ($config['connection'] ?? 'default');

        if (isset($this->databases[$connection])) {
            return $this->databases[$connection];
        }

        if (isset($this->databases['default'])) {
            return $this->databases['default'];
        }

        $databaseConfig = $config['database'] ?? null;

        if (is_array($databaseConfig)) {
            return new Database($databaseConfig);
        }

        throw new QueueException(sprintf('Database queue connection [%s] requires a Database instance or database config.', $connection));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function redisClient(array $config): PredisClient
    {
        if (! class_exists(PredisClient::class)) {
            throw new QueueException('Redis queue support requires predis/predis to be installed.');
        }

        $parameters = $config['parameters'] ?? [
            'scheme' => 'tcp',
            'host' => $config['host'] ?? '127.0.0.1',
            'port' => $config['port'] ?? 6379,
            'database' => $config['database'] ?? 0,
        ];

        return new PredisClient($parameters, is_array($config['options'] ?? null) ? $config['options'] : []);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function beanstalkd(array $config): Pheanstalk
    {
        if (! class_exists(Pheanstalk::class)) {
            throw new QueueException('Beanstalkd queue support requires pda/pheanstalk to be installed.');
        }

        return Pheanstalk::create(
            (string) ($config['host'] ?? '127.0.0.1'),
            (int) ($config['port'] ?? 11300),
        );
    }
}

