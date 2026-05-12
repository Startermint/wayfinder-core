# Health Checks

Wayfinder includes framework-level health and readiness checks for production deploys.

Register the `health` command lazily so normal console commands such as `route:list` do not open database, queue, cache, or storage connections during bootstrap.

```php
$application->addLazy('health', 'Run application health and readiness checks.', static function () use ($container, $config): HealthCommand {
    return new HealthCommand(new HealthRunner([
        new AppConfigHealthCheck($config),
        new DatabaseHealthCheck($container->get(Database::class)),
        new CacheHealthCheck($container->get(Cache::class)),
        new StorageHealthCheck($container->get(FilesystemManager::class)->disk()),
        new QueueHealthCheck($container->get(QueueManager::class)),
    ]));
});
```

Run checks before deploy traffic is routed to the app:

```sh
php wayfinder health
php wayfinder health --json
```

The command exits with `1` when a check fails. Warnings are printed but do not fail the command.

Built-in checks:

- `AppConfigHealthCheck`: validates app key, URL, debug mode, and trusted hosts.
- `DatabaseHealthCheck`: runs a lightweight `SELECT 1`.
- `DatabaseTablesHealthCheck`: verifies required tables exist.
- `CacheHealthCheck`: writes, reads, and deletes a cache key.
- `StorageHealthCheck`: writes, reads, and deletes a file on a disk.
- `QueueHealthCheck`: resolves the queue connection and can warn when failed jobs exist.
