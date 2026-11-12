<?php

namespace EloquentGraphQL\Factories\Pagination;

use EloquentGraphQL\Exceptions\GraphQLError;
use EloquentGraphQL\Security\Visibility;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PaginatorQuery extends Paginator
{
    /**
     * Operators that map 1:1 to a SQL comparison operator.
     */
    private const COMPARISON_OPERATORS = [
        'lt' => '<',
        'gt' => '>',
        'lte' => '<=',
        'gte' => '>=',
        'like' => 'like',
        'nlike' => 'not like',
    ];

    private const ALLOWED_ORDER_DIRECTIONS = ['asc', 'desc'];

    private Builder $queryBuilder;

    private ?Visibility $visibility = null;

    public function __construct(Builder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    public function count(): int
    {
        if ($this->constrain()->isNone()) {
            return 0;
        }

        return $this->queryBuilder->cloneWithout(['limit', 'offset'])->count();
    }

    // -----------------------------------------------------------------------
    // Public / hook API
    // -----------------------------------------------------------------------

    /** @throws GraphQLError */
    protected function applyFilter(array $filter): void
    {
        $this->applyFilterOnQuery($filter, $this->queryBuilder);
    }

    /** @throws GraphQLError */
    protected function applyOrder(array $order): void
    {
        $tableName = $this->queryBuilder->getModel()->getTable();
        $this->applyOrderOnQuery($order, $this->queryBuilder, $tableName);
    }

    public function getQueryBuilder(): Builder
    {
        $this->constrain();
        $this->applyLimitOffset($this->queryBuilder);

        return $this->queryBuilder;
    }

    public function get(): Collection
    {
        if ($this->entries) {
            return $this->entries;
        }

        if ($this->constrain()->isNone()) {
            return $this->queryBuilder->getModel()->newCollection();
        }

        $this->applyLimitOffset($this->queryBuilder);

        return $this->queryBuilder->get();
    }

    // -----------------------------------------------------------------------
    // Filter
    // -----------------------------------------------------------------------

    /** @throws GraphQLError */
    protected function applyFilterOnQuery(array $filter, Builder $query): void
    {
        if (Arr::exists($filter, 'and')) {
            $clauses = $this->removeDuplicates($filter['and']);
            $query->where(function (Builder $query) use ($clauses) {
                foreach ($clauses as $clause) {
                    $query->where(fn (Builder $q) => $this->applyFilterOnQuery($clause, $q));
                }
            });
        }

        if (Arr::exists($filter, 'or')) {
            $clauses = $this->removeDuplicates($filter['or']);
            $query->where(function (Builder $query) use ($clauses) {
                foreach ($clauses as $clause) {
                    $query->orWhere(fn (Builder $q) => $this->applyFilterOnQuery($clause, $q));
                }
            });
        }

        if (Arr::exists($filter, 'not')) {
            $query->whereNot(fn (Builder $q) => $this->applyFilterFieldsOnQuery($filter['not'], $q));
        }

        $this->applyFilterFieldsOnQuery($filter, $query);
    }

    /** @throws GraphQLError */
    private function applyFilterFieldsOnQuery(array $filter, Builder $query, ?string $tableName = null, int $level = 0): void
    {
        unset($filter['and'], $filter['or'], $filter['not']);

        foreach ($filter as $field => $operators) {
            $qualifiedField = $this->qualifyField($field, $tableName ?? $query->getModel()->getTable());

            foreach ($operators as $operator => $value) {
                if ($this->isRelationOperator($operator)) {
                    $this->applyRelationFilter($query, $field, $operators, $level);
                    break; // all sub-fields are handled inside applyRelationFilter at once
                }

                $this->applySingleOperator($query, $qualifiedField, $operator, $value);
            }
        }
    }

    /**
     * Returns true when $operator is not a known scalar filter keyword,
     * meaning the current field refers to a related model's filter input.
     */
    private function isRelationOperator(string $operator): bool
    {
        $scalarOperators = ['eq', 'ne', 'date', 'ndate', 'in', 'nin', ...array_keys(self::COMPARISON_OPERATORS)];

        return ! in_array($operator, $scalarOperators, strict: true);
    }

    /** @throws GraphQLError */
    private function applyRelationFilter(Builder $query, string $field, array $operators, int $level): void
    {
        if ($level >= 2) {
            throw new GraphQLError('Nested filtering is only allowed up to one level.');
        }

        $relatedTable = $query->getModel()->{$field}()->getRelated()->getTable();

        $query->whereHas($field, function (Builder $q) use ($operators, $relatedTable, $level) {
            $this->service->security()->constrain($q);
            $this->applyFilterFieldsOnQuery($operators, $q, $relatedTable, $level + 1);
        });
    }

    /** @throws GraphQLError */
    private function applySingleOperator(Builder $query, string $qualifiedField, string $operator, mixed $value): void
    {
        match ($operator) {
            'eq' => $value === null ? $query->whereNull($qualifiedField) : $query->where($qualifiedField, '=', $value),
            'ne' => $value === null ? $query->whereNotNull($qualifiedField) : $query->where($qualifiedField, '!=', $value),
            'date' => $query->whereDate($qualifiedField, $value),
            'ndate' => $query->whereDate($qualifiedField, '!=', $value),
            'in' => $query->whereIn($qualifiedField, $value),
            'nin' => $query->whereNotIn($qualifiedField, $value),
            default => isset(self::COMPARISON_OPERATORS[$operator])
                ? $query->where($qualifiedField, self::COMPARISON_OPERATORS[$operator], $value)
                : throw new GraphQLError("Unknown filter operator: '$operator'."),
        };
    }

    protected function removeDuplicates(array $array): array
    {
        if (count($array) <= 1) {
            return $array;
        }

        return array_map('unserialize', array_unique(array_map('serialize', $array)));
    }

    // -----------------------------------------------------------------------
    // Order
    // -----------------------------------------------------------------------

    /** @throws GraphQLError */
    private function applyOrderOnQuery(array $order, Builder $query, ?string $tableName = null, int $level = 0, ?Model $baseModel = null): void
    {
        if (count($order) > 1) {
            throw new GraphQLError('Order must have exactly one field.');
        }

        foreach ($order as $field => $orderInput) {
            if (Arr::has($orderInput, 'order')) {
                $this->applyDirectOrder($query, $field, $orderInput, $tableName);
            } else {
                $this->applyRelationOrder($query, $field, $orderInput, $level, $baseModel);
            }
        }
    }

    /** @throws GraphQLError */
    private function applyDirectOrder(Builder $query, string $field, array $orderInput, ?string $tableName): void
    {
        $direction = $this->resolveDirection($orderInput['order']);
        $qualifiedField = $this->qualifyField($field, $tableName);

        $query->orderBy($qualifiedField, $direction);
    }

    /** @throws GraphQLError */
    private function applyRelationOrder(Builder $query, string $field, array $orderInput, int $level, ?Model $baseModel): void
    {
        if ($level >= 2) {
            throw new GraphQLError('Nested ordering is only allowed up to one level.');
        }

        foreach ($orderInput as $innerField => $innerOrderInput) {
            if (! Arr::has($innerOrderInput, 'order')) {
                throw new GraphQLError('Nested ordering is only allowed up to one level.');
            }

            $direction = $this->resolveDirection($innerOrderInput['order']);

            $query->orderBy($this->buildRelationOrderQuery($query, $field, $innerField, $baseModel), $direction);
        }
    }

    /**
     * Builds a correlated subquery that selects a column of the related model, restricted to the models the user can
     * view, so that the order does not reveal anything about hidden models.
     */
    private function buildRelationOrderQuery(Builder $query, string $field, string $innerField, ?Model $baseModel): Builder
    {
        $model = $baseModel ?? $query->getModel();
        $relation = $model->{$field}();
        $related = $relation->getRelated();

        $orderQuery = $relation
            ->getRelationExistenceQuery($related->newQuery(), $model->newQuery(), [$related->qualifyColumn($innerField)])
            ->limit(1);

        return $this->service->security()->constrain($orderQuery);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Validates and normalises an order direction string.
     *
     * @throws GraphQLError
     */
    private function resolveDirection(string $raw): string
    {
        $direction = Str::lower($raw);

        if (! in_array($direction, self::ALLOWED_ORDER_DIRECTIONS, strict: true)) {
            throw new GraphQLError(
                'Order direction must be one of ['.implode(', ', self::ALLOWED_ORDER_DIRECTIONS).']'
            );
        }

        return $direction;
    }

    /**
     * Qualifies a bare column name with its table, if a table name is given.
     */
    private function qualifyField(string $field, ?string $tableName): string
    {
        return $tableName ? $tableName.'.'.$field : $field;
    }

    /**
     * Restricts the query to the models the user can view. Applied once before the query is executed, so that
     * filters and counts do not consider models the user cannot view.
     */
    private function constrain(): Visibility
    {
        if ($this->visibility === null) {
            $this->visibility = $this->service->security()->getVisibility($this->className);
            $this->service->security()->constrain($this->queryBuilder, $this->visibility);
        }

        return $this->visibility;
    }

    /**
     * Applies the current limit and offset to the given query builder.
     */
    private function applyLimitOffset(Builder $query): void
    {
        if ($this->limit) {
            $query->limit($this->limit);
        }

        if ($this->offset) {
            $query->offset($this->offset);
        }
    }
}
