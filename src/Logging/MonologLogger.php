<?php

declare(strict_types=1);

namespace Wayfinder\Logging;

use Psr\Log\LoggerInterface;

final class MonologLogger implements Logger
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $this->logger->log(strtolower($level), $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    public function psrLogger(): LoggerInterface
    {
        return $this->logger;
    }
}
