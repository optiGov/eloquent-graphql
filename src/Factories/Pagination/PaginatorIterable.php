<?php

namespace EloquentGraphQL\Factories\Pagination;

use Exception;
use Illuminate\Support\Collection;

class PaginatorIterable extends Paginator
{

    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * @throws Exception
     */
    public function get(): array
    {
        if ($this->entries instanceof Collection) {
            return $this->entries->slice($this->offset ?? 0, $this->limit)->all();
        } elseif (is_array($this->entries)) {
            return array_slice($this->entries, $this->offset ?? 0, $this->limit);
        }

        throw new Exception('Unsupported iterable type.');
    }
}
