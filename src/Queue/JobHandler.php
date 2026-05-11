<?php

declare(strict_types=1);

namespace Wayfinder\Queue;

use ReflectionMethod;
use ReflectionNamedType;
use Wayfinder\Contracts\Container;
use Wayfinder\Queue\Exception\QueueException;

final class JobHandler
{
    public function __construct(
        private readonly ?Container $container = null,
    ) {
    }

    public function handle(object $job): mixed
    {
        if (! method_exists($job, 'handle')) {
            throw new QueueException(sprintf('Queued job [%s] must define a handle() method.', $job::class));
        }

        $method = new ReflectionMethod($job, 'handle');
        $arguments = [];

        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin() && $this->container !== null) {
                $arguments[] = $this->container->get($type->getName());
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            throw new QueueException(sprintf(
                'Unable to resolve handle() parameter [$%s] for queued job [%s].',
                $parameter->getName(),
                $job::class,
            ));
        }

        return $method->invokeArgs($job, $arguments);
    }
}

