<?php

declare(strict_types=1);

namespace Wayfinder\Tests\Routing;

use PHPUnit\Framework\TestCase;
use Wayfinder\Http\Request;
use Wayfinder\Routing\UrlGenerator;

final class UrlGeneratorTest extends TestCase
{
    public function test_to_generates_fully_qualified_url(): void
    {
        $url = new UrlGenerator('http://example.test');

        self::assertSame('http://example.test/user/profile', $url->to('user/profile'));
    }

    public function test_to_appends_positional_parameters(): void
    {
        $url = new UrlGenerator('http://example.test');

        self::assertSame('http://example.test/user/profile/1', $url->to('user/profile', [1]));
    }

    public function test_to_replaces_placeholders(): void
    {
        $url = new UrlGenerator('http://example.test');

        self::assertSame('http://example.test/users/7/profile', $url->to('users/{id}/profile', ['id' => 7]));
    }

    public function test_secure_forces_https(): void
    {
        $url = new UrlGenerator('http://example.test');

        self::assertSame('https://example.test/account', $url->secure('account'));
    }

    public function test_asset_generates_fully_qualified_asset_url(): void
    {
        $url = new UrlGenerator('http://example.test');

        self::assertSame('http://example.test/img/photo.jpg', $url->asset('img/photo.jpg'));
    }

    public function test_current_and_full_use_active_request(): void
    {
        $url = new UrlGenerator('http://example.test');
        $url->setRequest(new Request(
            method: 'GET',
            path: '/search',
            query: ['q' => 'stackmint'],
            request: [],
            cookies: [],
            files: [],
            server: ['REQUEST_URI' => '/search?q=stackmint'],
            headers: [],
            body: '',
        ));

        self::assertSame('http://example.test/search', $url->current());
        self::assertSame('http://example.test/search?q=stackmint', $url->full());
    }

    public function test_previous_uses_referer_header_or_fallback(): void
    {
        $url = new UrlGenerator('http://example.test');

        self::assertSame('http://example.test', $url->previous());

        $url->setRequest(new Request(
            method: 'GET',
            path: '/account',
            query: [],
            request: [],
            cookies: [],
            files: [],
            server: [],
            headers: ['referer' => 'http://example.test/dashboard'],
            body: '',
        ));

        self::assertSame('http://example.test/dashboard', $url->previous());
    }
}
