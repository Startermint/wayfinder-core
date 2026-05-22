# Scenarios and Fake Transports

Wayfinder treats mail, SMS, queue, and webhook delivery as transport ports. Fake implementations are normal transport adapters that can be swapped into the container for scenario or test execution.

The framework owns the runtime spine:

- `Wayfinder\Scenario\ScenarioDefinition`
- `Wayfinder\Scenario\ScenarioContext`
- `Wayfinder\Scenario\ScenarioRunner`
- `Wayfinder\Scenario\StepRegistry`
- `Wayfinder\Transport\TransportManager`
- `Wayfinder\Transport\TransportContext`
- fake mail, SMS, queue, and webhook adapters

Applications own the scenario definitions and concrete step classes.

## Define Steps

```php
use Wayfinder\Scenario\ScenarioContext;
use Wayfinder\Scenario\ScenarioStep;

final class SendWelcomeStep implements ScenarioStep
{
    public function execute(array $args, ScenarioContext $context): void
    {
        // Call real application services here.
        $context->record('welcome.sent', ['user_id' => $args['user_id'] ?? null]);
    }
}
```

## Run a Scenario

```php
use Wayfinder\Scenario\Scenario;
use Wayfinder\Scenario\ScenarioRunner;
use Wayfinder\Scenario\StepRegistry;

$steps = (new StepRegistry())
    ->register('send_welcome', SendWelcomeStep::class);

$scenario = Scenario::make('Welcome flow')
    ->addStep('send_welcome', ['user_id' => 123]);

$context = (new ScenarioRunner($steps, $container))->run($scenario);
```

The returned context contains recorded events and per-step snapshots.

## Scenario Time

`ScenarioContext` includes a `ClockManager`, which implements Wayfinder's `Clock` interface and uses Carbon internally.

```php
$context->clock->freeze('2026-05-01 09:00:00');
$context->clock->travel('+30 days');
$context->clock->reset();
```

In scoped fake transport mode, the same clock is bound into the container as both `ClockManager::class` and `Clock::class`.

## Scoped Fake Transports

Use `TransportContext::fake()` to bind fake transports for only the duration of a callback. The original container bindings are restored afterward.

```php
use Wayfinder\Contracts\Queue\QueueBus;
use Wayfinder\Mail\Mailer;
use Wayfinder\Mail\MailMessage;
use Wayfinder\Transport\TransportContext;

TransportContext::fake($container, function ($scenario, $container): void {
    $container->get(Mailer::class)->send(
        new MailMessage('person@example.com', 'Welcome', 'Hello')
    );

    $container->get(QueueBus::class)->dispatch(new SendWelcomeEmail());
});
```

Fake transports record activity to the scenario event recorder and keep adapter-local message/job/request lists for assertions.

## Assertions

```php
use Wayfinder\Transport\TransportAssertions;

$assert = new TransportAssertions($scenario->transports);

$assert->mailer()->sentTo('person@example.com');
$assert->queue()->dispatched(SendWelcomeEmail::class);
$assert->webhooks()->postedEvent('survey.completed');
```

## Queue Modes

Fake queue dispatching supports two modes:

- recorded mode: store jobs without running them
- immediate mode: run job `handle()` methods in-process

```php
TransportContext::fake($container, function () {
    // ...
}, executeQueuedJobs: true);
```
