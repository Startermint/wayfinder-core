<?php

declare(strict_types=1);

namespace Wayfinder\Scenario;

final class Scenario
{
    public static function make(string $name): ScenarioDefinition
    {
        return new ScenarioDefinition($name);
    }
}
