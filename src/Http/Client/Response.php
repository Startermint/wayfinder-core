<?php

declare(strict_types=1);

namespace Wayfinder\Http\Client;

use Wayfinder\Http\Client\Exception\HttpClientException;

final class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly int $status,
        private readonly array $headers = [],
        private readonly string $body = '',
    ) {
    }

    /**
     * @param array<string, string> $headers
     */
    public static function make(int $status, array $headers = [], string $body = ''): self
    {
        return new self($status, $headers, $body);
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $data
     */
    public static function json(int $status, array $data, array $headers = []): self
    {
        $body = json_encode($data, JSON_UNESCAPED_SLASHES);

        if ($body === false) {
            throw new HttpClientException('Unable to encode JSON HTTP client response.');
        }

        return new self($status, ['Content-Type' => 'application/json', ...$headers], $body);
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $normalized = strtolower($name);

        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === $normalized) {
                return $value;
            }
        }

        return $default;
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeJson(): array
    {
        $decoded = json_decode($this->body, true);

        if (! is_array($decoded)) {
            throw new HttpClientException('Response body does not contain a JSON object or array.');
        }

        return $decoded;
    }

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }
}
