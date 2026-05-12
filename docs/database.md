# Database

Wayfinder's `Database` wrapper keeps PDO explicit while adding a few production controls for connection setup and observability.

## Connection Options

Database connection config supports the normal driver fields plus optional PDO controls:

```php
return [
    'default' => 'default',
    'connections' => [
        'default' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'dbname' => 'app',
            'charset' => 'utf8mb4',
            'username' => 'root',
            'password' => '',
            'timeout' => 5,
            'persistent' => false,
            'options' => [
                PDO::ATTR_CASE => PDO::CASE_NATURAL,
            ],
        ],
    ],
];
```

Wayfinder always defaults PDO to exception mode, associative fetches, and native prepares. Values in `options` may override PDO attributes when an application needs a specific driver setting.

Use persistent connections only after validating the process model and database server limits. Long-running queue workers and PHP-FPM pools can keep persistent handles open longer than expected.

## Query Events

Register query listeners when an application needs SQL timing or diagnostics:

```php
$database->listen(function (Wayfinder\Database\QueryExecuted $query): void {
    logger()->debug('SQL query executed.', [
        'connection' => $query->connection,
        'sql' => $query->sql,
        'bindings' => $query->bindings,
        'milliseconds' => $query->milliseconds,
    ]);
});
```

Slow-query listeners are threshold based:

```php
$database->whenQueryingForLongerThan(250, function (Wayfinder\Database\QueryExecuted $query): void {
    logger()->warning('Slow SQL query.', [
        'connection' => $query->connection,
        'sql' => $query->sql,
        'milliseconds' => $query->milliseconds,
    ]);
});
```

## Reconnecting

`Database::reconnect()` refreshes the underlying PDO connection. `DatabaseManager::disconnect()` drops a cached connection instance, and `DatabaseManager::reconnect()` creates a fresh one by name.

This is useful for long-running processes after a database restart or when a worker process should release stale handles before continuing.
