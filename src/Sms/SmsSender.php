<?php

declare(strict_types=1);

namespace Wayfinder\Sms;

interface SmsSender
{
    public function send(SmsMessage $message): void;
}
