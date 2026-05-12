<?php

declare(strict_types=1);

namespace Wayfinder\Http;

use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Wayfinder\Session\Session;

final class Response
{
    /**
     * @var string|\Closure(): void
     */
    private readonly string|\Closure $content;

    /**
     * @param array<string, string> $headers
     * @param list<Cookie> $cookies
     * @param string|\Closure(): void $content
     */
    public function __construct(
        string|\Closure $content,
        private readonly int $status = 200,
        private readonly array $headers = [],
        private readonly array $cookies = [],
    ) {
        $this->content = $content;
    }

    /**
     * @param array<string, string> $headers
     */
    public static function make(string $content, int $status = 200, array $headers = []): self
    {
        return new self($content, $status, $headers);
    }

    /**
     * @param \Closure(): void $callback
     * @param array<string, string> $headers
     */
    public static function stream(\Closure $callback, int $status = 200, array $headers = []): self
    {
        return new self($callback, $status, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function text(string $content, int $status = 200, array $headers = []): self
    {
        return new self($content, $status, ['Content-Type' => 'text/plain; charset=utf-8', ...$headers]);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function html(string $content, int $status = 200, array $headers = []): self
    {
        return new self($content, $status, ['Content-Type' => 'text/html; charset=utf-8', ...$headers]);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function json(array $data, int $status = 200, array $headers = []): self
    {
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            throw new \RuntimeException('Unable to encode JSON response.');
        }

        return new self($encoded, $status, ['Content-Type' => 'application/json; charset=utf-8', ...$headers]);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function redirect(string $location, int $status = 302, array $headers = []): self
    {
        return new self('', $status, ['Location' => $location, ...$headers]);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function back(Request $request, int $status = 302, array $headers = []): self
    {
        return self::redirect($request->redirectTarget(), $status, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function notFound(string $content = 'Not Found', array $headers = []): self
    {
        return self::text($content, 404, $headers);
    }

    public function content(): string
    {
        if (is_string($this->content)) {
            return $this->content;
        }

        ob_start();
        ($this->content)();

        return (string) ob_get_clean();
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

    /**
     * @return list<Cookie>
     */
    public function cookies(): array
    {
        return $this->cookies;
    }

    public function header(string $name, string $value): self
    {
        return new self(
            $this->content,
            $this->status,
            [...$this->headers, $name => $value],
            $this->cookies,
        );
    }

    public function withCookie(Cookie $cookie): self
    {
        return new self(
            $this->content,
            $this->status,
            $this->headers,
            [...$this->cookies, $cookie],
        );
    }

    public function withFlash(Session $session, string $key, mixed $value): self
    {
        $session->flash($key, $value);

        return $this;
    }

    public function toSymfonyResponse(): SymfonyResponse
    {
        $response = is_string($this->content)
            ? new SymfonyResponse($this->content, $this->status, $this->headers)
            : new StreamedResponse($this->content, $this->status, $this->headers);

        foreach ($this->cookies as $cookie) {
            $response->headers->setCookie(new SymfonyCookie(
                $cookie->name(),
                $cookie->value(),
                $cookie->expires() > 0 ? $cookie->expires() : 0,
                $cookie->options()['path'],
                $cookie->options()['domain'] !== '' ? $cookie->options()['domain'] : null,
                $cookie->options()['secure'],
                $cookie->options()['httponly'],
                false,
                $cookie->options()['samesite'],
            ));
        }

        return $response;
    }

    public function symfony(): SymfonyResponse
    {
        return $this->toSymfonyResponse();
    }

    public function send(): void
    {
        $this->toSymfonyResponse()->send();
    }
}
