<?php

namespace EloquentGraphQL\Factories\Pagination;

use Closure;
use EloquentGraphQL\Exceptions\GraphQLError;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
        // apply `and` concatenated filters
        $anyFilterApplied = false;
        if (Arr::exists($filter, 'and')) {
            foreach ($filter['and'] as $filterLevel) {
                $query->where(function (Builder $query) use ($filterLevel) {
                    $this->applyFilterOnQuery($filterLevel, $query);
                });
                $anyFilterApplied = true;
            }
        }

        // apply `or` concatenated filters
        if (Arr::exists($filter, 'or')) {
            // if no `and` filter was applied, start with `where` instead of `orWhere`
            $method = $anyFilterApplied
                ? fn (Closure $callback) => $query->orWhere($callback)
                : fn (Closure $callback) => $query->where($callback);

            foreach ($filter['or'] as $filterLevel) {
                $method(function (Builder $query) use ($filterLevel) {
                    $this->applyFilterOnQuery($filterLevel, $query);
                });

                // after the first `or` filter was applied, use `orWhere` for the rest
                $method = fn (Closure $callback) => $query->orWhere($callback);
            }
        }

        // apply filters on current level
        $this->applyFilterFieldsOnQuery($filter, $query);
    }

    /**
     * @throws GraphQLError
     */
    private function applyFilterFieldsOnQuery(array $filter, Builder $query, string $tableName = null, int $level = 0): void
    {
        unset($filter['and']);
        unset($filter['or']);

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
                if ($level >= 1) {
                    throw new GraphQLError('Nested filtering is only allowed up to one level.');
                }

                // handle relation filter type
                $tableName = $query->getModel()->{$field}()->getRelated()->getTable();
                $query->whereHas($field, function (Builder $query) use ($filterInput, $tableName, $level) {
                    $this->applyFilterFieldsOnQuery($filterInput, $query, $tableName, $level + 1);
                });
            }
        }
    }

    /**
     * @throws GraphQLError
     */
    protected function applyOrder(array $order): void
    {
        $tableName = $this->queryBuilder->getModel()->getTable();
        $this->applyOrderOnQuery($order, $this->queryBuilder, $tableName);
    }

    /**
     * @throws GraphQLError
     */
    private function applyOrderOnQuery(array $order, Builder $query, string $tableName = null, int $level = 0): void
    {
        if (count($order) > 1) {
            throw new GraphQLError('Order must have exactly one field.');
        }

        $allowedDirections = ['asc', 'desc'];

        foreach ($order as $field => $orderInput) {

            if (Arr::has($orderInput, 'order')) {
                $direction = Str::lower($orderInput['order']);

                if (! in_array($direction, $allowedDirections)) {
                    throw new GraphQLError('Order direction must be one of ['.implode(', ', $allowedDirections).']');
                }

                if ($tableName) {
                    $field = $tableName.'.'.$field;
                }

                $query->orderBy($field, $direction);
            } else {
                if ($level >= 1) {
                    throw new GraphQLError('Nested ordering is only allowed up to one level.');
                }

                // handle relation order type
                $relation = $query->getModel()->{$field}();
                $parentTable = $query->getModel()->getTable();
                $foreignTable = $relation->getRelated()->getTable();
                $parentKey = $relation->getForeignKeyName();
                $foreignKey = $relation->getParent()->getKeyName();

                $query->join($foreignTable, $parentTable.'.'.$parentKey, '=', $foreignTable.'.'.$foreignKey);
                $this->applyOrderOnQuery($orderInput, $query, $foreignTable, $level + 1);
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
