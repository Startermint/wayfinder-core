<?php

declare(strict_types=1);

namespace Wayfinder\Transport;

use Wayfinder\Webhook\WebhookClient;

final class WebhookAssertions
{
    public function __construct(
        private readonly WebhookClient $webhooks,
    ) {
    }

    public function postedCount(int $expected): void
    {
        $actual = count($this->fake()->posted());

        if ($actual !== $expected) {
            throw new \RuntimeException(sprintf('Expected %d webhook request(s), saw %d.', $expected, $actual));
        }
    }

    public function postedEvent(string $event): void
    {
        foreach ($this->fake()->posted() as $request) {
            if ($request->event() === $event) {
                return;
            }
        }

        throw new \RuntimeException(sprintf('Expected webhook event [%s] to be posted.', $event));
    }

    private function fake(): FakeWebhookClient
    {
        if (! $this->webhooks instanceof FakeWebhookClient) {
            throw new \RuntimeException('Webhook assertions require FakeWebhookClient.');
        }

        return $this->webhooks;
    }
}
