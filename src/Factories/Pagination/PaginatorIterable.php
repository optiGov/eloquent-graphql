<?php

namespace EloquentGraphQL\Factories\Pagination;

use Exception;
use Illuminate\Support\Collection;

class PaginatorIterable extends Paginator
{
    private array|Collection $data;

    public function __construct(array|Collection $data)
    {
        $this->data = $data;
    }

    public function count(): int
    {
        return count($this->data);
    }

    /**
     * @throws Exception
     */
    public function get(): array
    {
        if ($this->data instanceof Collection) {
            return $this->data->slice($this->offset ?? 0, $this->limit)->all();
        } elseif (is_array($this->data)) {
            return array_slice($this->data, $this->offset ?? 0, $this->limit);
        }

        throw new Exception('Unsupported iterable type.');
    }
}
