<?php

declare(strict_types=1);

namespace Wayfinder\Webhook;

interface WebhookClient
{
    public function post(WebhookRequest $request): void;
}
