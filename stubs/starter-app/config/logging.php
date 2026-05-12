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
            'days' => (int) ($_ENV['LOG_DAILY_DAYS'] ?? 14),
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
