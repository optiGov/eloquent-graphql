<?php

namespace EloquentGraphQL\Factories\Type\Field;

use EloquentGraphQL\Reflection\ReflectionProperty;
use EloquentGraphQL\Services\EloquentGraphQLService;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;

abstract class FieldFactory
{
    protected EloquentGraphQLService $service;

    protected string $fieldName;

    protected ReflectionProperty $property;

    protected string $model;

    public function __construct(EloquentGraphQLService $service)
    {
        $this->service = $service;
    }

    public function setFieldName(string $fieldName): FieldFactory
    {
        $this->fieldName = $fieldName;

        return $this;
    }

    public function setProperty(ReflectionProperty $property): FieldFactory
    {
        $this->property = $property;

        return $this;
    }

    public function setModel(string $model): FieldFactory
    {
        $this->model = $model;

        return $this;
    }

    abstract public function build(): array;

    abstract protected function getType(): NonNull|ObjectType|ScalarType;
}
