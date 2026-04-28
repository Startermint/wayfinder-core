<?php

declare(strict_types=1);

namespace Wayfinder\Http\Client;

final class Request
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly string $method,
        private readonly string $url,
        private readonly array $headers = [],
        private readonly string $body = '',
    ) {
    }

    /**
     * @param array<string, string> $headers
     */
    public static function make(string $method, string $url, array $headers = [], string $body = ''): self
    {
        return new self(strtoupper($method), $url, $headers, $body);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function get(string $url, array $headers = []): self
    {
        return self::make('GET', $url, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function post(string $url, string $body = '', array $headers = []): self
    {
        return self::make('POST', $url, $headers, $body);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function url(): string
    {
        return $this->url;
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

    public function withHeader(string $name, string $value): self
    {
        $headers = $this->headers;
        $headers[$name] = $value;

        return new self($this->method, $this->url, $headers, $this->body);
    }

    /**
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        return new self($this->method, $this->url, [...$this->headers, ...$headers], $this->body);
    }

    public function withBody(string $body): self
    {
        return new self($this->method, $this->url, $this->headers, $body);
    }
}
