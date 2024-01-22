<?php

namespace EloquentGraphQL\Factories\FieldFactories;

use Closure;
use EloquentGraphQL\Exceptions\EloquentGraphQLException;
use EloquentGraphQL\Factories\Pagination\PaginatorQuery;
use GraphQL\Type\Definition\Type;
use ReflectionException;

class FieldFactoryAll extends FieldFactory
{
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

        return [
            'limit' => [
                'type' => Type::int(),
            ],
            'offset' => [
                'type' => Type::int(),
            ],
            'filter' => [
                'type' => $factory->buildFilter(),
            ],
            'order' => [
                'type' => $factory->buildOrder(),
            ],
        ];
    }
}
