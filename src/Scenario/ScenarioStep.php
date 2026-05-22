<?php

declare(strict_types=1);

namespace Wayfinder\Scenario;

interface ScenarioStep
{
    /**
     * @param array<string, mixed> $args
     */
    public function execute(array $args, ScenarioContext $context): void;
}
