<?php

namespace EloquentGraphQL\Factories\Pagination;

class PaginatorIterable extends Paginator
{
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function count(): int
    {
        return count($this->data);
    }

    public function get(): array
    {
        return array_slice($this->data, $this->offset ?? 0, $this->limit);
    }
}
