<?php

declare(strict_types=1);

namespace Wayfinder\Logging;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger as Monolog;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger as PsrNullLogger;

final class LogManager
{
    /** @var array<string, LoggerInterface> */
    private array $channels = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config,
    ) {
    }

    public function logger(?string $channel = null): LoggerInterface
    {
        $channel ??= $this->defaultChannelName();

        if (isset($this->channels[$channel])) {
            return $this->channels[$channel];
        }

        return $this->channels[$channel] = $this->build($channel);
    }

    public function wayfinderLogger(?string $channel = null): Logger
    {
        return new MonologLogger($this->logger($channel));
    }

    public function defaultChannelName(): string
    {
        return (string) ($this->config['default'] ?? 'single');
    }

    private function build(string $channel): LoggerInterface
    {
        $config = $this->channelConfig($channel);
        $driver = (string) ($config['driver'] ?? 'single');

        if ($driver === 'null') {
            return new PsrNullLogger();
        }

        $logger = new Monolog($channel);
        $logger->pushHandler(match ($driver) {
            'single', 'file' => $this->singleHandler($config),
            'daily' => $this->dailyHandler($config),
            'stderr' => $this->streamHandler('php://stderr', $config),
            'stream' => $this->streamHandler((string) ($config['path'] ?? 'php://stderr'), $config),
            default => throw new \InvalidArgumentException(sprintf('Logging driver [%s] is not supported.', $driver)),
        });

        return $logger;
    }

    /**
     * @return array<string, mixed>
     */
    private function channelConfig(string $channel): array
    {
        $channels = $this->config['channels'] ?? [];

        if (! is_array($channels) || ! isset($channels[$channel]) || ! is_array($channels[$channel])) {
            throw new \InvalidArgumentException(sprintf('Logging channel [%s] is not configured.', $channel));
        }

        return $channels[$channel];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function singleHandler(array $config): StreamHandler
    {
        $path = $config['path'] ?? null;

        if (! is_string($path) || $path === '') {
            throw new \InvalidArgumentException('Single file logging channels require a non-empty [path].');
        }

        return $this->streamHandler($path, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function dailyHandler(array $config): RotatingFileHandler
    {
        $path = $config['path'] ?? null;

        if (! is_string($path) || $path === '') {
            throw new \InvalidArgumentException('Daily logging channels require a non-empty [path].');
        }

        $handler = new RotatingFileHandler(
            $path,
            max(0, (int) ($config['days'] ?? 14)),
            $this->level($config['level'] ?? 'debug'),
        );
        $handler->setFormatter($this->formatter());

        return $handler;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function streamHandler(string $stream, array $config): StreamHandler
    {
        $handler = new StreamHandler($stream, $this->level($config['level'] ?? 'debug'));
        $handler->setFormatter($this->formatter());

        return $handler;
    }

    private function formatter(): LineFormatter
    {
        return new LineFormatter("[%datetime%] %level_name%: %message% %context%\n", 'Y-m-d H:i:s', true, true);
    }

    private function level(mixed $level): Level
    {
        if (! is_string($level) || $level === '') {
            throw new \InvalidArgumentException('Log level must be a non-empty string.');
        }

        return Level::fromName($level);
    }
}
