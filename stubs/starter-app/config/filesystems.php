<?php

declare(strict_types=1);

return [
    'default' => $_ENV['FILESYSTEM_DISK'] ?? 'local',
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
        ],
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim($_ENV['APP_URL'] ?? 'http://localhost:8000', '/') . '/storage',
            'visibility' => 'public',
        ],
    ],
];
