<?php

namespace EloquentGraphQL\Factories\Pagination;

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
        if (Arr::exists($filter, 'and')) {
            $query->where(function (Builder $query) use ($filter) {
                foreach ($filter['and'] as $filterLevel) {
                    $query->where(function (Builder $query) use ($filterLevel) {
                        $this->applyFilterOnQuery($filterLevel, $query);
                    });
                }
            });
        }

        // apply `or` concatenated filters
        if (Arr::exists($filter, 'or')) {
            $query->where(function (Builder $query) use ($filter) {
                foreach ($filter['or'] as $filterLevel) {
                    $query->orWhere(function (Builder $query) use ($filterLevel) {
                        $this->applyFilterOnQuery($filterLevel, $query);
                    });
                }
            });
        }

        // apply `not` concatenated filters
        if (Arr::exists($filter, 'not')) {
            $query->whereNot(function (Builder $query) use ($filter) {
                $this->applyFilterFieldsOnQuery($filter['not'], $query);
            });
        }

        // apply filters on current level
        $this->applyFilterFieldsOnQuery($filter, $query);
    }

    /**
     * @throws GraphQLError
     */
    private function applyFilterFieldsOnQuery(array $filter, Builder $query, ?string $tableName = null, int $level = 0): void
    {
        unset($filter['and']);
        unset($filter['or']);
        unset($filter['not']);

        foreach ($filter as $field => $filterInput) {
            $qualifiedField = $tableName ? $tableName.'.'.$field : $field;
            foreach ($filterInput as $operator => $value) {
                if ($operator === 'eq') {
                    if ($value === null) {
                        $query->whereNull($qualifiedField);
                    } else {
                        $query->where($qualifiedField, '=', $value);
                    }
                } elseif ($operator === 'ne') {
                    $query->where($qualifiedField, '!=', $value);
                } elseif ($operator === 'lt') {
                    $query->where($qualifiedField, '<', $value);
                } elseif ($operator === 'gt') {
                    $query->where($qualifiedField, '>', $value);
                } elseif ($operator === 'lte') {
                    $query->where($qualifiedField, '<=', $value);
                } elseif ($operator === 'gte') {
                    $query->where($qualifiedField, '>=', $value);
                } elseif ($operator === 'like') {
                    $query->where($qualifiedField, 'like', $value);
                } elseif ($operator === 'in') {
                    $query->whereIn($qualifiedField, $value);
                } elseif ($operator === 'nin') {
                    $query->whereNotIn($qualifiedField, $value);
                } else {
                    if ($level >= 1) {
                        throw new GraphQLError('Nested filtering is only allowed up to one level.');
                    }

                    // handle relation filter type
                    $tableName = $query->getModel()->{$field}()->getRelated()->getTable();
                    $query->whereHas($qualifiedField, function (Builder $query) use ($filterInput, $tableName, $level) {
                        $this->applyFilterFieldsOnQuery($filterInput, $query, $tableName, $level + 1);
                    });
                }
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
    private function applyOrderOnQuery(array $order, Builder $query, ?string $tableName = null, int $level = 0): void
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
