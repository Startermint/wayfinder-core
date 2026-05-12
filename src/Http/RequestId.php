<?php

declare(strict_types=1);

namespace Wayfinder\Http;

use Wayfinder\Contracts\Middleware;

final class RequestId implements Middleware
{
    public function __construct(
        private readonly string $header = 'X-Request-Id',
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $requestId = $this->requestId($request);

        return $next($request)->header($this->header, $requestId);
    }

    private function requestId(Request $request): string
    {
        $incoming = $request->header($this->header);

        if (is_string($incoming) && preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $incoming) === 1) {
            return $incoming;
        }

        return bin2hex(random_bytes(16));
    }
}
