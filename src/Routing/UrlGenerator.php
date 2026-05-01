<?php

declare(strict_types=1);

namespace Wayfinder\Routing;

use Wayfinder\Http\Request;

final class UrlGenerator
{
    /**
     * @var callable(): self|null
     */
    private static $resolver = null;

    public function __construct(
        private readonly string $baseUrl,
        private ?Request $request = null,
    ) {
    }

    /**
     * @param callable(): self $resolver
     */
    public static function setResolver(callable $resolver): void
    {
        self::$resolver = $resolver;
    }

    public static function resolve(): self
    {
        if (self::$resolver === null) {
            throw new \RuntimeException('No URL generator resolver has been configured.');
        }

        return (self::$resolver)();
    }

    public function setRequest(?Request $request): void
    {
        $this->request = $request;
    }

    /**
     * @param array<int|string, mixed> $parameters
     */
    public function to(string $path = '', array $parameters = []): string
    {
        if ($this->isAbsoluteUrl($path)) {
            return $path;
        }

        $path = $this->applyParameters($path, $parameters);

        return $this->join($this->base(), $path);
    }

    /**
     * @param array<int|string, mixed> $parameters
     */
    public function secure(string $path = '', array $parameters = []): string
    {
        $url = $this->to($path, $parameters);

        return preg_replace('#^http://#i', 'https://', $url) ?? $url;
    }

    public function asset(string $path = ''): string
    {
        if ($this->isAbsoluteUrl($path)) {
            return $path;
        }

        return $this->join($this->base(), $path);
    }

    public function current(): string
    {
        if (! $this->request instanceof Request) {
            return $this->to();
        }

        return $this->to($this->request->path());
    }

    public function full(): string
    {
        if (! $this->request instanceof Request) {
            return $this->to();
        }

        $query = $this->queryString();

        return $query === '' ? $this->current() : $this->current() . '?' . $query;
    }

    public function previous(string $fallback = '/'): string
    {
        if (! $this->request instanceof Request) {
            return $this->to($fallback);
        }

        $referer = $this->request->header('referer');

        if (is_string($referer) && $referer !== '') {
            return $referer;
        }

        return $this->to($fallback);
    }

    private function base(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    private function join(string $base, string $path): string
    {
        if ($path === '' || $path === '/') {
            return $base === '' ? '/' : $base;
        }

        if ($base === '') {
            return '/' . ltrim($path, '/');
        }

        return $base . '/' . ltrim($path, '/');
    }

    /**
     * @param array<int|string, mixed> $parameters
     */
    private function applyParameters(string $path, array $parameters): string
    {
        if ($parameters === []) {
            return $path;
        }

        foreach ($parameters as $key => $value) {
            if (is_string($key)) {
                $placeholder = '{' . $key . '}';

                if (str_contains($path, $placeholder)) {
                    $path = str_replace($placeholder, $this->encodeSegment($value), $path);
                    unset($parameters[$key]);
                }
            }
        }

        foreach ($parameters as $value) {
            if (preg_match('/\{[^}]+\}/', $path) === 1) {
                $path = preg_replace('/\{[^}]+\}/', $this->encodeSegment($value), $path, 1) ?? $path;
                continue;
            }

            $path = rtrim($path, '/') . '/' . $this->encodeSegment($value);
        }

        return $path;
    }

    private function encodeSegment(mixed $value): string
    {
        if (! is_scalar($value) && ! (is_object($value) && method_exists($value, '__toString'))) {
            throw new \InvalidArgumentException('URL parameters must be scalar or stringable.');
        }

        return rawurlencode((string) $value);
    }

    private function queryString(): string
    {
        if (! $this->request instanceof Request) {
            return '';
        }

        $server = $this->request->server();
        $requestUri = $server['REQUEST_URI'] ?? null;

        if (is_string($requestUri)) {
            $query = parse_url($requestUri, PHP_URL_QUERY);

            if (is_string($query)) {
                return $query;
            }
        }

        $query = $this->request->query();

        return $query === [] ? '' : http_build_query($query);
    }

    private function isAbsoluteUrl(string $path): bool
    {
        return preg_match('#^[a-z][a-z0-9+.-]*://#i', $path) === 1 || str_starts_with($path, '//');
    }
}
