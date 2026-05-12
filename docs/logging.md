# Logging

Wayfinder uses Monolog behind its existing `Wayfinder\Logging\Logger` contract. Applications can also type-hint `Psr\Log\LoggerInterface` when the bootstrap binds it.

Applications should define `config/logging.php`:

```php
<?php

declare(strict_types=1);

return [
    'default' => $_ENV['LOG_CHANNEL'] ?? 'single',
    'sensitive_keys' => ['authorization', 'cookie', 'csrf', 'password', 'secret', 'token', 'api_key', 'private_key'],
    'channels' => [
        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/app.log'),
            'level' => $_ENV['LOG_LEVEL'] ?? 'debug',
        ],
        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/app.log'),
            'level' => $_ENV['LOG_LEVEL'] ?? 'debug',
            'days' => 14,
        ],
        'stderr' => [
            'driver' => 'stderr',
            'level' => $_ENV['LOG_LEVEL'] ?? 'debug',
        ],
        'null' => [
            'driver' => 'null',
        ],
    ],
];
```

Supported drivers:

- `single`: write to one file
- `file`: backward-compatible alias for `single`
- `daily`: write dated log files and retain a configured number of days
- `stderr`: write to `php://stderr`, useful for containers
- `stream`: write to a configured stream path
- `null`: discard logs

For simple VPS deployments, `single` or `daily` is usually enough. For containerized deployments, prefer `stderr` and let the platform collect logs.

Sensitive context values are redacted before Monolog writes records. The default redaction keys cover passwords, tokens, cookies, authorization headers, CSRF values, secrets, and API/private keys. Override `sensitive_keys` only when an application has additional names that should be masked.
