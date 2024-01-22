<?php

namespace EloquentGraphQL\Factories\FieldFactories;

use Closure;
use EloquentGraphQL\Exceptions\EloquentGraphQLException;
use EloquentGraphQL\Factories\Pagination\PaginatorQuery;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Collection;
use ReflectionException;

class FieldFactoryAll extends FieldFactory
{
    protected bool $filterable = true;

    protected bool $orderable = true;

    protected bool $paginate = true;

    public function filterable(bool $filter): static
    {
        $this->filterable = $filter;

        return $this;
    }

    public function orderable(bool $order): static
    {
        $this->orderable = $order;

        return $this;
    }

    public function paginate(bool $paginate): static
    {
        $this->paginate = $paginate;

        return $this;
    }

    /**
     * Builds the return type for the field.
     *
     * @throws EloquentGraphQLException|ReflectionException
     */
    protected function buildReturnType(): Type
    {
        return $this->service->typeFactory($this->model)->buildListPaginated();
    }

    /**
     * Builds the resolve function for the field.
     */
    protected function buildResolve(): Closure
    {
        return function ($parent, $args) {
            // check if any entry can be viewed
            $this->service->security()->assertCanViewAny($this->model);

            // get args
            $limit = $args['limit'] ?? null;
            $offset = $args['offset'] ?? null;
            $filter = $args['filter'] ?? null;
            $order = $args['order'] ?? null;

            // get entries
            $builder = call_user_func("{$this->model}::query");

            // use table.* to prevent issues with joins
            $builder->select($builder->getModel()->getTable().'.*');
            $paginator = new PaginatorQuery($builder);

            // set limit and offset
            $paginator
                ->className($this->model)
                ->service($this->service)
                ->limit($limit)
                ->offset($offset)
                ->filter($filter)
                ->order($order);

            // filter entries
            return $paginator;
        };
    }

    /**
     * Builds the arguments for the field.
     *
     * @throws ReflectionException
     * @throws EloquentGraphQLException
     */
    protected function buildArgs(): array
    {
        $factory = $this->service->typeFactory($this->model);

        $args = new Collection([]);

        if ($this->paginate) {
            $args->put('limit', Type::int());
            $args->put('offset', Type::int());
        }

        if ($this->filterable) {
            $args->put('filter', $factory->buildFilter());
        }

        if ($this->orderable) {
            $args->put('order', $factory->buildOrder());
        }

        return $args->toArray();
    }
}
