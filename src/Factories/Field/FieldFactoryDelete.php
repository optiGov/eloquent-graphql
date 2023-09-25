<?php

namespace EloquentGraphQL\Factories\Field;

use Closure;
use GraphQL\Type\Definition\Type;

class FieldFactoryDelete extends FieldFactory
{
    /**
     * Builds the return type for the field.
     */
    protected function buildReturnType(): Type
    {
        return Type::nonNull(Type::boolean());
    }

    /**
     * Builds the resolve function for the field.
     */
    protected function buildResolve(): Closure
    {
        return function ($_, $args) {
            $model = call_user_func("{$this->model}::find", $args['id']);

            // authorize
            if (!$this->service->security()->check("delete", $this->model, [$model])) {
                abort(403);
            }

            return $model->delete();
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
