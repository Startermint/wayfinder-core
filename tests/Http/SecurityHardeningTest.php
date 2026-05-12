<?php

declare(strict_types=1);

namespace Wayfinder\Tests\Http;

use PHPUnit\Framework\TestCase;
use Wayfinder\Http\Cookie;
use Wayfinder\Http\Request;
use Wayfinder\Http\Response;
use Wayfinder\Http\SecurityHeaders;
use Wayfinder\Http\ValidateHost;
use Wayfinder\Tests\Concerns\MakesRequests;

final class SecurityHardeningTest extends TestCase
{
    use MakesRequests;

    public function testSecurityHeadersAddsDefaultBrowserProtections(): void
    {
        $response = (new SecurityHeaders())->handle(
            $this->makeRequest(),
            static fn (Request $request): Response => Response::html('OK'),
        );

        self::assertSame('nosniff', $response->headers()['X-Content-Type-Options'] ?? null);
        self::assertSame('SAMEORIGIN', $response->headers()['X-Frame-Options'] ?? null);
        self::assertSame('strict-origin-when-cross-origin', $response->headers()['Referrer-Policy'] ?? null);
        self::assertSame('geolocation=(), microphone=(), camera=()', $response->headers()['Permissions-Policy'] ?? null);
    }

    public function testSecurityHeadersDoesNotOverwriteExplicitHeaders(): void
    {
        $response = (new SecurityHeaders())->handle(
            $this->makeRequest(),
            static fn (Request $request): Response => Response::html('OK')->header('X-Frame-Options', 'DENY'),
        );

        self::assertSame('DENY', $response->headers()['X-Frame-Options'] ?? null);
    }

    public function testValidateHostAllowsConfiguredHostAndStripsPort(): void
    {
        $response = (new ValidateHost(['app.example.com']))->handle(
            $this->makeRequest(headers: ['Host' => 'app.example.com:8443']),
            static fn (Request $request): Response => Response::text('OK'),
        );

        self::assertSame(200, $response->status());
    }

    public function testValidateHostAllowsWildcardSubdomain(): void
    {
        $response = (new ValidateHost(['*.example.com']))->handle(
            $this->makeRequest(headers: ['Host' => 'tenant.example.com']),
            static fn (Request $request): Response => Response::text('OK'),
        );

        self::assertSame(200, $response->status());
    }

    public function testValidateHostRejectsUnexpectedHost(): void
    {
        $response = (new ValidateHost(['app.example.com']))->handle(
            $this->makeRequest(headers: ['Host' => 'evil.example.net']),
            static fn (Request $request): Response => Response::text('OK'),
        );

        self::assertSame(400, $response->status());
    }

    public function testRequestHeadersAreNormalizedWhenConstructedDirectly(): void
    {
        $request = $this->makeRequest(headers: ['X-Requested-With' => 'XMLHttpRequest']);

        self::assertSame('XMLHttpRequest', $request->header('x-requested-with'));
        self::assertTrue($request->expectsJson());
    }

    public function testSameSiteNoneCookieRequiresSecureFlag(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cookies using SameSite=None must also be Secure.');

        Cookie::make('session', 'value', sameSite: 'None');
    }

    public function testCookieSameSiteValueIsNormalized(): void
    {
        $cookie = Cookie::make('session', 'value', secure: true, sameSite: 'None');

        self::assertSame('None', $cookie->options()['samesite']);
    }
}
