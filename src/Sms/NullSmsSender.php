<?php

declare(strict_types=1);

namespace Wayfinder\Sms;

final class NullSmsSender implements SmsSender
{
    public function send(SmsMessage $message): void
    {
    }
}
