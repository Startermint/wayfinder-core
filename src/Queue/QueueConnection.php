<?php

declare(strict_types=1);

namespace Wayfinder\Queue;

use Wayfinder\Queue\Contracts\QueueDriver;

interface QueueConnection extends QueueDriver
{
    public function pop(?string $queue = null): ?QueuedJob;
}
