<?php

declare(strict_types=1);

namespace Wayfinder\Transport;

use Wayfinder\Scenario\EventRecorder;
use Wayfinder\Sms\SmsMessage;
use Wayfinder\Sms\SmsSender;

final class FakeSmsSender implements SmsSender
{
    /**
     * @var list<SmsMessage>
     */
    private array $sent = [];

    public function __construct(
        private readonly ?EventRecorder $events = null,
    ) {
    }

    public function send(SmsMessage $message): void
    {
        $this->sent[] = $message;
        $this->events?->record('sms.sent', [
            'to' => $message->to(),
            'metadata' => $message->metadata(),
        ]);
    }

    /**
     * @return list<SmsMessage>
     */
    public function sent(): array
    {
        return $this->sent;
    }
}
