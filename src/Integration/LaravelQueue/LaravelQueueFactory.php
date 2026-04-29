<?php

declare(strict_types=1);

namespace Wayfinder\Integration\LaravelQueue;

use Illuminate\Config\Repository;
use Illuminate\Container\Container as IlluminateContainer;
use Illuminate\Database\Capsule\Manager as CapsuleManager;
use Illuminate\Database\DatabaseManager as IlluminateDatabaseManager;
use Illuminate\Contracts\Queue\Factory as LaravelQueueFactoryContract;
use Illuminate\Contracts\Redis\Factory as RedisFactoryContract;
use Illuminate\Events\Dispatcher;
use Illuminate\Queue\Connectors\BeanstalkdConnector;
use Illuminate\Queue\Connectors\DatabaseConnector;
use Illuminate\Queue\Connectors\RedisConnector;
use Illuminate\Queue\Connectors\SyncConnector;
use Illuminate\Queue\QueueManager;
use Illuminate\Redis\RedisManager;
use Pheanstalk\Pheanstalk;
use Wayfinder\Contracts\Queue\QueueBus;
use Wayfinder\Queue\Exception\QueueException;

final class LaravelQueueFactory
{
    /**
     * @param array<string, mixed> $config
     */
    public function make(array $config, ?IlluminateContainer $container = null): LaravelQueueFactoryContract
    {
        $this->assertIlluminatePackagesAvailable();

        $connections = $config['connections'] ?? null;
        if (! is_array($connections) || $connections === []) {
            throw new QueueException('Queue config must define at least one connection.');
        }

        $default = $config['default'] ?? array_key_first($connections);
        if (! is_string($default) || $default === '') {
            throw new QueueException('Queue config must define a non-empty default connection.');
        }

        if (! array_key_exists($default, $connections)) {
            throw new QueueException(sprintf('Queue default connection [%s] is not defined.', $default));
        }

        $container ??= new IlluminateContainer();

        if (! $container->bound('config')) {
            $container->instance('config', new Repository([
                'queue.default' => $default,
                'queue.connections' => $connections,
                'queue.failed' => is_array($config['failed'] ?? null) ? $config['failed'] : [],
            ]));
        }

        if (! $container->bound('events')) {
            $container->instance('events', new Dispatcher($container));
        }

        $manager = new QueueManager($container);
        $this->registerConnectors($manager, $container, $config);

        return $manager;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function makeBus(array $config, ?string $connection = null, ?IlluminateContainer $container = null): QueueBus
    {
        return new LaravelQueueBus($this->make($config, $container), $connection);
    }

    private function assertIlluminatePackagesAvailable(): void
    {
        if (
            ! class_exists(QueueManager::class)
            || ! class_exists(IlluminateContainer::class)
            || ! class_exists(Repository::class)
            || ! class_exists(Dispatcher::class)
        ) {
            throw new QueueException('Laravel queue integration requires illuminate/config, illuminate/events, and illuminate/queue to be installed.');
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function registerConnectors(QueueManager $manager, IlluminateContainer $container, array $config): void
    {
        $manager->addConnector('sync', static fn (): SyncConnector => new SyncConnector());

        $defaultConnectionName = is_string($config['default'] ?? null) ? $config['default'] : 'sync';
        $defaultConnection = $config['connections'][$defaultConnectionName] ?? null;
        $defaultDriver = is_array($defaultConnection) ? ($defaultConnection['driver'] ?? null) : null;

        if ($defaultDriver === 'beanstalkd') {
            if (! class_exists(Pheanstalk::class)) {
                throw new QueueException('Beanstalkd queue support requires pda/pheanstalk to be installed.');
            }

            $manager->addConnector('beanstalkd', static fn (): BeanstalkdConnector => new BeanstalkdConnector());
        }

        if ($defaultDriver === 'redis') {
            if (! class_exists(RedisManager::class)) {
                throw new QueueException('Redis queue support requires illuminate/redis to be installed.');
            }

            $redisConfig = $config['redis'] ?? [];
            if (! is_array($redisConfig) || $redisConfig === []) {
                throw new QueueException('Redis queue support requires a redis config array.');
            }

            if (! $container->bound('redis')) {
                $client = is_string($redisConfig['client'] ?? null) ? $redisConfig['client'] : 'predis';
                $connections = is_array($redisConfig['connections'] ?? null) ? $redisConfig['connections'] : [];
                $clusters = is_array($redisConfig['clusters'] ?? null) ? $redisConfig['clusters'] : [];
                $options = is_array($redisConfig['options'] ?? null) ? $redisConfig['options'] : [];
                $redisManagerConfig = $connections;

                if ($clusters !== []) {
                    $redisManagerConfig['clusters'] = $clusters;
                }

                if ($options !== []) {
                    $redisManagerConfig['options'] = $options;
                }

                $redisManager = new RedisManager($container, $client, $redisManagerConfig);

                $container->instance('redis', $redisManager);
                $container->instance(RedisManager::class, $redisManager);
                $container->instance(RedisFactoryContract::class, $redisManager);
            }

            $manager->addConnector('redis', static function () use ($container): RedisConnector {
                return new RedisConnector($container->get(RedisFactoryContract::class));
            });
        }

        if ($defaultDriver === 'database') {
            $databaseConfig = $config['database'] ?? [];
            if (! is_array($databaseConfig) || $databaseConfig === []) {
                throw new QueueException('Database queue support requires a database config array.');
            }

            if (! $container->bound('db')) {
                $capsule = new CapsuleManager($container);

                $normalized = $databaseConfig['connections'] ?? null;
                if (! is_array($normalized) || $normalized === []) {
                    throw new QueueException('Database queue support requires database connections to be configured.');
                }

                foreach ($normalized as $name => $connection) {
                    if (! is_string($name) || ! is_array($connection)) {
                        continue;
                    }

                    $capsule->addConnection($this->normalizeIlluminateDatabaseConnection($connection), $name);
                }

                if ($container->bound('events')) {
                    $capsule->setEventDispatcher($container->get('events'));
                }

                $container->instance('db', $capsule->getDatabaseManager());
                $container->instance(IlluminateDatabaseManager::class, $capsule->getDatabaseManager());
            }

            $manager->addConnector('database', static function () use ($container): DatabaseConnector {
                return new DatabaseConnector($container->get(IlluminateDatabaseManager::class));
            });
        }
    }

    /**
     * @param array<string, mixed> $connection
     * @return array<string, mixed>
     */
    private function normalizeIlluminateDatabaseConnection(array $connection): array
    {
        $driver = is_string($connection['driver'] ?? null) ? $connection['driver'] : 'sqlite';

        if ($driver === 'sqlite') {
            return [
                'driver' => 'sqlite',
                'database' => $connection['database'] ?? $connection['path'] ?? ':memory:',
                'prefix' => '',
            ];
        }

        return [
            'driver' => $driver,
            'host' => $connection['host'] ?? '127.0.0.1',
            'port' => $connection['port'] ?? ($driver === 'pgsql' ? 5432 : 3306),
            'database' => $connection['database'] ?? $connection['dbname'] ?? '',
            'username' => $connection['username'] ?? 'root',
            'password' => $connection['password'] ?? '',
            'charset' => $connection['charset'] ?? ($driver === 'pgsql' ? 'utf8' : 'utf8mb4'),
            'collation' => $connection['collation'] ?? ($driver === 'pgsql' ? 'utf8_unicode_ci' : 'utf8mb4_unicode_ci'),
            'prefix' => '',
        ];
    }
}
