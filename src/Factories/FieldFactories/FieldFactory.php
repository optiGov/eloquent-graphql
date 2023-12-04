<?php

namespace EloquentGraphQL\Factories\FieldFactories;

use Closure;
use EloquentGraphQL\Exceptions\EloquentGraphQLException;
use EloquentGraphQL\Services\EloquentGraphQLService;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;
use ReflectionException;

abstract class FieldFactory
{
    /**
     * Name of the GraphQL field. E.g. "deleteUser".
     */
    private string $name;

    /**
     * Pure name of the GraphQL field. E.g. "user".
     */
    protected string $pureName;

    /**
     * Description of the GraphQL field.
     */
    private string $description = '';

    /**
     * Model class name.
     */
    protected string $model;

    /**
     * EloquentGraphQLService instance for resolving other type factories.
     */
    protected EloquentGraphQLService $service;

    /**
     * Creates a new FieldFactory.
     */
    public function __construct(EloquentGraphQLService $service)
    {
        $this->service = $service;
    }

    public function name(string $name): FieldFactory
    {
        $this->name = $name;

        return $this;
    }

    public function description(string $description): FieldFactory
    {
        $this->description = $description;

        return $this;
    }

    public function model(string $model): FieldFactory
    {
        $this->model = $model;

        return $this;
    }

    public function pureName(string $pureName): FieldFactory
    {
        $this->pureName = strtolower($pureName);

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function build(): array
    {
        return [
            'type' => $this->buildReturnType(),
            'description' => $this->getDescription(),
            'args' => $this->buildArgs(),
            'resolve' => $this->buildResolve(),
        ];
    }

    /**
     * Builds the return type for the field.
     *
     * @throws ReflectionException|EloquentGraphQLException
     */
    private function buildInputType(): InputObjectType
    {
        return $this->service->typeFactory($this->model)->buildInput();
    }

    /**
     * Builds the resolve function for the field.
     */
    abstract protected function buildReturnType(): Type;

    /**
     * Builds the resolve function for the field.
     */
    abstract protected function buildResolve(): Closure;

    /**
     * Builds the arguments for the field.
     */
    abstract protected function buildArgs(): array;
}
