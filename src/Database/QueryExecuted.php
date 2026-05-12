<?php

declare(strict_types=1);

namespace Wayfinder\Database;

final readonly class QueryExecuted
{
    /**
     * @param list<mixed> $bindings
     */
    public function __construct(
        public string $sql,
        public array $bindings,
        public float $milliseconds,
        public string $connection,
    ) {
    }
}
