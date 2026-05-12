<?php

declare(strict_types=1);

namespace Wayfinder\Database;

use PDO;
use PDOException;

final class Database
{
    private PDO $pdo;

    private bool $pretending = false;

    /**
     * @var list<array{sql: string, bindings: list<mixed>}>
     */
    private array $pretendStatements = [];

    /**
     * @param array{
     *     driver?: string,
     *     host?: string,
     *     port?: int|string,
     *     dbname?: string,
     *     database?: string,
     *     charset?: string,
     *     username?: string,
     *     password?: string,
     *     path?: string,
     *     options?: array<int, mixed>,
     *     timeout?: int|string,
     *     persistent?: bool
     * } $config
     */
    public function __construct(
        private readonly array $config,
        private readonly string $connectionName = 'default',
    )
    {
        $this->pdo = $this->connect();
    }

    /** @var list<callable(QueryExecuted): void> */
    private array $queryListeners = [];

    /** @var list<callable(QueryExecuted): void> */
    private array $slowQueryListeners = [];

    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    public function reconnect(): void
    {
        if ($this->pretending) {
            return;
        }

        $this->pdo = $this->connect();
    }

    public function listen(callable $listener): void
    {
        $this->queryListeners[] = $listener;
    }

    public function whenQueryingForLongerThan(int|float $milliseconds, callable $listener): void
    {
        $this->slowQueryListeners[] = static function (QueryExecuted $query) use ($milliseconds, $listener): void {
            if ($query->milliseconds >= $milliseconds) {
                $listener($query);
            }
        };
    }

