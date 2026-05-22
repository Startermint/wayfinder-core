<?php

declare(strict_types=1);

namespace Wayfinder\Scenario;

final class EventRecorder
{
    /**
     * @var list<array{type: string, payload: array<string, mixed>, time: float}>
     */
    private array $events = [];

    /**
     * @param array<string, mixed> $payload
     */
    public function record(string $type, array $payload = []): void
    {
        $this->events[] = [
            'type' => $type,
            'payload' => $payload,
            'time' => microtime(true),
        ];
    }

    /**
     * @return list<array{type: string, payload: array<string, mixed>, time: float}>
     */
    public function all(): array
    {
        return $this->events;
    }

    /**
     * @return list<array{type: string, payload: array<string, mixed>, time: float}>
     */
    public function ofType(string $type): array
    {
        return array_values(array_filter(
            $this->events,
            static fn (array $event): bool => $event['type'] === $type,
        ));
    }

    public function count(string $type): int
    {
        return count($this->ofType($type));
    }
}
