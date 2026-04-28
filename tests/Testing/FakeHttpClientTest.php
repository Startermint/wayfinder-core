<?php

declare(strict_types=1);

namespace Wayfinder\Tests\Testing;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Wayfinder\Http\Client\Exception\HttpClientException;
use Wayfinder\Http\Client\Request;
use Wayfinder\Http\Client\Response;
use Wayfinder\Testing\FakeHttpClient;

final class FakeHttpClientTest extends TestCase
{
    public function testSendReturnsQueuedResponsesAndCapturesRequests(): void
    {
        $client = new FakeHttpClient([
            Response::json(200, ['page' => 1]),
            Response::make(202, [], 'accepted'),
        ]);

        $first = $client->send(Request::get('https://api.example.test/users'));
        $second = $client->send(Request::post('https://api.example.test/users', '{"name":"Ron"}'));

        self::assertSame(['page' => 1], $first->decodeJson());
        self::assertSame(202, $second->status());
        self::assertCount(2, $client->sent());
        self::assertSame('POST', $client->lastRequest()?->method());
    }

    public function testSendThrowsQueuedThrowable(): void
    {
        $client = new FakeHttpClient([
            new RuntimeException('API unavailable'),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('API unavailable');

        $client->send(Request::get('https://api.example.test/status'));
    }

    public function testSendThrowsClearErrorWhenNothingQueued(): void
    {
        $client = new FakeHttpClient();

        $this->expectException(HttpClientException::class);
        $this->expectExceptionMessage('No fake HTTP response has been queued.');

        $client->send(Request::get('https://api.example.test/status'));
    }

    public function testAssertSentCountThrowsWhenExpectationDoesNotMatch(): void
    {
        $client = new FakeHttpClient([Response::make(200)]);
        $client->send(Request::get('https://api.example.test/users'));

        $this->expectException(HttpClientException::class);
        $this->expectExceptionMessage('Expected 2 HTTP request(s) to be sent, but saw 1.');

        $client->assertSentCount(2);
    }
}
