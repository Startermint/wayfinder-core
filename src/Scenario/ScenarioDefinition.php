<?php

declare(strict_types=1);

namespace Wayfinder\Scenario;

final class ScenarioDefinition
{
    /**
     * @param list<ScenarioStepDefinition> $steps
     */
    public function __construct(
        public readonly string $name,
        public array $steps = [],
    ) {
    }

    /**
     * @param array<string, mixed> $args
     */
    public function addStep(string $type, array $args = []): self
    {
        $this->steps[] = new ScenarioStepDefinition($type, $args);

        return $this;
    }
}
