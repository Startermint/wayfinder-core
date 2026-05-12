<?php

declare(strict_types=1);

namespace Wayfinder\Queue\Connectors;

class_alias(\Wayfinder\Queue\Drivers\Redis\RedisQueueDriver::class, __NAMESPACE__ . '\\RedisQueueConnection');

