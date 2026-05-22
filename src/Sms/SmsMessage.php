<?php

declare(strict_types=1);

namespace Wayfinder\Sms;

final class SmsMessage
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        private readonly string $to,
        private readonly string $body,
        private readonly array $metadata = [],
    ) {
    }

    public function to(): string
    {
        return $this->to;
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }
}
