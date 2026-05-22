<?php

declare(strict_types=1);

namespace Wayfinder\Tests\Scenario;

use PHPUnit\Framework\TestCase;
use Wayfinder\Scenario\Scenario;
use Wayfinder\Scenario\ScenarioContext;
use Wayfinder\Scenario\ScenarioRunner;
use Wayfinder\Scenario\ScenarioStep;
use Wayfinder\Scenario\StepRegistry;

final class ScenarioRunnerTest extends TestCase
{
    public function testRunsRegisteredStepsAndRecordsLifecycleEventsAndSnapshots(): void
    {
        $registry = (new StepRegistry())
            ->register('store_value', StoreValueStep::class);

        $context = (new ScenarioRunner($registry))->run(
            Scenario::make('Example')->addStep('store_value', ['key' => 'answer', 'value' => 42]),
        );

        self::assertSame(42, $context->storage['answer'] ?? null);
        self::assertSame(1, $context->events->count('scenario.start'));
        self::assertSame(1, $context->events->count('step.start'));
        self::assertSame(1, $context->events->count('value.stored'));
        self::assertSame(1, $context->events->count('step.end'));
        self::assertSame(1, $context->events->count('scenario.end'));
        self::assertSame('store_value', $context->storage['snapshots'][0]['step'] ?? null);
    }

    public function testUnknownStepThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown scenario step [missing].');

        (new ScenarioRunner(new StepRegistry()))->run(
            Scenario::make('Broken')->addStep('missing'),
        );
    }
}

final class StoreValueStep implements ScenarioStep
{
    public function execute(array $args, ScenarioContext $context): void
    {
        $key = (string) ($args['key'] ?? 'value');
        $context->storage[$key] = $args['value'] ?? null;
        $context->record('value.stored', ['key' => $key]);
    }
}
