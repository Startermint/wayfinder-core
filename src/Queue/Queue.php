<?php

declare(strict_types=1);

namespace Wayfinder\Queue;

use DateInterval;
use DateTimeInterface;
use Wayfinder\Contracts\Queue\QueueBus;

final class Queue
{
    /**
     * @var callable(?string): QueueBus|null
     */
    private static $resolver = null;

    /**
     * @param callable(?string): QueueBus $resolver
     */
    public static function setResolver(callable $resolver): void
    {
        self::$resolver = $resolver;
    }

    public static function bus(?string $connection = null): QueueBus
    {
        if (self::$resolver === null) {
            throw new \RuntimeException('No queue resolver has been configured.');
        }

        return (self::$resolver)($connection);
    }

    public static function dispatch(object $job): mixed
    {
        return self::bus()->dispatch($job);
    }

    public static function dispatchTo(string $queue, object $job): mixed
    {
        return self::bus()->dispatchTo($queue, $job);
    }

    public static function dispatchLater(DateTimeInterface|DateInterval|int $delay, object $job): mixed
    {
        return self::bus()->dispatchLater($delay, $job);
    }

    public static function dispatchLaterTo(string $queue, DateTimeInterface|DateInterval|int $delay, object $job): mixed
    {
        return self::bus()->dispatchLaterTo($queue, $delay, $job);
    }
}

