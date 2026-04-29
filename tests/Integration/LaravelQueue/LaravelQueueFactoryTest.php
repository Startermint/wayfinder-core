<?php

declare(strict_types=1);

namespace Wayfinder\Tests\Integration\LaravelQueue;

use Illuminate\Contracts\Queue\Factory as LaravelQueueFactoryContract;
use PHPUnit\Framework\TestCase;
use Wayfinder\Integration\LaravelQueue\LaravelQueueBus;
use Wayfinder\Integration\LaravelQueue\LaravelQueueFactory;
use Wayfinder\Queue\Exception\QueueException;

final class LaravelQueueFactoryTest extends TestCase
{
    public function testMakeThrowsClearErrorWhenIlluminatePackagesAreMissing(): void
    {
        if (interface_exists(LaravelQueueFactoryContract::class)) {
            self::markTestSkipped('Illuminate queue packages are installed.');
        }

        $factory = new LaravelQueueFactory();

        $this->expectException(QueueException::class);
        $this->expectExceptionMessage('Laravel queue integration requires illuminate/config, illuminate/events, and illuminate/queue to be installed.');

        $factory->make([
            'default' => 'sync',
            'connections' => [
                'sync' => ['driver' => 'sync'],
            ],
        ]);
    }

    public function testMakeBuildsLaravelQueueManagerWhenIlluminatePackagesAreInstalled(): void
    {
        if (! interface_exists(LaravelQueueFactoryContract::class)) {
            self::markTestSkipped('Illuminate queue packages are not installed.');
        }

        $factory = new LaravelQueueFactory();
        $manager = $factory->make([
            'default' => 'sync',
            'connections' => [
                'sync' => ['driver' => 'sync'],
            ],
        ]);

        self::assertInstanceOf(LaravelQueueFactoryContract::class, $manager);
        self::assertInstanceOf(LaravelQueueBus::class, $factory->makeBus([
            'default' => 'sync',
            'connections' => [
                'sync' => ['driver' => 'sync'],
            ],
        ]));
    }

    public function testMakeValidatesQueueConfig(): void
    {
        $factory = new LaravelQueueFactory();

        try {
            $factory->make(['default' => 'sync']);
            self::fail('Expected queue config validation to throw.');
        } catch (QueueException $exception) {
            if ($exception->getMessage() === 'Laravel queue integration requires illuminate/config, illuminate/events, and illuminate/queue to be installed.') {
                self::markTestSkipped('Illuminate queue packages are not installed.');
            }

            self::assertSame('Queue config must define at least one connection.', $exception->getMessage());
        }
    }
}
