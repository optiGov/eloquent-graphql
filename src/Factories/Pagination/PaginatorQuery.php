<?php

namespace EloquentGraphQL\Factories\Pagination;

use Closure;
use EloquentGraphQL\Exceptions\GraphQLError;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class PaginatorQuery extends Paginator
{
    private Builder $queryBuilder;

    public function __construct(Builder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    public function count(): int
    {
        $clone = $this->queryBuilder->cloneWithout(['limit', 'offset']);

        return $clone->count();
    }

    /**
     * @throws GraphQLError
     */
    protected function applyFilter(array $filter): void
    {
        $this->applyFilterOnQuery($filter, $this->queryBuilder);
    }

    /**
     * @throws GraphQLError
     */
    protected function applyFilterOnQuery(array $filter, Builder $query): void
    {

        if (Arr::exists($filter, 'and')) {
            foreach ($filter['and'] as $filterLevel) {
                $query->where(function (Builder $query) use ($filterLevel) {
                    $this->applyFilterOnQuery($filterLevel, $query);
                });
            }
        }

        if (Arr::exists($filter, 'or')) {
            $method = Arr::exists($filter, 'and')
                ? fn (Closure $callback) => $query->orWhere($callback)
                : fn (Closure $callback) => $query->where($callback);

            foreach ($filter['or'] as $filterLevel) {
                $method(function (Builder $query) use ($filterLevel) {
                    $this->applyFilterOnQuery($filterLevel, $query);
                });

                $method = fn (Closure $callback) => $query->orWhere($callback);
            }
        }

        unset($filter['and']);
        unset($filter['or']);

        $this->applyFilterFieldsOnQuery($filter, $query);
    }

    /**
     * @throws GraphQLError
     */
    private function applyFilterFieldsOnQuery(array $filter, Builder $query, string $tableName = null): void
    {
        foreach ($filter as $field => $filterInput) {
            if (count($filterInput) !== 1) {
                throw new GraphQLError('Filter must have exactly one operator.');
            }

            if ($tableName) {
                $field = $tableName.'.'.$field;
            }

            $operator = Arr::first(array_keys($filterInput));
            $value = Arr::first($filterInput);

            if ($operator === 'eq') {
                if ($value === null) {
                    $query->whereNull($field);
                } else {
                    $query->where($field, '=', $value);
                }
            } elseif ($operator === 'ne') {
                $query->where($field, '!=', $value);
            } elseif ($operator === 'lt') {
                $query->where($field, '<', $value);
            } elseif ($operator === 'gt') {
                $query->where($field, '>', $value);
            } elseif ($operator === 'lte') {
                $query->where($field, '<=', $value);
            } elseif ($operator === 'gte') {
                $query->where($field, '>=', $value);
            } elseif ($operator === 'like') {
                $query->where($field, 'like', $value);
            } elseif ($operator === 'in') {
                $query->whereIn($field, $value);
            } elseif ($operator === 'nin') {
                $query->whereNotIn($field, $value);
            } else {
                // handle relation filter type
                $tableName = $query->getModel()->{$field}()->getRelated()->getTable();
                $query->whereHas($field, function (Builder $query) use ($filterInput, $tableName) {
                    $this->applyFilterFieldsOnQuery($filterInput, $query, $tableName);
                });
            }
        }
    }

    public function get(): Collection
    {
        if ($this->limit) {
            $this->queryBuilder->limit($this->limit);
        }

        if ($this->offset) {
            $this->queryBuilder->offset($this->offset);
        }

        return $this->queryBuilder->get();
    }
}
