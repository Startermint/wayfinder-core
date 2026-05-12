<?php

declare(strict_types=1);

namespace Wayfinder\Http;

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

final class TrustedProxies
{
    /**
     * @param list<string> $proxies
     */
    public static function configure(array $proxies): void
    {
        $proxies = array_values(array_filter(array_map('trim', $proxies), static fn (string $proxy): bool => $proxy !== ''));

        if ($proxies === []) {
            SymfonyRequest::setTrustedProxies([], -1);

            return;
        }

        SymfonyRequest::setTrustedProxies($proxies, self::trustedHeaderSet());
    }

    private static function trustedHeaderSet(): int
    {
        return SymfonyRequest::HEADER_X_FORWARDED_FOR
            | SymfonyRequest::HEADER_X_FORWARDED_HOST
            | SymfonyRequest::HEADER_X_FORWARDED_PORT
            | SymfonyRequest::HEADER_X_FORWARDED_PROTO
            | SymfonyRequest::HEADER_X_FORWARDED_PREFIX;
    }
}
