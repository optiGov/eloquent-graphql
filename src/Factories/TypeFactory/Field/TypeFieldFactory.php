<?php

namespace EloquentGraphQL\Factories\TypeFactory\Field;

use EloquentGraphQL\Reflection\ReflectionProperty;
use EloquentGraphQL\Services\EloquentGraphQLService;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;

abstract class TypeFieldFactory
{
    protected EloquentGraphQLService $service;

    protected string $fieldName;

    protected ReflectionProperty $property;

    protected string $model;

    public function __construct(EloquentGraphQLService $service)
    {
        $this->service = $service;
    }

    public function setFieldName(string $fieldName): TypeFieldFactory
    {
        $this->fieldName = $fieldName;

        return $this;
    }

    public function setProperty(ReflectionProperty $property): TypeFieldFactory
    {
        $this->property = $property;

        return $this;
    }

    public function setModel(string $model): TypeFieldFactory
    {
        $this->model = $model;

        return $this;
    }

    abstract public function build(): array;

    abstract protected function getType(): NonNull|ObjectType|ScalarType;
}
