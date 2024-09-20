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
            'isRelation' => true,
            'eagerLoadDisabled' => $this->property->isEagerLoadDisabled(),
            'type' => $this->getType(),
            'args' => $this->getArgs(),
            'resolve' => function ($parent, $args) {
                // authorize
                $this->service->security()->assertCanViewProperty($parent, $this->property);

                // check if user can view any entry
                $this->service->security()->assertCanViewAny($this->model);

                // get args
                $limit = $args['limit'] ?? null;
                $offset = $args['offset'] ?? null;
                $filter = $args['filter'] ?? null;
                $order = $args['order'] ?? null;

                // get entries
                $fieldIsMethod = method_exists($parent, $this->fieldName);
                $needToBuildQuery = $limit || $offset || $filter || $order;

                // only resolve query builder if we need to paginate or filter
                if ($fieldIsMethod && $needToBuildQuery) {
                    $builderOrIterable = $parent->{$this->fieldName}();
                } else {
                    $builderOrIterable = $parent->{$this->fieldName};
                }

                // build paginator
                if ($builderOrIterable instanceof Builder) {
                    // use table.* to prevent issues with joins
                    $builderOrIterable->select($builderOrIterable->getModel()->getTable().'.*');
                    $paginator = new PaginatorQuery($builderOrIterable);
                } else {
                    $paginator = new PaginatorIterable();
                    $paginator->setEntries($builderOrIterable);
                }

                // set limit and offset
                $paginator
                    ->className($this->property->getType())
                    ->service($this->service)
                    ->limit($limit)
                    ->offset($offset)
                    ->filter($filter)
                    ->order($order);

                // return paginator or filtered entries
                if ($this->property->hasPagination()) {
                    $relationEagerLoaded = $parent->relationLoaded($this->fieldName);
                    if ($relationEagerLoaded) {
                        $eagerLoadedEntries = $parent->{$this->fieldName};
                        $paginator->setEntries($eagerLoadedEntries);
                    }

                    return $paginator;
                } else {
                    return $this->service->security()->assertCanViewAll($paginator->get());
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

    /**
     * @throws ReflectionException
     * @throws EloquentGraphQLException
     */
    private function getArgs(): array
    {
        $args = new Collection();

        $args = $args
            ->merge($this->getArgsPagination())
            ->merge($this->getArgsFilter())
            ->merge($this->getArgsOrder());

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

    /**
     * @throws ReflectionException
     * @throws EloquentGraphQLException
     */
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

    /**
     * @throws ReflectionException
     */
    private function getArgsOrder(): Collection
    {
        if (! $this->property->hasOrder()) {
            return new Collection();
        }

        $factory = $this->service->typeFactory($this->property->getType());

        return new Collection([
            'order' => [
                'type' => $factory->buildOrder(),
            ],
        ]);
    }
}
