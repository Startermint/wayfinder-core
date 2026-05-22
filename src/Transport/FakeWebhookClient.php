<?php

declare(strict_types=1);

namespace Wayfinder\Transport;

use Wayfinder\Scenario\EventRecorder;
use Wayfinder\Webhook\WebhookClient;
use Wayfinder\Webhook\WebhookRequest;

final class FakeWebhookClient implements WebhookClient
{
    /**
     * @var list<WebhookRequest>
     */
    private array $posted = [];

    public function __construct(
        private readonly ?EventRecorder $events = null,
    ) {
    }

    public function post(WebhookRequest $request): void
    {
        $this->posted[] = $request;
        $this->events?->record('webhook.sent', [
            'url' => $request->url(),
            'event' => $request->event(),
            'payload' => $request->payload(),
        ]);
    }

    /**
     * @return list<WebhookRequest>
     */
    public function posted(): array
    {
        return $this->posted;
    }
}