    public function driver(): string
    {
        return $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this, $table);
    }

    /**
     * Run a callback without executing SQL, returning the statements it attempted.
     *
     * @return list<array{sql: string, bindings: list<mixed>}>
     */
    public function pretend(callable $callback): array
    {
        $wasPretending = $this->pretending;
        $previousStatements = $this->pretendStatements;

        $this->pretending = true;
        $this->pretendStatements = [];

        try {
            $callback();

            return $this->pretendStatements;
        } finally {
            $this->pretending = $wasPretending;
            $this->pretendStatements = $previousStatements;
        }
    }

    public function select(string $table, string|array $columns = '*'): QueryBuilder
    {
        return $this->table($table)->select($columns);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(string $table, array $data = []): int|QueryBuilder
    {
        $builder = $this->table($table)->prepareInsert($data);

        if ($data === []) {
            return $builder;
        }

        return $builder->execute();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(string $table, array $data = []): QueryBuilder
    {
        return $this->table($table)->prepareUpdate($data);
    }

    public function delete(string $table): QueryBuilder
    {
        return $this->table($table)->prepareDelete();
    }

    /**
     * @param list<mixed> $bindings
     * @return list<array<string, mixed>>
     */
    public function raw(string $sql, array $bindings = []): array
    {
        return $this->query($sql, $bindings);
    }

    /**
     * @param list<mixed> $bindings
     * @return list<array<string, mixed>>
     */
    public function query(string $sql, array $bindings = []): array
    {
        if ($this->pretending) {
            $this->recordPretendStatement($sql, $bindings);

            return [];
        }

        try {
            $startedAt = microtime(true);
            $statement = $this->pdo->prepare($sql);
            $statement->execute($bindings);
            $results = $statement->fetchAll();
            $statement->closeCursor();
            $this->recordQuery($sql, $bindings, $startedAt);

            return $results;
        } catch (PDOException $exception) {
            throw new \RuntimeException('Database query failed.', 0, $exception);
        }
    }

    /**
     * @param list<mixed> $bindings
     */
    public function firstResult(string $sql, array $bindings = []): array|false
    {
        if ($this->pretending) {
            $this->recordPretendStatement($sql, $bindings);

            return false;
        }

        try {
            $startedAt = microtime(true);
            $statement = $this->pdo->prepare($sql);
            $statement->execute($bindings);
            $result = $statement->fetch();
            $statement->closeCursor();
            $this->recordQuery($sql, $bindings, $startedAt);

            return $result;
        } catch (PDOException $exception) {
            throw new \RuntimeException('Database query failed.', 0, $exception);
        }
    }

    /**
     * @param list<mixed> $bindings
     */
    public function statement(string $sql, array $bindings = []): int
    {
        if ($this->pretending) {
            $this->recordPretendStatement($sql, $bindings);

            return 0;
        }

        try {
            $startedAt = microtime(true);
            $statement = $this->pdo->prepare($sql);
            $statement->execute($bindings);
            $count = $statement->rowCount();
            $statement->closeCursor();
            $this->recordQuery($sql, $bindings, $startedAt);

            return $count;
        } catch (PDOException $exception) {
            throw new \RuntimeException('Database statement failed.', 0, $exception);
        }
    }

    public function lastInsertId(): string
    {
        if ($this->pretending) {
            return '0';
        }

        return $this->pdo->lastInsertId();
    }

    /**
     * Execute a callback inside a database transaction.
     *
     * If a transaction is already active the callback runs within it and
     * commit/rollback are left to the outer caller. Any exception thrown
     * by the callback rolls back the transaction and is re-thrown.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        if ($this->pretending) {
            return $callback();
        }

        $nested = $this->pdo->inTransaction();

        if (! $nested) {
            $this->pdo->beginTransaction();
        }

        try {
            $result = $callback();

            if (! $nested) {
                $this->pdo->commit();
            }

            return $result;
        } catch (\Throwable $e) {
            if (! $nested) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function beginTransaction(): bool
    {
        if ($this->pretending) {
            return true;
        }

        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        if ($this->pretending) {
            return true;
        }

        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        if ($this->pretending) {
            return true;
        }

        return $this->pdo->rollBack();
    }

    public function inTransaction(): bool
    {
        if ($this->pretending) {
            return false;
        }

        return $this->pdo->inTransaction();
    }

    public function qualifyIdentifier(string $identifier): string
    {
        $parts = explode('.', $identifier);

        foreach ($parts as $part) {
            if ($part === '*') {
                continue;
            }

            if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $part)) {
                throw new \InvalidArgumentException(sprintf('Invalid SQL identifier [%s].', $identifier));
            }
        }

        return implode('.', $parts);
    }

    public function normalizeColumnList(string|array $columns): string
    {
        if (is_array($columns)) {
            return implode(', ', array_map($this->qualifyIdentifier(...), $columns));
        }

        if ($columns === '*') {
            return '*';
        }

        $parts = array_map('trim', explode(',', $columns));

        return implode(', ', array_map($this->qualifyIdentifier(...), $parts));
    }

    /**
     * @param array{
     *     host?: string,
     *     port?: int|string,
     *     dbname?: string,
     *     database?: string,
     *     charset?: string,
     *     path?: string
     * } $config
     */
    private function buildDsn(string $driver, array $config): string
    {
        return match ($driver) {
            'mysql' => sprintf(
                'mysql:host=%s;dbname=%s;charset=%s%s',
                $config['host'] ?? '127.0.0.1',
                $config['dbname'] ?? $config['database'] ?? '',
                $config['charset'] ?? 'utf8mb4',
                isset($config['port']) ? ';port=' . $config['port'] : '',
            ),
            'pgsql' => sprintf(
                'pgsql:host=%s;dbname=%s%s',
                $config['host'] ?? '127.0.0.1',
                $config['dbname'] ?? $config['database'] ?? '',
                isset($config['port']) ? ';port=' . $config['port'] : '',
            ),
            'sqlite' => sprintf('sqlite:%s', $config['path'] ?? ':memory:'),
            default => throw new \InvalidArgumentException(sprintf('Unsupported database driver [%s].', $driver)),
        };
    }

    private function connect(): PDO
    {
        $driver = (string) ($this->config['driver'] ?? 'mysql');
        $dsn = $this->buildDsn($driver, $this->config);

        try {
            return new PDO(
                $dsn,
                $this->config['username'] ?? null,
                $this->config['password'] ?? null,
                $this->pdoOptions($this->config),
            );
        } catch (PDOException $exception) {
            throw new \RuntimeException('Database connection failed.', 0, $exception);
        }
    }

    /**
     * @param array<string, mixed> $config
     * @return array<int, mixed>
     */
    private function pdoOptions(array $config): array
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => (bool) ($config['persistent'] ?? false),
        ];

        if (isset($config['timeout']) && is_numeric($config['timeout'])) {
            $options[PDO::ATTR_TIMEOUT] = max(1, (int) $config['timeout']);
        }

        if (isset($config['options']) && is_array($config['options'])) {
            foreach ($config['options'] as $key => $value) {
                if (is_int($key)) {
                    $options[$key] = $value;
                }
            }
        }

        return $options;
    }

    /**
     * @param list<mixed> $bindings
     */
    private function recordQuery(string $sql, array $bindings, float $startedAt): void
    {
        if ($this->queryListeners === [] && $this->slowQueryListeners === []) {
            return;
        }

        $query = new QueryExecuted(
            $sql,
            $bindings,
            (microtime(true) - $startedAt) * 1000,
            $this->connectionName,
        );

        foreach ($this->queryListeners as $listener) {
            $listener($query);
        }

        foreach ($this->slowQueryListeners as $listener) {
            $listener($query);
        }
    }

    /**
     * @param list<mixed> $bindings
     */
    private function recordPretendStatement(string $sql, array $bindings): void
    {
        $this->pretendStatements[] = [
            'sql' => $sql,
            'bindings' => $bindings,
        ];
    }
}
