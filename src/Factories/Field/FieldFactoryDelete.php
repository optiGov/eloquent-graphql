<?php

namespace EloquentGraphQL\Factories\Field;

use Closure;
use EloquentGraphQL\Events\DeletedModelWithGraphQL;
use EloquentGraphQL\Events\DeletingModelWithGraphQL;
use EloquentGraphQL\Exceptions\GraphQLError;
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
            if (! $this->service->security()->check('delete', $this->model, [$model])) {
                throw new GraphQLError('You are not authorized to delete this model.');
            }

            DeletingModelWithGraphQL::dispatch($model);

            $success = $model->delete();

            if ($success) {
                DeletedModelWithGraphQL::dispatch($model);
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
