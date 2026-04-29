<?php

declare(strict_types=1);

namespace Wayfinder\Tests\Mail;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Wayfinder\Mail\MailMessage;
use Wayfinder\Mail\SmtpMailer;

final class SmtpMailerTest extends TestCase
{
    public function testSendWritesExpectedSmtpConversationForPlainTextMail(): void
    {
        [$client, $server] = $this->socketPair();

        fwrite($server, implode("\r\n", [
            '220 mail.test ESMTP ready',
            '250-mail.test',
            '250 AUTH LOGIN PLAIN',
            '250 Sender OK',
            '250 Recipient OK',
            '354 End data with <CR><LF>.<CR><LF>',
            '250 Queued',
            '221 Bye',
            '',
        ]));

        $mailer = new SmtpMailer(
            host: '127.0.0.1',
            port: 1025,
            encryption: null,
            fromAddress: 'no-reply@example.com',
            fromName: 'Stackmint',
            streamFactory: static fn (): mixed => $client,
        );

        $mailer->send(new MailMessage('user@example.com', 'Hello', 'Plain body'));

        $conversation = stream_get_contents($server);

        self::assertStringContainsString("EHLO ", $conversation);
        self::assertStringContainsString("MAIL FROM:<no-reply@example.com>\r\n", $conversation);
        self::assertStringContainsString("RCPT TO:<user@example.com>\r\n", $conversation);
        self::assertStringContainsString("DATA\r\n", $conversation);
        self::assertStringContainsString("From: \"Stackmint\" <no-reply@example.com>\r\n", $conversation);
        self::assertStringContainsString("To: <user@example.com>\r\n", $conversation);
        self::assertStringContainsString("Subject: Hello\r\n", $conversation);
        self::assertStringContainsString("Plain body\r\n.\r\n", $conversation);
    }

    public function testSendWritesMultipartMailWhenHtmlIsPresent(): void
    {
        [$client, $server] = $this->socketPair();

        fwrite($server, implode("\r\n", [
            '220 mail.test ESMTP ready',
            '250-mail.test',
            '250 AUTH LOGIN PLAIN',
            '250 Sender OK',
            '250 Recipient OK',
            '354 End data with <CR><LF>.<CR><LF>',
            '250 Queued',
            '221 Bye',
            '',
        ]));

        $mailer = new SmtpMailer(
            host: '127.0.0.1',
            port: 1025,
            fromAddress: 'no-reply@example.com',
            streamFactory: static fn (): mixed => $client,
        );

        $mailer->send(new MailMessage('user@example.com', 'Hello', 'Plain body', '<p>Hello</p>'));

        $conversation = stream_get_contents($server);

        self::assertStringContainsString('Content-Type: multipart/alternative;', $conversation);
        self::assertStringContainsString('Content-Type: text/plain; charset=UTF-8', $conversation);
        self::assertStringContainsString('Content-Type: text/html; charset=UTF-8', $conversation);
        self::assertStringContainsString('<p>Hello</p>', $conversation);
    }

    public function testSendSupportsAuthLogin(): void
    {
        [$client, $server] = $this->socketPair();

        fwrite($server, implode("\r\n", [
            '220 mail.test ESMTP ready',
            '250-mail.test',
            '250 AUTH LOGIN PLAIN',
            '334 VXNlcm5hbWU6',
            '334 UGFzc3dvcmQ6',
            '235 Authenticated',
            '250 Sender OK',
            '250 Recipient OK',
            '354 End data with <CR><LF>.<CR><LF>',
            '250 Queued',
            '221 Bye',
            '',
        ]));

        $mailer = new SmtpMailer(
            host: '127.0.0.1',
            port: 1025,
            username: 'mailer',
            password: 'secret',
            fromAddress: 'no-reply@example.com',
            streamFactory: static fn (): mixed => $client,
        );

        $mailer->send(new MailMessage('user@example.com', 'Hello', 'Plain body'));

        $conversation = stream_get_contents($server);

        self::assertStringContainsString("AUTH LOGIN\r\n", $conversation);
        self::assertStringContainsString(base64_encode('mailer') . "\r\n", $conversation);
        self::assertStringContainsString(base64_encode('secret') . "\r\n", $conversation);
    }

    public function testSendThrowsWhenSmtpServerRejectsCommand(): void
    {
        [$client, $server] = $this->socketPair();

        fwrite($server, implode("\r\n", [
            '220 mail.test ESMTP ready',
            '550 nope',
            '',
        ]));

        $mailer = new SmtpMailer(
            host: '127.0.0.1',
            port: 1025,
            fromAddress: 'no-reply@example.com',
            streamFactory: static fn (): mixed => $client,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unexpected SMTP response [550 nope].');

        $mailer->send(new MailMessage('user@example.com', 'Hello', 'Plain body'));
    }

    /**
     * @return array{0: resource, 1: resource}
     */
    private function socketPair(): array
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        if ($pair === false) {
            self::fail('Unable to create stream socket pair for SMTP test.');
        }

        return $pair;
    }
}
