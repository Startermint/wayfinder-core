<?php

declare(strict_types=1);

namespace Wayfinder\Http;

use Wayfinder\Contracts\Middleware;

final class ValidateHost implements Middleware
{
    /**
     * @param list<string> $allowedHosts
     */
    public function __construct(
        private readonly array $allowedHosts = [],
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($this->allowedHosts === []) {
            return $next($request);
        }

        $host = $this->host($request);

        if ($host === null || ! $this->isAllowed($host)) {
            return Response::text('Bad Request', 400);
        }

        return $next($request);
    }

    private function host(Request $request): ?string
    {
        $host = $request->header('host');

        if (! is_string($host) || trim($host) === '') {
            $server = $request->server();
            $host = is_string($server['HTTP_HOST'] ?? null) ? $server['HTTP_HOST'] : null;
        }

        if (! is_string($host) || trim($host) === '') {
            return null;
        }

        $host = strtolower(trim($host));

        if (str_contains($host, "\r") || str_contains($host, "\n")) {
            return null;
        }

        if (str_starts_with($host, '[')) {
            $end = strpos($host, ']');

            return $end === false ? null : substr($host, 0, $end + 1);
        }

        return explode(':', $host, 2)[0];
    }

    private function isAllowed(string $host): bool
    {
        foreach ($this->allowedHosts as $allowed) {
            $allowed = strtolower(trim($allowed));

            if ($allowed === '') {
                continue;
            }

            if ($allowed === '*' || $allowed === $host) {
                return true;
            }

            if (str_starts_with($allowed, '*.') && str_ends_with($host, substr($allowed, 1))) {
                return true;
            }
        }

        return false;
    }
}
