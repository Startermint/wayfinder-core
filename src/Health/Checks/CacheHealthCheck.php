<?php

declare(strict_types=1);

namespace Wayfinder\Health\Checks;

use Wayfinder\Cache\Cache;
use Wayfinder\Health\HealthCheck;
use Wayfinder\Health\HealthResult;

final readonly class CacheHealthCheck implements HealthCheck
{
    public function __construct(
        private Cache $cache,
        private string $name = 'cache',
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function check(): HealthResult
    {
        $key = 'wayfinder:health:' . bin2hex(random_bytes(8));
        $value = bin2hex(random_bytes(8));

        $this->cache->put($key, $value, 60);
        $stored = $this->cache->get($key);
        $this->cache->forget($key);

        if ($stored !== $value) {
            return HealthResult::fail($this->name, 'Cache write/read check failed.');
        }

        return HealthResult::ok($this->name, 'Cache write/read/delete check succeeded.');
    }
}
