<?php

declare(strict_types=1);

namespace Wayfinder\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

final class SensitiveContextProcessor implements ProcessorInterface
{
    /**
     * @param list<string> $sensitiveKeys
     */
    public function __construct(
        private readonly array $sensitiveKeys = [],
        private readonly string $replacement = '[redacted]',
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(context: $this->redact($record->context));
    }

    /**
     * @param array<mixed> $context
     * @return array<mixed>
     */
    private function redact(array $context): array
    {
        foreach ($context as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $context[$key] = $this->replacement;
                continue;
            }

            if (is_array($value)) {
                $context[$key] = $this->redact($value);
            }
        }

        return $context;
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        foreach ($this->keys() as $sensitiveKey) {
            if ($key === $sensitiveKey || str_contains($key, $sensitiveKey)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function keys(): array
    {
        if ($this->sensitiveKeys !== []) {
            return array_map(static fn (string $key): string => strtolower($key), $this->sensitiveKeys);
        }

        return [
            'authorization',
            'cookie',
            'csrf',
            'password',
            'secret',
            'token',
            'api_key',
            'apikey',
            'private_key',
        ];
    }
}
