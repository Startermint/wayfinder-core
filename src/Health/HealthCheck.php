<?php

declare(strict_types=1);

namespace Wayfinder\Health;

interface HealthCheck
{
    public function name(): string;

    public function check(): HealthResult;
}
