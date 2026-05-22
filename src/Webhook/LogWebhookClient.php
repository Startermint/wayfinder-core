<?php

declare(strict_types=1);

namespace Wayfinder\Webhook;

use Wayfinder\Logging\Logger;
use Wayfinder\Logging\NullLogger;

final class LogWebhookClient implements WebhookClient
{
    public function __construct(
        private readonly Logger $logger = new NullLogger(),
    ) {
    }

    public function post(WebhookRequest $request): void
    {
        $this->logger->info('Webhook posted.', [
            'url' => $request->url(),
            'event' => $request->event(),
            'payload' => $request->payload(),
            'headers' => $request->headers(),
        ]);
    }
}
