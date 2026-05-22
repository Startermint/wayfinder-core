<?php

declare(strict_types=1);

namespace Wayfinder\Transport;

use Wayfinder\Sms\SmsSender;

final class SmsAssertions
{
    public function __construct(
        private readonly SmsSender $sms,
    ) {
    }

    public function sentCount(int $expected): void
    {
        $actual = count($this->fake()->sent());

        if ($actual !== $expected) {
            throw new \RuntimeException(sprintf('Expected %d SMS message(s), saw %d.', $expected, $actual));
        }
    }

    public function sentTo(string $recipient): void
    {
        foreach ($this->fake()->sent() as $message) {
            if ($message->to() === $recipient) {
                return;
            }
        }

        throw new \RuntimeException(sprintf('Expected SMS to be sent to [%s].', $recipient));
    }

    private function fake(): FakeSmsSender
    {
        if (! $this->sms instanceof FakeSmsSender) {
            throw new \RuntimeException('SMS assertions require FakeSmsSender.');
        }

        return $this->sms;
    }
}
