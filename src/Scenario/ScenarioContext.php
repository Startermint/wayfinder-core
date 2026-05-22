<?php

declare(strict_types=1);

namespace Wayfinder\Scenario;

use Wayfinder\Support\ClockManager;
use Wayfinder\Transport\TransportManager;

final class ScenarioContext
{
    /**
     * @var array<string, mixed>
     */
    public array $storage = [];

    public readonly EventRecorder $events;

    public function __construct(
        public readonly TransportManager $transports,
        ?EventRecorder $events = null,
        public readonly ClockManager $clock = new ClockManager(),
    ) {
        $this->events = $events ?? new EventRecorder();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function record(string $type, array $payload = []): void
    {
        $this->events->record($type, $payload);
    }
}
