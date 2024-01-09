<?php

namespace EloquentGraphQL\Factories\Pagination;

use EloquentGraphQL\Exceptions\GraphQLError;

abstract class Paginator
{
    protected ?int $offset = null;

    protected ?int $limit = null;

    protected ?array $filter = null;

    protected ?array $order = null;

    public function offset(?int $n): static
    {
        $this->offset = $n;

        return $this;
    }

    public function limit(?int $n): static
    {
        $this->limit = $n;

        return $this;
    }

    /**
     * @throws GraphQLError
     */
    public function filter(?array $filter): static
    {
        $this->filter = $filter;

        if ($filter) {
            $this->applyFilter($filter);
        }

        return $this;
    }

    /**
     * @throws GraphQLError
     */
    public function order(?array $order): static
    {
        $this->order = $order;

        if ($order) {
            $this->applyOrder($order);
        }

        return $this;
    }

    public function noOffset(): static
    {
        $this->offset = null;

        return $this;
    }

    public function noLimit(): static
    {
        $this->limit = null;

        return $this;
    }

    /**
     * @throws GraphQLError
     */
    protected function applyFilter(array $filter): void
    {
        throw new GraphQLError('Filtering is not supported for this paginator.');
    }

    /**
     * @throws GraphQLError
     */
    protected function applyOrder(array $order): void
    {
        throw new GraphQLError('Ordering is not supported for this paginator.');
    }

    abstract public function count(): int;

    abstract public function get(): mixed;
}
