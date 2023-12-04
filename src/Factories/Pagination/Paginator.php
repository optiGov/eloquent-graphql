<?php

namespace EloquentGraphQL\Factories\Pagination;

abstract class Paginator
{
    protected ?int $offset = null;

    protected ?int $limit = null;

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

    abstract public function count(): int;

    abstract public function get(): mixed;
}
