<?php

declare(strict_types=1);

namespace Wayfinder\Tests\Mail;

use PHPUnit\Framework\TestCase;
use Wayfinder\Mail\FileMailer;
use Wayfinder\Mail\MailFactory;
use Wayfinder\Mail\NullMailer;
use Wayfinder\Mail\SmtpMailer;

final class MailFactoryTest extends TestCase
{
    public function testCreatesFileMailer(): void
    {
        $mailer = (new MailFactory())->make([
            'driver' => 'file',
            'path' => '/tmp/mails',
        ]);

        self::assertInstanceOf(FileMailer::class, $mailer);
    }

    public function testCreatesSmtpMailer(): void
    {
        $mailer = (new MailFactory())->make([
            'driver' => 'smtp',
            'host' => '127.0.0.1',
            'port' => 1025,
            'username' => '',
            'password' => '',
            'encryption' => '',
            'from' => [
                'address' => 'no-reply@example.com',
                'name' => 'Stackmint',
            ],
        ]);

        self::assertInstanceOf(SmtpMailer::class, $mailer);
    }

    public function testFallsBackToNullMailerForUnknownDriver(): void
    {
        $mailer = (new MailFactory())->make([
            'driver' => 'unknown',
        ]);

        self::assertInstanceOf(NullMailer::class, $mailer);
    }
}
