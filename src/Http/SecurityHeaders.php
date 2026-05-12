<?php

declare(strict_types=1);

namespace Wayfinder\Http;

use Wayfinder\Contracts\Middleware;

final class SecurityHeaders implements Middleware
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly array $headers = [],
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        foreach ($this->defaults() as $name => $value) {
            if (! $this->hasHeader($response, $name)) {
                $response = $response->header($name, $value);
            }
        }

        return $response;
    }

    /**
     * @return array<string, string>
     */
    private function defaults(): array
    {
        return [
            'X-Content-Type-Options' => $this->headers['X-Content-Type-Options'] ?? 'nosniff',
            'X-Frame-Options' => $this->headers['X-Frame-Options'] ?? 'SAMEORIGIN',
            'Referrer-Policy' => $this->headers['Referrer-Policy'] ?? 'strict-origin-when-cross-origin',
            'Permissions-Policy' => $this->headers['Permissions-Policy'] ?? 'geolocation=(), microphone=(), camera=()',
        ];
    }

    private function hasHeader(Response $response, string $name): bool
    {
        foreach ($response->headers() as $header => $_value) {
            if (strcasecmp($header, $name) === 0) {
                return true;
            }
        }

        return false;
    }
}
