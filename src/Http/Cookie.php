<?php

declare(strict_types=1);

namespace Wayfinder\Http;

final class Cookie
{
    public function __construct(
        private readonly string $name,
        private readonly string $value,
        private readonly int $expires = 0,
        private readonly string $path = '/',
        private readonly string $domain = '',
        private readonly bool $secure = false,
        private readonly bool $httpOnly = true,
        string $sameSite = 'Lax',
    ) {
        $this->sameSite = $this->normalizeSameSite($sameSite);

        if ($this->sameSite === 'None' && ! $this->secure) {
            throw new \InvalidArgumentException('Cookies using SameSite=None must also be Secure.');
        }
    }

    private readonly string $sameSite;

    public static function make(
        string $name,
        string $value,
        int $expires = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true,
        string $sameSite = 'Lax',
    ): self {
        return new self($name, $value, $expires, $path, $domain, $secure, $httpOnly, $sameSite);
    }

    public static function forget(
        string $name,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true,
        string $sameSite = 'Lax',
    ): self {
        return new self($name, '', time() - 31536000, $path, $domain, $secure, $httpOnly, $sameSite);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function expires(): int
    {
        return $this->expires;
    }

    /**
     * @return array{
     *     expires: int,
     *     path: string,
     *     domain: string,
     *     secure: bool,
     *     httponly: bool,
     *     samesite: string
     * }
     */
    public function options(): array
    {
        return [
            'expires' => $this->expires,
            'path' => $this->path,
            'domain' => $this->domain,
            'secure' => $this->secure,
            'httponly' => $this->httpOnly,
            'samesite' => $this->sameSite,
        ];
    }

    public function send(): void
    {
        setcookie($this->name, $this->value, $this->options());
    }

    private function normalizeSameSite(string $sameSite): string
    {
        $sameSite = strtolower(trim($sameSite));

        return match ($sameSite) {
            'lax' => 'Lax',
            'strict' => 'Strict',
            'none' => 'None',
            default => throw new \InvalidArgumentException(sprintf('Unsupported SameSite cookie value [%s].', $sameSite)),
        };
    }
}
