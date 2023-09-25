<?php

namespace EloquentGraphQL\Factories\Field;

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
        return Type::nonNull(
            Type::listOf(
                Type::nonNull(
                    $this->service->typeFactory($this->model)->build()
                )
            )
        );
    }

    /**
     * Builds the resolve function for the field.
     */
    protected function buildResolve(): Closure
    {
        return function () {
            // authorize
            if (!$this->service->security()->check("viewAny", $this->model)) {
                abort(403);
            }

            return call_user_func("{$this->model}::all");
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
