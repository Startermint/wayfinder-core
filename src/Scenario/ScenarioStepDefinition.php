<?php

declare(strict_types=1);

namespace Wayfinder\Scenario;

final class ScenarioStepDefinition
{
    /**
     * @param array<string, mixed> $args
     */
    public function __construct(
        public readonly string $type,
        public readonly array $args = [],
    ) {
    }
}
