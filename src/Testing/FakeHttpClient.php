<?php

declare(strict_types=1);

namespace Wayfinder\Testing;

use Throwable;
use Wayfinder\Contracts\HttpClient;
use Wayfinder\Http\Client\Exception\HttpClientException;
use Wayfinder\Http\Client\Request;
use Wayfinder\Http\Client\Response;

final class FakeHttpClient implements HttpClient
{
    /**
     * @var list<Request>
     */
    private array $sent = [];

    /**
     * @var list<Response|Throwable>
     */
    private array $queue = [];

    /**
     * @param list<Response|Throwable> $responses
     */
    public function __construct(array $responses = [])
    {
        $this->queue = array_values($responses);
    }

    public function send(Request $request): Response
    {
        $this->sent[] = $request;
        $next = array_shift($this->queue);

        if ($next instanceof Throwable) {
            throw $next;
        }

        if (! $next instanceof Response) {
            throw new HttpClientException('No fake HTTP response has been queued.');
        }

        return $next;
    }

    public function push(Response|Throwable $response): self
    {
        $this->queue[] = $response;

        return $this;
    }

    /**
     * @param list<Response|Throwable> $responses
     */
    public function pushMany(array $responses): self
    {
        foreach ($responses as $response) {
            $this->queue[] = $response;
        }

        return $this;
    }

    /**
     * @return list<Request>
     */
    public function sent(): array
    {
        return $this->sent;
    }

    public function lastRequest(): ?Request
    {
        if ($this->sent === []) {
            return null;
        }

        return $this->sent[array_key_last($this->sent)];
    }

    public function assertSentCount(int $expected): void
    {
        $actual = count($this->sent);

        if ($actual !== $expected) {
            throw new HttpClientException(sprintf(
                'Expected %d HTTP request(s) to be sent, but saw %d.',
                $expected,
                $actual,
            ));
        }
    }
}
