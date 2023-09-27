<?php

namespace EloquentGraphQL\Factories\Field;

use Closure;
use EloquentGraphQL\Exceptions\EloquentGraphQLException;
use EloquentGraphQL\Exceptions\GraphQLError;
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
            $entries = call_user_func("{$this->model}::all");

            // authorize entries
            foreach ($entries as $entry) {
                if (! $this->service->security()->check('view', $this->model, [$entry])) {
                    throw new GraphQLError('You are not authorized to view this model.');
                }
            }

            return $entries;
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
