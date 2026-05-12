<?php

declare(strict_types=1);

namespace Wayfinder\Health;

final readonly class HealthReport
{
    /**
     * @param list<HealthResult> $results
     */
    public function __construct(
        public array $results,
    ) {
    }

    public function status(): string
    {
        if ($this->hasFailures()) {
            return HealthResult::FAIL;
        }

        if ($this->hasWarnings()) {
            return HealthResult::WARN;
        }

        return HealthResult::OK;
    }

    public function hasFailures(): bool
    {
        foreach ($this->results as $result) {
            if ($result->status === HealthResult::FAIL) {
                return true;
            }
        }

        return false;
    }

    public function hasWarnings(): bool
    {
        foreach ($this->results as $result) {
            if ($result->status === HealthResult::WARN) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{status: string, checks: list<array{name: string, status: string, message: string, context: array<string, mixed>}>}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status(),
            'checks' => array_map(static fn (HealthResult $result): array => $result->toArray(), $this->results),
        ];
    }
}
