<?php

namespace EloquentGraphQL\Factories\FieldFactories;

use Closure;
use EloquentGraphQL\Events\GraphQLDuplicatedModel;
use EloquentGraphQL\Events\GraphQLDuplicatingModel;
use EloquentGraphQL\Exceptions\EloquentGraphQLException;
use GraphQL\Type\Definition\Type;
use Illuminate\Database\Eloquent\Model;
use ReflectionException;

class FieldFactoryDuplicate extends FieldFactory
{
    /**
     * @throws EloquentGraphQLException|ReflectionException
     */
    protected function buildReturnType(): Type
    {
        return $this->service->typeFactory($this->model)->buildNonNull();
    }

    protected function buildResolve(): Closure
    {
        return function ($_, $args) {
            /** @var Model $model */
            $model = call_user_func("$this->model::find", $args['id']);

            if (! $model) {
                return false;
            }

            $this->service->security()->assertCanDuplicate($model);

            GraphQLDuplicatingModel::dispatch($model);

            $duplicate = $model->replicate();
            $duplicate->save();

            GraphQLDuplicatedModel::dispatch($duplicate);

            return $duplicate;
        };
    }

    protected function buildArgs(): array
    {
        return [
            'id' => [
                'type' => Type::nonNull(Type::int()),
            ],
        ];
    }
}
