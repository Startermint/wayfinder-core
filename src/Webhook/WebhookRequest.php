<?php

declare(strict_types=1);

namespace Wayfinder\Webhook;

final class WebhookRequest
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly string $url,
        private readonly array $payload = [],
        private readonly array $headers = [],
        private readonly string $event = '',
    ) {
    }

    public function url(): string
    {
        return $this->url;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function event(): string
    {
        return $this->event;
    }
}
