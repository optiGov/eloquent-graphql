<?php

namespace EloquentGraphQL\Factories\FieldFactories;

use Closure;
use EloquentGraphQL\Events\GraphQLDuplicatedModel;
use EloquentGraphQL\Events\GraphQLDuplicatingModel;
use EloquentGraphQL\Exceptions\EloquentGraphQLException;
use GraphQL\Type\Definition\Type;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use ReflectionException;

class FieldFactoryDuplicate extends FieldFactory
{
    /**
     * @throws EloquentGraphQLException|ReflectionException
     */
    protected function buildReturnType(): Type
    {
        return $this->service->typeFactory($this->model)->build();
    }

    protected function buildResolve(): Closure
    {
        return function ($_, $args) {
            /** @var Model $model */
            $model = call_user_func("{$this->model}::find", $args['id']);

            // return null if model does not exist
            if (! $model) {
                return null;
            }

            $relations = $args['relations'] ?? [];

            $this->service->security()->assertCanDuplicate($model);
            $this->assertRelationsCanBeDuplicated($model, $relations);

            GraphQLDuplicatingModel::dispatch($model);

            $duplicate = DB::transaction(function () use ($model, $relations) {
                $duplicate = $model->replicate();
                $duplicate->save();

                $this->duplicateRelations($model, $duplicate, $relations);

                return $duplicate;
            });

            GraphQLDuplicatedModel::dispatch($duplicate);

            return $duplicate;
        };
    }

    /**
     * Authorizes each requested relation by asserting that every related model may be
     * duplicated on its own policy.
     *
     * @param  string[]  $relations
     *
     * @throws EloquentGraphQLException
     */
    protected function assertRelationsCanBeDuplicated(Model $model, array $relations): void
    {
        foreach ($relations as $relationName) {
            if (! method_exists($model, $relationName)) {
                throw new EloquentGraphQLException("Relation '$relationName' does not exist on the model.");
            }

            if (! $model->{$relationName}() instanceof Relation) {
                throw new EloquentGraphQLException("'$relationName' is not a relation and cannot be duplicated.");
            }

            $model->{$relationName}()->get()->each(
                fn (Model $relatedModel) => $this->service->security()->assertCanDuplicate($relatedModel)
            );
        }
    }

    /**
     * Duplicates the given relations onto the freshly created duplicate.
     *
     * Note: relations are only duplicated one level deep — the related models'
     * own relations are not recursively duplicated.
     *
     * @param  string[]  $relations
     *
     * @throws EloquentGraphQLException
     */
    protected function duplicateRelations(Model $model, Model $duplicate, array $relations): void
    {
        foreach ($relations as $relationName) {
            if (! method_exists($model, $relationName)) {
                throw new EloquentGraphQLException("Relation '$relationName' does not exist on the model.");
            }

            $relation = $model->{$relationName}();

            if (! $relation instanceof Relation) {
                throw new EloquentGraphQLException("'$relationName' is not a relation and cannot be duplicated.");
            }

            if ($relation instanceof HasOneOrMany) {
                $foreignKeyName = $relation->getForeignKeyName();

                $model->{$relationName}()->get()->each(function (Model $related) use ($duplicate, $foreignKeyName) {
                    $relatedDuplicate = $related->replicate();
                    $relatedDuplicate->{$foreignKeyName} = $duplicate->getKey();
                    $relatedDuplicate->save();
                });

                continue;
            }

            if ($relation instanceof BelongsToMany) {
                // Only the associations are copied, not any extra pivot columns.
                $keyName = $relation->getRelated()->getKeyName();
                $duplicate->{$relationName}()->attach($model->{$relationName}->pluck($keyName));

                continue;
            }

            throw new EloquentGraphQLException("Relation '$relationName' cannot be duplicated automatically.");
        }
    }

    protected function buildArgs(): array
    {
        return [
            'id' => [
                'type' => Type::nonNull(Type::int()),
            ],
            'relations' => [
                'type' => Type::listOf(Type::nonNull(Type::string())),
            ],
        ];
    }
}
