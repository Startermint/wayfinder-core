<?php

declare(strict_types=1);

namespace Wayfinder\Transport;

use Wayfinder\Mail\MailMessage;
use Wayfinder\Mail\Mailer;
use Wayfinder\Scenario\EventRecorder;

final class FakeMailer implements Mailer
{
    /**
     * @var list<MailMessage>
     */
    private array $sent = [];

    public function __construct(
        private readonly ?EventRecorder $events = null,
    ) {
    }

    public function send(MailMessage $message): void
    {
        $this->sent[] = $message;
        $this->events?->record('mail.sent', [
            'to' => $message->to(),
            'subject' => $message->subject(),
        ]);
    }

    /**
     * @return list<MailMessage>
     */
    public function sent(): array
    {
        return $this->sent;
    }
}
