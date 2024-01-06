<?php

namespace EloquentGraphQL\Factories\TypeFactory\Field;

use EloquentGraphQL\Exceptions\EloquentGraphQLException;
use EloquentGraphQL\Factories\Pagination\PaginatorIterable;
use EloquentGraphQL\Factories\Pagination\PaginatorQuery;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Collection;
use ReflectionException;

class TypeFieldFactoryHasMany extends TypeFieldFactory
{
    /**
     * @throws ReflectionException
     * @throws EloquentGraphQLException
     */
    public function build(): array
    {
        return [
            'type' => $this->getType(),
            'args' => $this->getArgs(),
            'resolve' => function ($parent, $args) {
                // authorize
                $this->service->security()->assertCanViewProperty($parent, $this->property);

                // check if user can view any entry
                $this->service->security()->assertCanViewAny($this->model);

                // get entries
                if (method_exists($parent, $this->fieldName)) {
                    $builderOrIterable = $parent->{$this->fieldName}();
                } else {
                    $builderOrIterable = $parent->{$this->fieldName};
                }

                // build paginator
                if ($builderOrIterable instanceof Builder) {
                    $paginator = new PaginatorQuery($builderOrIterable);
                } else {
                    $paginator = new PaginatorIterable($builderOrIterable);
                }

                // set limit and offset
                $paginator
                    ->limit($args['limit'] ?? null)
                    ->offset($args['offset'] ?? null);

                // return paginator or filtered entries
                if ($this->property->hasPagination()) {
                    return $paginator;
                } else {
                    return $this->service->security()->filterViewable($paginator->get());
                }
            },
        ];
    }

    /**
     * @throws ReflectionException
     * @throws EloquentGraphQLException
     */
    protected function getType(): NonNull|ObjectType|ScalarType
    {
        $factory = $this->service->typeFactory($this->property->getType());

        if ($this->property->hasPagination()) {
            return $factory->buildListPaginated();
        } else {
            return $factory->buildList();
        }
    }

    private function getArgs(): array
    {
        $args = new Collection();

        $args = $args->merge($this->getArgsPagination());
        $args = $args->merge($this->getArgsFilter());

        return $args->toArray();
    }

    private function getArgsPagination(): Collection
    {
        if (! $this->property->hasPagination()) {
            return new Collection();
        }

        return new Collection([
            'limit' => [
                'type' => Type::int(),
            ],
            'offset' => [
                'type' => Type::int(),
            ],
        ]);
    }

    private function getArgsFilter(): Collection
    {
        if (! $this->property->hasFilters()) {
            return new Collection();
        }

        $factory = $this->service->typeFactory($this->property->getType());

        return new Collection([
            'filter' => [
                'type' => $factory->buildFilter(),
            ],
        ]);
    }
}
