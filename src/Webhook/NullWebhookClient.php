<?php

declare(strict_types=1);

namespace Wayfinder\Webhook;

final class NullWebhookClient implements WebhookClient
{
    public function post(WebhookRequest $request): void
    {
    }
}
