<?php

declare(strict_types=1);

namespace Wayfinder\Mail;

final class MailFactory
{
    /**
     * @param array<string, mixed> $config
     */
    public function make(array $config): Mailer
    {
        return match ($config['driver'] ?? 'null') {
            'file' => new FileMailer((string) ($config['path'] ?? sys_get_temp_dir() . '/wayfinder-mail')),
            'smtp' => new SmtpMailer(
                host: (string) ($config['host'] ?? '127.0.0.1'),
                port: (int) ($config['port'] ?? 25),
                username: $this->nullableString($config['username'] ?? null),
                password: $this->nullableString($config['password'] ?? null),
                encryption: $this->nullableString($config['encryption'] ?? null),
                fromAddress: (string) (($config['from']['address'] ?? null) ?: 'no-reply@example.com'),
                fromName: $this->nullableString($config['from']['name'] ?? null),
                timeout: (float) ($config['timeout'] ?? 10.0),
            ),
            default => new NullMailer(),
        };
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
