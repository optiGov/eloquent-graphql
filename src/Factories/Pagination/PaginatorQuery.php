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
        if ($this->limit) {
            $this->queryBuilder->limit($this->limit);
        }

        if ($this->offset) {
            $this->queryBuilder->offset($this->offset);
        }

        return $this->queryBuilder->get();
    }
}
