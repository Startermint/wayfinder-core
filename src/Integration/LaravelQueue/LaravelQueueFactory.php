<?php

declare(strict_types=1);

namespace Wayfinder\Integration\LaravelQueue;

use Illuminate\Config\Repository;
use Illuminate\Container\Container as IlluminateContainer;
use Illuminate\Contracts\Queue\Factory as LaravelQueueFactoryContract;
use Illuminate\Events\Dispatcher;
use Illuminate\Queue\QueueManager;
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

        return new QueueManager($container);
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
}
