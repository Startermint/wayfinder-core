<?php

declare(strict_types=1);

namespace Wayfinder\Tests\Http;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Wayfinder\Http\Cookie;
use Wayfinder\Http\Request;
use Wayfinder\Http\Response;

final class HttpFoundationIntegrationTest extends TestCase
{
    public function testRequestCanBeCreatedFromSymfonyRequest(): void
    {
        $symfony = SymfonyRequest::create(
            '/contacts?source=ad',
            'POST',
            [],
            ['visitor' => 'abc'],
            [],
            ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
            '{"name":"Ron"}',
        );

        $request = Request::fromSymfony($symfony);

        self::assertSame('POST', $request->method());
        self::assertSame('/contacts', $request->path());
        self::assertSame('ad', $request->input('source'));
        self::assertSame('Ron', $request->input('name'));
        self::assertSame('abc', $request->cookie('visitor'));
        self::assertTrue($request->expectsJson());
        self::assertSame($symfony, $request->toSymfonyRequest());
    }

    public function testFormPayloadTakesPrecedenceOverJsonBody(): void
    {
        $symfony = SymfonyRequest::create(
            '/contacts',
            'POST',
            ['name' => 'Form Name'],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"name":"Json Name"}',
        );

        $request = Request::fromSymfony($symfony);

        self::assertSame('Form Name', $request->input('name'));
    }

    public function testManualRequestBuildsSymfonyRequestWithQueryAndHeaders(): void
    {
        $request = new Request(
            method: 'POST',
            path: '/contacts',
            query: ['source' => 'ad'],
            request: ['name' => 'Ron'],
            cookies: ['visitor' => 'abc'],
            files: [],
            server: [],
            headers: ['x-requested-with' => 'XMLHttpRequest'],
            body: '',
        );

        $symfony = $request->toSymfonyRequest();

        self::assertSame('ad', $symfony->query->get('source'));
        self::assertSame('Ron', $symfony->request->get('name'));
        self::assertSame('abc', $symfony->cookies->get('visitor'));
        self::assertSame('XMLHttpRequest', $symfony->headers->get('x-requested-with'));
        self::assertTrue($request->expectsJson());
    }

    public function testRouteParamsAreMirroredToSymfonyAttributes(): void
    {
        $request = (new Request(
            method: 'GET',
            path: '/users/42',
            query: [],
            request: [],
            cookies: [],
            files: [],
            server: [],
            headers: [],
            body: '',
        ))->withRouteParams(['id' => '42']);

        self::assertSame('42', $request->route('id'));
        self::assertSame('42', $request->toSymfonyRequest()->attributes->get('id'));
    }

    public function testSymfonyAttributesBecomeRouteParams(): void
    {
        $symfony = SymfonyRequest::create('/users/42');
        $symfony->attributes->set('id', 42);

        $request = Request::fromSymfony($symfony);

        self::assertSame('42', $request->route('id'));
    }

    public function testWayfinderResponseCanBeConvertedToSymfonyResponse(): void
    {
        $response = Response::json(['ok' => true], 201, ['X-Test' => 'yes'])
            ->withCookie(Cookie::make('session', 'abc', secure: true, httpOnly: true, sameSite: 'Strict'));

        $symfony = $response->toSymfonyResponse();
        $cookie = $symfony->headers->getCookies()[0] ?? null;

        self::assertInstanceOf(SymfonyResponse::class, $symfony);
        self::assertSame(201, $symfony->getStatusCode());
        self::assertSame('yes', $symfony->headers->get('X-Test'));
        self::assertSame('application/json; charset=utf-8', $symfony->headers->get('Content-Type'));
        self::assertStringContainsString('"ok": true', (string) $symfony->getContent());
        self::assertNotNull($cookie);
        self::assertSame('session', $cookie->getName());
        self::assertTrue($cookie->isSecure());
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame('strict', strtolower((string) $cookie->getSameSite()));
    }

    public function testStreamedResponseCanBeConvertedToSymfonyResponse(): void
    {
        $response = Response::stream(static function (): void {
            echo 'chunk';
        }, headers: ['X-Stream' => 'yes']);

        $symfony = $response->toSymfonyResponse();

        self::assertSame(200, $symfony->getStatusCode());
        self::assertSame('yes', $symfony->headers->get('X-Stream'));
    }

    public function testSymfonyUploadedFilesUseWayfinderMetadataShape(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'wf-upload-');
        self::assertIsString($path);
        file_put_contents($path, 'avatar');

        $symfony = SymfonyRequest::create('/profile', 'POST', files: [
            'avatar' => new UploadedFile($path, 'avatar.png', 'image/png', UPLOAD_ERR_OK, true),
        ]);

        $request = Request::fromSymfony($symfony);
        $files = $request->files();

        self::assertIsArray($files['avatar'] ?? null);
        self::assertSame('avatar.png', $files['avatar']['name'] ?? null);
        self::assertSame('image/png', $files['avatar']['type'] ?? null);
        self::assertSame(UPLOAD_ERR_OK, $files['avatar']['error'] ?? null);
        self::assertSame(6, $files['avatar']['size'] ?? null);
    }
}
