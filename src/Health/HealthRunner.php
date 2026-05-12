<?php

declare(strict_types=1);

namespace Wayfinder\Health;

final readonly class HealthRunner
{
    /**
     * @param iterable<HealthCheck> $checks
     */
    public function __construct(
        private iterable $checks,
    ) {
    }

    public function run(): HealthReport
    {
        $results = [];

        foreach ($this->checks as $check) {
            try {
                $results[] = $check->check();
            } catch (\Throwable $exception) {
                $results[] = HealthResult::fail($check->name(), $exception->getMessage(), [
                    'exception' => $exception::class,
                ]);
            }
        }

        return new HealthReport($results);
    }
}
