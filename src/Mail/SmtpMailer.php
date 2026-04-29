<?php

declare(strict_types=1);

namespace Wayfinder\Mail;

use RuntimeException;

final class SmtpMailer implements Mailer
{
    /** @var callable(string, float): mixed|null */
    private mixed $streamFactory;

    public function __construct(
        private readonly string $host,
        private readonly int $port = 25,
        private readonly ?string $username = null,
        private readonly ?string $password = null,
        private readonly ?string $encryption = null,
        private readonly string $fromAddress = 'no-reply@example.com',
        private readonly ?string $fromName = null,
        private readonly float $timeout = 10.0,
        ?callable $streamFactory = null,
    ) {
        $this->streamFactory = $streamFactory;
    }

    public function send(MailMessage $message): void
    {
        $stream = $this->connect();

        try {
            $this->expectResponse($stream, [220]);
            $this->ehlo($stream);

            if ($this->normalizedEncryption() === 'tls') {
                $this->startTls($stream);
                $this->ehlo($stream);
            }

            if ($this->username !== null) {
                $this->authenticate($stream);
            }

            $this->command($stream, sprintf('MAIL FROM:<%s>', $this->sanitizeAddress($this->fromAddress)), [250]);
            $this->command($stream, sprintf('RCPT TO:<%s>', $this->sanitizeAddress($message->to())), [250, 251]);
            $this->command($stream, 'DATA', [354]);
            $this->write($stream, $this->dotStuff($this->buildMimeMessage($message)) . "\r\n.\r\n");
            $this->expectResponse($stream, [250]);
            $this->command($stream, 'QUIT', [221]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function connect()
    {
        $host = $this->host;
        $encryption = $this->normalizedEncryption();

        if ($encryption === 'ssl') {
            $host = 'ssl://' . $host;
        }

        $factory = $this->streamFactory ?? static fn (string $remote, float $timeout) => @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            $timeout,
        );

        $stream = $factory(sprintf('%s:%d', $host, $this->port), $this->timeout);

        if (! is_resource($stream)) {
            throw new RuntimeException(sprintf('Unable to connect to SMTP server [%s:%d].', $this->host, $this->port));
        }

        stream_set_timeout($stream, (int) ceil($this->timeout));

        return $stream;
    }

    private function ehlo($stream): void
    {
        $heloHost = gethostname();
        if (! is_string($heloHost) || trim($heloHost) === '') {
            $heloHost = 'localhost';
        }

        $this->command($stream, 'EHLO ' . $heloHost, [250]);
    }

    private function startTls($stream): void
    {
        $this->command($stream, 'STARTTLS', [220]);

        $enabled = @stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

        if ($enabled !== true) {
            throw new RuntimeException('Unable to enable TLS for SMTP connection.');
        }
    }

    private function authenticate($stream): void
    {
        $this->command($stream, 'AUTH LOGIN', [334]);
        $this->command($stream, base64_encode($this->username ?? ''), [334]);
        $this->command($stream, base64_encode($this->password ?? ''), [235]);
    }

    private function buildMimeMessage(MailMessage $message): string
    {
        $headers = [
            'Date: ' . date('r'),
            'From: ' . $this->formatAddress($this->fromAddress, $this->fromName),
            'To: ' . $this->formatAddress($message->to(), null),
            'Subject: ' . $this->encodeHeader($message->subject()),
            'MIME-Version: 1.0',
            'Message-ID: ' . sprintf('<%s@%s>', bin2hex(random_bytes(8)), preg_replace('/[^A-Za-z0-9.-]/', '', $this->host) ?: 'localhost'),
        ];

        if ($message->html() === null) {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: 8bit';

            return implode("\r\n", $headers) . "\r\n\r\n" . $this->normalizeBody($message->text());
        }

        $boundary = 'wayfinder_' . bin2hex(random_bytes(8));

        $headers[] = sprintf('Content-Type: multipart/alternative; boundary="%s"', $boundary);

        $parts = [
            '--' . $boundary,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            $this->normalizeBody($message->text()),
            '--' . $boundary,
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            $this->normalizeBody($message->html()),
            '--' . $boundary . '--',
            '',
        ];

        return implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $parts);
    }

    private function command($stream, string $command, array $expectedCodes): void
    {
        $this->write($stream, $command . "\r\n");
        $this->expectResponse($stream, $expectedCodes);
    }

    /**
     * @param list<int> $expectedCodes
     */
    private function expectResponse($stream, array $expectedCodes): void
    {
        $response = $this->readResponse($stream);
        $code = (int) substr($response, 0, 3);

        if (! in_array($code, $expectedCodes, true)) {
            throw new RuntimeException(sprintf('Unexpected SMTP response [%s].', trim($response)));
        }
    }

    private function readResponse($stream): string
    {
        $response = '';

        while (($line = fgets($stream)) !== false) {
            $response .= $line;

            if (preg_match('/^\d{3} /', $line) === 1) {
                break;
            }
        }

        if ($response === '') {
            throw new RuntimeException('SMTP server did not return a response.');
        }

        return $response;
    }

    private function write($stream, string $payload): void
    {
        $written = fwrite($stream, $payload);

        if ($written === false || $written < strlen($payload)) {
            throw new RuntimeException('Unable to write to SMTP connection.');
        }
    }

    private function formatAddress(string $address, ?string $name): string
    {
        $address = $this->sanitizeAddress($address);

        if ($name === null || trim($name) === '') {
            return sprintf('<%s>', $address);
        }

        $escaped = addcslashes($name, "\"\\");

        return sprintf('"%s" <%s>', $escaped, $address);
    }

    private function sanitizeAddress(string $address): string
    {
        if (str_contains($address, "\r") || str_contains($address, "\n")) {
            throw new RuntimeException('Mail addresses must not contain newlines.');
        }

        return trim($address);
    }

    private function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value) !== 1) {
            return $value;
        }

        return sprintf('=?UTF-8?B?%s?=', base64_encode($value));
    }

    private function normalizeBody(?string $body): string
    {
        return str_replace(["\r\n", "\r"], "\n", (string) $body);
    }

    private function dotStuff(string $payload): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $payload);
        $lines = explode("\n", $normalized);

        $lines = array_map(static fn (string $line): string => str_starts_with($line, '.') ? '.' . $line : $line, $lines);

        return implode("\r\n", $lines);
    }

    private function normalizedEncryption(): ?string
    {
        if ($this->encryption === null) {
            return null;
        }

        $normalized = strtolower(trim($this->encryption));

        return $normalized === '' ? null : $normalized;
    }
}
