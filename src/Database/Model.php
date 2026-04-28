<?php

declare(strict_types=1);

namespace Wayfinder\Database;

use Wayfinder\Database\Concerns\HasAttributes;
use Wayfinder\Pagination\Paginator;

abstract class Model
{
    use HasAttributes;

    protected bool $exists = false;

    protected static string $table;

    protected static string $primaryKey = 'id';

    /**
     * @param array<string, mixed> $attributes
     */
    public static function fromDatabaseRow(array $attributes): static
    {
        $model = new static($attributes);
        $model->exists = true;

        return $model;
    }

    public static function query(): ModelQuery
    {
        return new ModelQuery(
            DB::connection()->table(static::tableName()),
            static::class,
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function make(array $attributes = []): static
    {
        return new static($attributes);
    }

    public static function find(int|string $id): ?static
    {
        return static::query()->whereKey($id)->first();
    }

    public static function findOrFail(int|string $id, ?string $message = null): static
    {
        return static::query()->whereKey($id)->firstOrFail(
            $message ?? sprintf('%s record [%s] not found.', static::class, (string) $id),
        );
    }

    /**
     * @param list<int|string> $ids
     * @return list<static>
     */
    public static function findMany(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return static::query()
            ->whereIn(static::primaryKeyName(), array_values($ids))
            ->get();
    }

    /**
     * @return list<static>
     */
    public static function all(): array
    {
        return static::query()->get();
    }

    public static function orderBy(string $column, string $direction = 'ASC'): ModelQuery
    {
        return static::query()->orderBy($column, $direction);
    }

    public static function latest(?string $column = null): ModelQuery
    {
        return static::query()->orderBy($column ?? static::primaryKeyName(), 'DESC');
    }

    public static function oldest(?string $column = null): ModelQuery
    {
        return static::query()->orderBy($column ?? static::primaryKeyName(), 'ASC');
    }

    public static function paginate(int $page = 1, int $perPage = 15): Paginator
    {
        return static::query()->paginate($page, $perPage);
    }

    public static function limit(int $limit): ModelQuery
    {
        return static::query()->limit($limit);
    }

    public static function take(int $limit): ModelQuery
    {
        return static::query()->take($limit);
    }

    public static function offset(int $offset): ModelQuery
    {
        return static::query()->offset($offset);
    }

    public static function skip(int $offset): ModelQuery
    {
        return static::query()->skip($offset);
    }

    public static function forPage(int $page, int $perPage): ModelQuery
    {
        return static::query()->forPage($page, $perPage);
    }

    public static function where(string|callable $column, mixed $operator = null, mixed $value = null): ModelQuery
    {
        if (func_num_args() === 2) {
            return static::query()->where($column, $operator);
        }

        return static::query()->where($column, $operator, $value);
    }

    public static function orWhere(string|callable $column, mixed $operator = null, mixed $value = null): ModelQuery
    {
        if (func_num_args() === 2) {
            return static::query()->orWhere($column, $operator);
        }

        return static::query()->orWhere($column, $operator, $value);
    }

    public static function whereNull(string $column): ModelQuery
    {
        return static::query()->whereNull($column);
    }

    public static function orWhereNull(string $column): ModelQuery
    {
        return static::query()->orWhereNull($column);
    }

    public static function whereNotNull(string $column): ModelQuery
    {
        return static::query()->whereNotNull($column);
    }

    public static function orWhereNotNull(string $column): ModelQuery
    {
        return static::query()->orWhereNotNull($column);
    }

    /**
     * @return list<static>
     */
    public static function get(string|callable $column, mixed $operator = null, mixed $value = null): array
    {
        if (func_num_args() === 2) {
            return static::query()->where($column, $operator)->get();
        }

        return static::query()->where($column, $operator, $value)->get();
    }

    public static function first(string|callable $column, mixed $operator = null, mixed $value = null): ?static
    {
        if (func_num_args() === 2) {
            return static::query()->where($column, $operator)->first();
        }

        return static::query()->where($column, $operator, $value)->first();
    }

    public static function firstWhere(string|callable $column, mixed $operator = null, mixed $value = null): ?static
    {
        if (func_num_args() === 2) {
            return static::first($column, $operator);
        }

        return static::first($column, $operator, $value);
    }

    public static function whereKey(int|string $id): ModelQuery
    {
        return static::query()->whereKey($id);
    }

    /**
     * @param list<mixed> $values
     */
    public static function whereIn(string $column, array $values): ModelQuery
    {
        return static::query()->whereIn($column, $values);
    }

    /**
     * @param list<mixed> $values
     */
    public static function whereNotIn(string $column, array $values): ModelQuery
    {
        return static::query()->whereNotIn($column, $values);
    }

    /**
     * @return list<mixed>
     */
    public static function pluck(string $column): array
    {
        return static::query()->pluck($column);
    }

    public static function value(string $column): mixed
    {
        return static::query()->value($column);
    }

    public static function count(string $column = '*'): int
    {
        return static::query()->count($column);
    }

    public static function existsWhere(string|callable $column, mixed $operator = null, mixed $value = null): bool
    {
        if (func_num_args() === 2) {
            return static::query()->where($column, $operator)->exists();
        }

        return static::query()->where($column, $operator, $value)->exists();
    }

    public static function sum(string $column): int|float
    {
        return static::query()->sum($column);
    }

    public static function avg(string $column): int|float
    {
        return static::query()->avg($column);
    }

    public static function min(string $column): mixed
    {
        return static::query()->min($column);
    }

    public static function max(string $column): mixed
    {
        return static::query()->max($column);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function create(array $attributes): static
    {
        $database = DB::connection();
        $database->insert(static::tableName())
            ->params($attributes)
            ->execute();

        $id = $database->lastInsertId();

        if ($id !== '0' && $id !== '') {
            $created = static::find(is_numeric($id) ? (int) $id : $id);

            if ($created !== null) {
                return $created;
            }
        }

        return static::fromDatabaseRow($attributes);
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<static>
     */
    public static function createMany(array $records): array
    {
        $created = [];

        foreach ($records as $attributes) {
            $created[] = static::create($attributes);
        }

        return $created;
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $values
     */
    public static function firstOrNew(array $attributes, array $values = []): static
    {
        $query = static::query();

        foreach ($attributes as $column => $value) {
            $query->where((string) $column, $value);
        }

        $existing = $query->first();

        if ($existing !== null) {
            return $existing;
        }

        return static::make([...$attributes, ...$values]);
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $values
     */
    public static function firstOrCreate(array $attributes, array $values = []): static
    {
        $existing = static::firstOrNew($attributes, $values);

        if ($existing->exists()) {
            return $existing;
        }

        return static::create($existing->toArray());
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $values
     */
    public static function updateOrCreate(array $attributes, array $values = []): static
    {
        $model = static::firstOrNew($attributes);

        if ($model->exists()) {
            return $model->update($values);
        }

        return static::create([...$attributes, ...$values]);
    }

    public function update(array $attributes): static
    {
        if (! $this->exists) {
            throw new \RuntimeException('Cannot update a model that has not been persisted.');
        }

        DB::connection()
            ->update(static::tableName(), $attributes)
            ->where(static::primaryKeyName(), $this->getKey())
            ->execute();

        $this->fill([...$this->attributes, ...$attributes]);

        return $this;
    }

    public function save(): static
    {
        if ($this->exists) {
            return $this->update($this->toArray());
        }

        $created = static::create($this->toArray());
        $this->fill($created->toArray());
        $this->exists = true;

        return $this;
    }

    public function delete(): bool
    {
        if (! $this->exists) {
            return false;
        }

        $deleted = DB::connection()
            ->delete(static::tableName())
            ->where(static::primaryKeyName(), $this->getKey())
            ->execute() > 0;

        if ($deleted) {
            $this->exists = false;
        }

        return $deleted;
    }

    public static function deleteWhere(string|callable $column, mixed $operator = null, mixed $value = null): int
    {
        $query = DB::connection()->delete(static::tableName());

        if (func_num_args() === 2) {
            return $query->where($column, $operator)->execute();
        }

        return $query->where($column, $operator, $value)->execute();
    }

    public function exists(): bool
    {
        return $this->exists;
    }

    public function refresh(): static
    {
        if (! $this->exists) {
            throw new \RuntimeException('Cannot refresh a model that has not been persisted.');
        }

        $fresh = static::find($this->getKey());

        if ($fresh === null) {
            throw new \RuntimeException('Cannot refresh a model that no longer exists in storage.');
        }

        $this->fill($fresh->toArray());
        $this->exists = true;

        return $this;
    }

    public function fresh(): ?static
    {
        if (! $this->exists) {
            return null;
        }

        return static::find($this->getKey());
    }

    public function getKey(): mixed
    {
        return $this->getAttribute(static::primaryKeyName());
    }

    public static function tableName(): string
    {
        if (! isset(static::$table) || static::$table === '') {
            throw new \RuntimeException(sprintf('Model [%s] must define a table name.', static::class));
        }

        return static::$table;
    }

    public static function primaryKeyName(): string
    {
        return static::$primaryKey;
    }
}
