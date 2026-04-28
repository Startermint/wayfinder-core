<?php

declare(strict_types=1);

namespace Wayfinder\Database;

use Wayfinder\Pagination\Paginator;

/**
 * @template TModel of Model
 */
final class ModelQuery
{
    /**
     * @param class-string<TModel> $modelClass
     */
    public function __construct(
        private readonly QueryBuilder $builder,
        private readonly string $modelClass,
    ) {
    }

    public function where(string|callable $column, mixed $operator = null, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            $this->builder->where($column, $operator);

            return $this;
        }

        $this->builder->where($column, $operator, $value);

        return $this;
    }

    public function whereKey(int|string $id): self
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $this->modelClass;

        $this->builder->where($modelClass::primaryKeyName(), $id);

        return $this;
    }

    public function orWhere(string|callable $column, mixed $operator = null, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            $this->builder->orWhere($column, $operator);

            return $this;
        }

        $this->builder->orWhere($column, $operator, $value);

        return $this;
    }

    /**
     * @param list<mixed> $values
     */
    public function whereIn(string $column, array $values): self
    {
        $this->builder->where($column, 'IN', $values);

        return $this;
    }

    /**
     * @param list<mixed> $values
     */
    public function whereNotIn(string $column, array $values): self
    {
        $this->builder->where($column, 'NOT IN', $values);

        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->builder->whereNull($column);

        return $this;
    }

    public function orWhereNull(string $column): self
    {
        $this->builder->orWhereNull($column);

        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->builder->whereNotNull($column);

        return $this;
    }

    public function orWhereNotNull(string $column): self
    {
        $this->builder->orWhereNotNull($column);

        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->builder->orderBy($column, $direction);

        return $this;
    }

    public function latest(string $column = 'id'): self
    {
        return $this->orderBy($column, 'DESC');
    }

    public function oldest(string $column = 'id'): self
    {
        return $this->orderBy($column, 'ASC');
    }

    public function limit(int $limit): self
    {
        $this->builder->limit($limit);

        return $this;
    }

    public function take(int $limit): self
    {
        return $this->limit($limit);
    }

    public function offset(int $offset): self
    {
        $this->builder->offset($offset);

        return $this;
    }

    public function skip(int $offset): self
    {
        return $this->offset($offset);
    }

    public function forPage(int $page, int $perPage): self
    {
        $this->builder->forPage($page, $perPage);

        return $this;
    }

    public function paginate(int $page = 1, int $perPage = 15): Paginator
    {
        $total = $this->count();
        $items = $this->forPage($page, $perPage)->get();

        return new Paginator($items, $total, $perPage, $page);
    }

    /**
     * @return list<TModel>
     */
    public function get(): array
    {
        return array_map(
            fn (array $row): Model => $this->modelClass::fromDatabaseRow($row),
            $this->builder->get(),
        );
    }

    /**
     * @return list<TModel>
     */
    public function all(): array
    {
        return $this->get();
    }

    /**
     * @return TModel|null
     */
    public function first(): ?Model
    {
        $row = $this->builder->first();

        if ($row === false) {
            return null;
        }

        return $this->modelClass::fromDatabaseRow($row);
    }

    public function firstOrFail(?string $message = null): Model
    {
        $model = $this->first();

        if ($model !== null) {
            return $model;
        }

        $shortName = basename(str_replace('\\', '/', $this->modelClass));

        throw new \RuntimeException($message ?? sprintf('%s record not found.', $shortName));
    }

    public function count(string $column = '*'): int
    {
        return $this->builder->count($column);
    }

    public function exists(): bool
    {
        return $this->builder->exists();
    }

    public function sum(string $column): int|float
    {
        return $this->builder->sum($column);
    }

    public function avg(string $column): int|float
    {
        return $this->builder->avg($column);
    }

    public function min(string $column): mixed
    {
        return $this->builder->min($column);
    }

    public function max(string $column): mixed
    {
        return $this->builder->max($column);
    }

    public function value(string $column): mixed
    {
        return $this->builder->value($column);
    }

    /**
     * @return list<mixed>
     */
    public function pluck(string $column): array
    {
        return $this->builder->pluck($column);
    }
}
