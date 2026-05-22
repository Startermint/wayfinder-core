<?php

declare(strict_types=1);

namespace Wayfinder\Scenario;

use Wayfinder\Contracts\Container;
use Wayfinder\Transport\TransportManager;

final class ScenarioRunner
{
    public function __construct(
        private readonly StepRegistry $steps,
        private readonly ?Container $container = null,
        private readonly ?TransportManager $transports = null,
    ) {
    }

    public function run(ScenarioDefinition $scenario, ?ScenarioContext $context = null): ScenarioContext
    {
        if ($context === null) {
            $events = new EventRecorder();
            $context = new ScenarioContext($this->transports ?? TransportManager::fake($events), $events);
        }

        $context->record('scenario.start', ['name' => $scenario->name]);

        foreach ($scenario->steps as $index => $step) {
            $this->beforeStep($context, $index, $step);
            $this->steps->resolve($step->type, $this->container)->execute($step->args, $context);
            $this->afterStep($context, $index, $step);
        }

        $context->record('scenario.end', ['name' => $scenario->name]);

        return $context;
    }

    private function beforeStep(ScenarioContext $context, int $index, ScenarioStepDefinition $step): void
    {
        $context->record('step.start', [
            'index' => $index,
            'type' => $step->type,
        ]);
    }

    private function afterStep(ScenarioContext $context, int $index, ScenarioStepDefinition $step): void
    {
        $context->record('step.end', [
            'index' => $index,
            'type' => $step->type,
        ]);

        $context->storage['snapshots'][$index] = [
            'step' => $step->type,
            'events_count' => count($context->events->all()),
            'memory' => memory_get_usage(true),
        ];
    }
}
