<?php

namespace EloquentGraphQL\Factories\FieldFactories;

use Closure;
use EloquentGraphQL\Events\GraphQLDeletedModel;
use EloquentGraphQL\Events\GraphQLDeletingModel;
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

            // return false if model does not exist
            if (! $model) {
                return false;
            }

            // authorize
            $this->service->security()->assertCanDelete($model);

            // dispatch deleting event
            GraphQLDeletingModel::dispatch($model);

            // delete model
            $success = $model->delete();

            // dispatch deleted event
            if ($success) {
                GraphQLDeletedModel::dispatch($model);
            }

            return $success;
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
