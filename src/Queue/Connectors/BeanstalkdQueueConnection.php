<?php

declare(strict_types=1);

namespace Wayfinder\Queue\Connectors;

class_alias(\Wayfinder\Queue\Drivers\Beanstalkd\BeanstalkdQueueDriver::class, __NAMESPACE__ . '\\BeanstalkdQueueConnection');

