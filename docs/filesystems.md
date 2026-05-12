# Filesystems

Wayfinder uses Flysystem for application storage. Use `Wayfinder\Filesystem\Storage` when application code needs to store user uploads, generated exports, public assets, or other app-managed files.

```php
use Wayfinder\Filesystem\Storage;

Storage::write('reports/monthly.csv', $csv);

$contents = Storage::read('reports/monthly.csv');

Storage::disk('public')->write('avatars/user-1.png', $png);

$url = Storage::url('avatars/user-1.png', 'public');
```

## Configuration

Applications should define `config/filesystems.php`:

```php
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
```

The first supported driver is `local`. Remote drivers such as S3, SFTP, and FTP should be added as optional adapter packages when an application needs them.

## Public Files

Use `php wayfinder storage:link` to connect `public/storage` to `storage/app/public`.

## Framework Internals

Flysystem is the application storage abstraction. Low-level framework internals such as config cache files, route cache files, generated migration files, and local session/cache files may continue using direct local filesystem operations because those are project-local bootstrap files rather than swappable application storage.
