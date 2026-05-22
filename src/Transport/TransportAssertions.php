<?php

declare(strict_types=1);

namespace Wayfinder\Transport;

final class TransportAssertions
{
    public function __construct(
        private readonly TransportManager $transports,
    ) {
    }

    public function mailer(): MailerAssertions
    {
        return new MailerAssertions($this->transports->mailer());
    }

    public function sms(): SmsAssertions
    {
        return new SmsAssertions($this->transports->sms());
    }

    public function queue(): QueueAssertions
    {
        return new QueueAssertions($this->transports->queue());
    }

    public function webhooks(): WebhookAssertions
    {
        return new WebhookAssertions($this->transports->webhooks());
    }
}
