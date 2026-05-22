<?php

declare(strict_types=1);

namespace Wayfinder\Sms;

use Wayfinder\Logging\Logger;
use Wayfinder\Logging\NullLogger;

final class LogSmsSender implements SmsSender
{
    public function __construct(
        private readonly Logger $logger = new NullLogger(),
    ) {
    }

    public function send(SmsMessage $message): void
    {
        $this->logger->info('SMS sent.', [
            'to' => $message->to(),
            'body' => $message->body(),
            'metadata' => $message->metadata(),
        ]);
    }
}
