<?php

namespace EloquentGraphQL\Factories\FieldFactories;

use Closure;
use EloquentGraphQL\Exceptions\EloquentGraphQLException;
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
        return $this->service->typeFactory($this->model)->buildList();
    }

    /**
     * Builds the resolve function for the field.
     */
    protected function buildResolve(): Closure
    {
        return function () {
            // check if any entry can be viewed
            $this->service->security()->assertCanViewAny($this->model);

            // get entries
            $entries = call_user_func("{$this->model}::all");

            // filter entries
            return $this->service->security()->filterViewable($entries);
        };
    }

    /**
     * Builds the arguments for the field.
     */
    protected function buildArgs(): array
    {
        return [];
    }
}
