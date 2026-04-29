<?php

declare(strict_types=1);

namespace Wayfinder\Tests\Integration\LaravelQueue;

use DateInterval;
use Illuminate\Contracts\Queue\Factory as LaravelQueueFactoryContract;
use Illuminate\Contracts\Queue\Queue as LaravelQueueContract;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Wayfinder\Integration\LaravelQueue\LaravelQueueBus;
use Wayfinder\Queue\Exception\QueueException;

final class LaravelQueueBusTest extends TestCase
{
    protected function setUp(): void
    {
        if (! interface_exists(LaravelQueueFactoryContract::class) || ! interface_exists(LaravelQueueContract::class)) {
            $this->markTestSkipped('Illuminate queue contracts are not installed.');
        }
    }

    public function testDispatchMethodsDelegateToLaravelQueueConnection(): void
    {
        $factory = $this->createMock(LaravelQueueFactoryContract::class);
        $queue = $this->createMock(LaravelQueueContract::class);
        $job = new \stdClass();

        $factory->expects(self::exactly(4))
            ->method('connection')
            ->with('emails')
            ->willReturn($queue);

        $queue->expects(self::once())
            ->method('push')
            ->with($job)
            ->willReturn('push-id');

        $queue->expects(self::once())
            ->method('pushOn')
            ->with('critical', $job)
            ->willReturn('push-on-id');

        $queue->expects(self::once())
            ->method('later')
            ->with(60, $job)
            ->willReturn('later-id');

        $queue->expects(self::once())
            ->method('laterOn')
            ->with('critical', self::isInstanceOf(DateInterval::class), $job)
            ->willReturn('later-on-id');

        $bus = new LaravelQueueBus($factory, 'emails');

        self::assertSame('push-id', $bus->dispatch($job));
        self::assertSame('push-on-id', $bus->dispatchTo('critical', $job));
        self::assertSame('later-id', $bus->dispatchLater(60, $job));
        self::assertSame('later-on-id', $bus->dispatchLaterTo('critical', new DateInterval('PT5M'), $job));
    }

    public function testDispatchWrapsUnderlyingQueueException(): void
    {
        $factory = $this->createMock(LaravelQueueFactoryContract::class);
        $queue = $this->createMock(LaravelQueueContract::class);
        $job = new \stdClass();

        $factory->method('connection')->willReturn($queue);
        $queue->method('push')->willThrowException(new RuntimeException('queue down'));

        $bus = new LaravelQueueBus($factory);

        $this->expectException(QueueException::class);
        $this->expectExceptionMessage('Unable to dispatch queued job.');

        $bus->dispatch($job);
    }
}
