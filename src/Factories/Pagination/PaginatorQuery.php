<?php

namespace EloquentGraphQL\Factories\Pagination;

use Illuminate\Contracts\Database\Query\Builder;
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

    public function get(): Collection
    {
        $clone = $this->queryBuilder->clone();

        if ($this->limit) {
            $clone->limit($this->limit);
        }

        if ($this->offset) {
            $clone->offset($this->offset);
        }

        return $clone->get();
    }
}
