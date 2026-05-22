<?php

declare(strict_types=1);

namespace Wayfinder\Transport;

use Wayfinder\Contracts\Queue\QueueBus;
use Wayfinder\Mail\Mailer;
use Wayfinder\Mail\NullMailer;
use Wayfinder\Scenario\EventRecorder;
use Wayfinder\Sms\NullSmsSender;
use Wayfinder\Sms\SmsSender;
use Wayfinder\Webhook\NullWebhookClient;
use Wayfinder\Webhook\WebhookClient;

final class TransportManager
{
    public function __construct(
        private readonly Mailer $mailer = new NullMailer(),
        private readonly SmsSender $sms = new NullSmsSender(),
        private readonly ?QueueBus $queue = null,
        private readonly WebhookClient $webhooks = new NullWebhookClient(),
    ) {
    }

    public static function fake(?EventRecorder $events = null, bool $executeQueuedJobs = false): self
    {
        return new self(
            new FakeMailer($events),
            new FakeSmsSender($events),
            new FakeQueueDispatcher($events, $executeQueuedJobs),
            new FakeWebhookClient($events),
        );
    }

    public function mailer(): Mailer
    {
        return $this->mailer;
    }

    public function sms(): SmsSender
    {
        return $this->sms;
    }

    public function queue(): QueueBus
    {
        if (! $this->queue instanceof QueueBus) {
            throw new \RuntimeException('No queue transport has been configured.');
        }

        return $this->queue;
    }

    public function webhooks(): WebhookClient
    {
        return $this->webhooks;
    }
}
