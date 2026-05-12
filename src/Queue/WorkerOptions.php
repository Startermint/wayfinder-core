<?php

declare(strict_types=1);

namespace Wayfinder\Queue;

final readonly class WorkerOptions
{
    public function __construct(
        public string $connection,
        public string $queue = 'default',
        public int $tries = 1,
        public int $sleep = 3,
        public int $delay = 0,
        public bool $once = false,
        public ?int $maxJobs = null,
    ) {
    }
}
