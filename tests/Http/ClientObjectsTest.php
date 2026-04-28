<?php

declare(strict_types=1);

namespace Wayfinder\Tests\Http;

use PHPUnit\Framework\TestCase;
use Wayfinder\Http\Client\Exception\HttpClientException;
use Wayfinder\Http\Client\Request;
use Wayfinder\Http\Client\Response;

final class ClientObjectsTest extends TestCase
{
    public function testRequestNormalizesFactoryMethodAndPreservesValues(): void
    {
        $request = Request::make('post', 'https://api.example.test/users', [
            'Accept' => 'application/json',
        ], '{"name":"Ron"}');

        self::assertSame('POST', $request->method());
        self::assertSame('https://api.example.test/users', $request->url());
        self::assertSame('application/json', $request->header('accept'));
        self::assertSame('{"name":"Ron"}', $request->body());
    }

    public function testRequestCanBeClonedWithAdditionalHeadersAndBody(): void
    {
        $request = Request::get('https://api.example.test/users')
            ->withHeader('Authorization', 'Bearer token')
            ->withBody('payload');

        self::assertSame('Bearer token', $request->header('authorization'));
        self::assertSame('payload', $request->body());
    }

    public function testResponseJsonFactoryAndDecoderRoundTrip(): void
    {
        $response = Response::json(200, ['ok' => true, 'id' => 7]);

        self::assertTrue($response->successful());
        self::assertSame('application/json', $response->header('content-type'));
        self::assertSame(['ok' => true, 'id' => 7], $response->decodeJson());
    }

    public function testResponseJsonThrowsForInvalidPayload(): void
    {
        $response = Response::make(200, ['Content-Type' => 'application/json'], 'not-json');

        $this->expectException(HttpClientException::class);
        $this->expectExceptionMessage('Response body does not contain a JSON object or array.');

        $response->decodeJson();
    }
}
