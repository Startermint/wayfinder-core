<?php

declare(strict_types=1);

namespace Wayfinder\Scenario;

use Wayfinder\Contracts\Container;

final class StepRegistry
{
    /**
     * @var array<string, class-string<ScenarioStep>|callable(Container|null): ScenarioStep>
     */
    private array $steps = [];

    /**
     * @param class-string<ScenarioStep>|callable(Container|null): ScenarioStep $handler
     */
    public function register(string $type, string|callable $handler): self
    {
        $this->steps[$type] = $handler;

        return $this;
    }

    public function resolve(string $type, ?Container $container = null): ScenarioStep
    {
        if (! isset($this->steps[$type])) {
            throw new \RuntimeException(sprintf('Unknown scenario step [%s].', $type));
        }

        $handler = $this->steps[$type];

        if (is_callable($handler)) {
            $resolved = $handler($container);
        } elseif ($container !== null) {
            $resolved = $container->get($handler);
        } else {
            $resolved = new $handler();
        }

        if (! $resolved instanceof ScenarioStep) {
            throw new \RuntimeException(sprintf('Scenario step [%s] did not resolve to a ScenarioStep.', $type));
        }

        return $resolved;
    }
}
