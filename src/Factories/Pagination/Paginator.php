<?php

namespace EloquentGraphQL\Factories\Pagination;

use EloquentGraphQL\Exceptions\GraphQLError;
use EloquentGraphQL\Reflection\ReflectionInspector;
use EloquentGraphQL\Reflection\ReflectionProperty;
use EloquentGraphQL\Services\EloquentGraphQLService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Arr;
use ReflectionException;

abstract class Paginator
{
    protected ?int $offset = null;

    protected ?int $limit = null;

    protected ?array $filter = null;

    protected ?array $order = null;

    protected EloquentGraphQLService $service;

    protected mixed $entries = null;

    protected string $className;

    public function offset(?int $n): static
    {
        $this->offset = $n;

        return $this;
    }

    public function noOffset(): static
    {
        $this->offset = null;

        return $this;
    }

    public function limit(?int $n): static
    {
        $this->limit = $n;

        return $this;
    }

    public function noLimit(): static
    {
        $this->limit = null;

        return $this;
    }

    /**
     * @throws GraphQLError
     */
    public function filter(?array $filter): static
    {
        $this->filter = $filter;

        if ($filter) {
            $this->verifyFilter($filter, $this->className);
            $this->applyFilter($filter);
        }

        return $this;
    }

    /**
     * @throws GraphQLError
     */
    public function order(?array $order): static
    {
        $this->order = $order;

        if ($order) {
            $this->applyOrder($order);
        }

        return $this;
    }

    public function service(EloquentGraphQLService $service): static
    {
        $this->service = $service;

        return $this;
    }

    public function className(string $className): static
    {
        $this->className = $className;

        return $this;
    }

    /**
     * @throws BindingResolutionException
     * @throws GraphQLError|ReflectionException
     */
    protected function verifyFilter(array $filter, string $className): void
    {
        // verify model and it's properties are filterable
        $this->service->security()->assertCanFilter($className, $filter);

        $properties = ReflectionInspector::getPropertiesFromClassDoc($className);
        $properties
            ->filter(fn (ReflectionProperty $property) => ! $property->isPrimitiveType())
            ->each(function (ReflectionProperty $property) use ($filter) {
                if (Arr::exists($filter, $property->getName())) {
                    $this->verifyFilter($filter[$property->getName()], $property->getType());
                }
            });

        // verify all 'and' and 'or' filters are filterable
        if (Arr::exists($filter, 'and')) {
            foreach ($filter['and'] as $andFilter) {
                $this->verifyFilter($andFilter, $className);
            }
        }
        if (Arr::exists($filter, 'or')) {
            foreach ($filter['or'] as $orFilter) {
                $this->verifyFilter($orFilter, $className);
            }
        }
    }

    /**
     * @throws GraphQLError
     */
    protected function applyFilter(array $filter): void
    {
        throw new GraphQLError('Filtering is not supported for this paginator.');
    }

    /**
     * @throws GraphQLError
     */
    protected function applyOrder(array $order): void
    {
        throw new GraphQLError('Ordering is not supported for this paginator.');
    }

    public function setEntries(mixed $entries): static
    {
        $this->entries = $entries;

        return $this;
    }

    abstract public function count(): int;

    abstract public function get(): mixed;
}
