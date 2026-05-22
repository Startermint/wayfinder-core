<?php

declare(strict_types=1);

namespace Wayfinder\Transport;

use Wayfinder\Mail\Mailer;

final class MailerAssertions
{
    public function __construct(
        private readonly Mailer $mailer,
    ) {
    }

    public function sentCount(int $expected): void
    {
        $actual = count($this->fake()->sent());

        if ($actual !== $expected) {
            throw new \RuntimeException(sprintf('Expected %d mail message(s), saw %d.', $expected, $actual));
        }
    }

    public function sentTo(string $recipient): void
    {
        foreach ($this->fake()->sent() as $message) {
            if ($message->to() === $recipient) {
                return;
            }
        }

        throw new \RuntimeException(sprintf('Expected mail to be sent to [%s].', $recipient));
    }

    private function fake(): FakeMailer
    {
        if (! $this->mailer instanceof FakeMailer) {
            throw new \RuntimeException('Mailer assertions require FakeMailer.');
        }

        return $this->mailer;
    }
}
