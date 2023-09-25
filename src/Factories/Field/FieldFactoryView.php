<?php

namespace EloquentGraphQL\Factories\Field;

use Closure;
use EloquentGraphQL\Exceptions\EloquentGraphQLException;
use GraphQL\Type\Definition\Type;
use ReflectionException;

class FieldFactoryView extends FieldFactory
{
    /**
     * Builds the return type for the field.
     *
     * @throws EloquentGraphQLException|ReflectionException
     */
    protected function buildReturnType(): Type
    {
        return $this->service->typeFactory($this->model)->build();
    }

    /**
     * Builds the resolve function for the field.
     */
    protected function buildResolve(): Closure
    {
        return function ($_, array $args) {
            $entry = call_user_func("{$this->model}::find", $args['id']);

            // authorize
            if (!$this->service->security()->check("view", $this->model, [$entry])) {
                abort(403);
            }

            return $entry;
        };
    }

    /**
     * Builds the arguments for the field.
     */
    protected function buildArgs(): array
    {
        return [
            'id' => [
                'type' => Type::nonNull(Type::int()),
            ],
        ];
    }
}
