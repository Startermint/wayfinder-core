<?php

declare(strict_types=1);

namespace Wayfinder\Http;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Wayfinder\Session\Session;
use Wayfinder\Validation\Validator;

class Request
{
    private readonly SymfonyRequest $symfony;

    /** @var array<string, string> */
    private readonly array $headers;

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $request
     * @param array<string, mixed> $cookies
     * @param array<string, mixed> $files
     * @param array<string, mixed> $server
     * @param array<string, string> $headers
     * @param array<string, string> $routeParams
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $request,
        private readonly array $cookies,
        private readonly array $files,
        private readonly array $server,
        array $headers,
        private readonly string $body,
        private readonly array $routeParams = [],
        private readonly ?Session $session = null,
        ?SymfonyRequest $symfony = null,
    ) {
        $this->headers = self::normalizeHeaderMap($headers);
        $this->symfony = $symfony ?? self::makeSymfonyRequest(
            $method,
            $path,
            $query,
            $request,
            $cookies,
            $files,
            $server,
            $this->headers,
            $body,
        );
    }

    public static function fromGlobals(): self
    {
        return self::fromSymfony(SymfonyRequest::createFromGlobals());
    }

    public static function fromSymfony(SymfonyRequest $request): self
    {
        return new self(
            method: strtoupper($request->getMethod()),
            path: $request->getPathInfo() !== '' ? $request->getPathInfo() : '/',
            query: $request->query->all(),
            request: self::payloadFromSymfony($request),
            cookies: $request->cookies->all(),
            files: self::normalizeFiles($request->files->all()),
            server: $request->server->all(),
            headers: self::normalizeHeaders($request->headers->all()),
            body: $request->getContent(),
            routeParams: self::normalizeRouteParams($request->attributes->all()),
            symfony: $request,
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, mixed>
     */
    public function query(): array
    {
        return $this->query;
    }

    /**
     * @return array<string, mixed>
     */
    public function request(): array
    {
        return $this->request;
    }

    /**
     * @return array<string, mixed>
     */
    public function cookies(): array
    {
        return $this->cookies;
    }

    /**
     * @return array<string, mixed>
     */
    public function files(): array
    {
        return $this->files;
    }

    /**
     * @return array<string, mixed>
     */
    public function server(): array
    {
        return $this->server;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function redirectTarget(?string $fallback = null): string
    {
        $explicit = $this->input('_redirect');

        if (is_string($explicit) && $explicit !== '') {
            return $this->sanitizeRedirectTarget(
                $explicit,
                $fallback ?? $this->path,
            );
        }

        return $this->sanitizeRedirectTarget(
            $this->header('referer'),
            $fallback ?? $this->path,
        );
    }

    public function body(): string
    {
        return $this->body;
    }

    public function toSymfonyRequest(): SymfonyRequest
    {
        return $this->symfony;
    }

    public function symfony(): SymfonyRequest
    {
        return $this->symfony;
    }

    public function expectsJson(): bool
    {
        $accept = $this->header('accept', '');
        $requestedWith = $this->header('x-requested-with', '');

        return str_contains($accept, 'application/json')
            || str_contains($accept, 'text/json')
            || strtolower($requestedWith) === 'xmlhttprequest';
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    /**
     * @return array<string, string>
     */
    public function routeParams(): array
    {
        return $this->routeParams;
    }

    public function route(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    /**
     * @param array<string, string> $params
     */
    public function withRouteParams(array $params): static
    {
        $symfony = $this->symfony->duplicate(
            null,
            null,
            $params,
            null,
            null,
            null,
        );

        return new static(
            $this->method,
            $this->path,
            $this->query,
            $this->request,
            $this->cookies,
            $this->files,
            $this->server,
            $this->headers,
            $this->body,
            $params,
            $this->session,
            $symfony,
        );
    }

    public function hasSession(): bool
    {
        return $this->session instanceof Session;
    }

    public function session(): Session
    {
        if (! $this->session instanceof Session) {
            throw new \RuntimeException('No active session is available on this request.');
        }

        return $this->session;
    }

    public function withSession(Session $session): static
    {
        return new static(
            $this->method,
            $this->path,
            $this->query,
            $this->request,
            $this->cookies,
            $this->files,
            $this->server,
            $this->headers,
            $this->body,
            $this->routeParams,
            $session,
            $this->symfony,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return [...$this->query, ...$this->request];
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->request[$key] ?? $this->query[$key] ?? $default;
    }

    public function old(string $key, mixed $default = null, string $bag = 'default'): mixed
    {
        if (! $this->hasSession()) {
            return $default;
        }

        $old = $this->session()->get('_old_input', []);

        if (! is_array($old)) {
            return $default;
        }

        if (isset($old[$bag]) && is_array($old[$bag])) {
            return $old[$bag][$key] ?? $default;
        }

        if ($bag === 'default') {
            return $old[$key] ?? $default;
        }

        return $default;
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(string $bag = 'default'): array
    {
        if (! $this->hasSession()) {
            return [];
        }

        $errors = $this->session()->get('_errors', []);

        if (! is_array($errors)) {
            return [];
        }

        if (isset($errors[$bag]) && is_array($errors[$bag])) {
            return $errors[$bag];
        }

        if ($bag === 'default' && $this->looksLikeFlatErrors($errors)) {
            return $errors;
        }

        return [];
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    public function integer(string $key, int $default = 0): int
    {
        $value = $this->input($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->input($key, $default);

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value) || is_int($value)) {
            $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            return $parsed ?? $default;
        }

        return $default;
    }

    /**
     * @param array<string, string|list<string>> $rules
     * @param array<string, string> $messages
     * @return array<string, mixed>
     */
    public function validate(array $rules, array $messages = [], string $bag = 'default'): array
    {
        return (new Validator($this, [...$this->all(), ...$this->files], $rules, $messages, $bag))->validate();
    }

    private function looksLikeFlatErrors(array $errors): bool
    {
        foreach ($errors as $field => $messages) {
            if (! is_string($field) || ! is_array($messages)) {
                return false;
            }
        }

        return true;
    }

    private function sanitizeRedirectTarget(?string $target, string $fallback): string
    {
        $fallback = trim($fallback) === '' ? '/' : $fallback;
        $normalizedFallback = str_starts_with($fallback, '/') ? $fallback : '/' . ltrim($fallback, '/');

        if (! is_string($target)) {
            return $normalizedFallback;
        }

        $target = trim($target);

        if ($target === '' || str_contains($target, "\r") || str_contains($target, "\n")) {
            return $normalizedFallback;
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $target) === 1 || str_starts_with($target, '//')) {
            return $normalizedFallback;
        }

        if (! str_starts_with($target, '/')) {
            return $normalizedFallback;
        }

        return '/' . ltrim($target, '/');
    }

    /**
     * @param array<string, mixed> $server
     * @return array<string, string>
     */
    private static function headersFromServer(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (! is_string($value)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }

            if (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $name = strtolower(str_replace('_', '-', $key));
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $request
     * @param array<string, mixed> $cookies
     * @param array<string, mixed> $files
     * @param array<string, mixed> $server
     * @param array<string, string> $headers
     */
    private static function makeSymfonyRequest(
        string $method,
        string $path,
        array $query,
        array $request,
        array $cookies,
        array $files,
        array $server,
        array $headers,
        string $body,
    ): SymfonyRequest {
        $server = [
            ...$server,
            'REQUEST_METHOD' => strtoupper($method),
            'REQUEST_URI' => $path,
        ];

        foreach ($headers as $name => $value) {
            $server[self::serverHeaderName($name)] = $value;
        }

        try {
            return SymfonyRequest::create(
                $path,
                strtoupper($method),
                $request,
                $cookies,
                $files,
                $server,
                $body,
            )->duplicate($query, $request, null, $cookies, $files, $server);
        } catch (\Throwable) {
            return SymfonyRequest::create(
                $path,
                strtoupper($method),
                $request,
                $cookies,
                [],
                $server,
                $body,
            )->duplicate($query, $request, null, $cookies, [], $server);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function payloadFromSymfony(SymfonyRequest $request): array
    {
        if ($request->request->count() > 0) {
            return $request->request->all();
        }

        if (! str_contains((string) $request->headers->get('content-type', ''), 'json')) {
            return [];
        }

        try {
            $payload = $request->toArray();
        } catch (\JsonException) {
            return [];
        }

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param array<string, list<string|null>> $headers
     * @return array<string, string>
     */
    private static function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $values) {
            $value = implode(', ', array_filter($values, static fn (mixed $value): bool => is_scalar($value)));

            if ($value !== '') {
                $normalized[strtolower($name)] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private static function normalizeHeaderMap(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $value) {
            if (is_scalar($value) && (string) $value !== '') {
                $normalized[strtolower((string) $name)] = (string) $value;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, string>
     */
    private static function normalizeRouteParams(array $attributes): array
    {
        $params = [];

        foreach ($attributes as $key => $value) {
            if (is_scalar($value)) {
                $params[$key] = (string) $value;
            }
        }

        return $params;
    }

    private static function serverHeaderName(string $name): string
    {
        $normalized = strtoupper(str_replace('-', '_', $name));

        return in_array($normalized, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)
            ? $normalized
            : 'HTTP_' . $normalized;
    }

    /**
     * @param array<string, mixed> $files
     * @return array<string, mixed>
     */
    private static function normalizeFiles(array $files): array
    {
        $normalized = [];

        foreach ($files as $key => $file) {
            if ($file instanceof UploadedFile) {
                $normalized[$key] = [
                    'name' => $file->getClientOriginalName(),
                    'type' => $file->getClientMimeType(),
                    'tmp_name' => $file->getPathname(),
                    'error' => $file->getError(),
                    'size' => $file->getSize() ?: 0,
                ];

                continue;
            }

            if (is_array($file)) {
                $normalized[$key] = self::normalizeFiles($file);
                continue;
            }

            $normalized[$key] = $file;
        }

        return $normalized;
    }
}
