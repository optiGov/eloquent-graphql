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
use Illuminate\Support\Collection;
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

            $this->service->security()->assertCanDuplicate($model);

            GraphQLDuplicatingModel::dispatch($model);

            $duplicate = DB::transaction(function () use ($model) {
                $duplicate = $model->replicate();
                $duplicate->save();

                $this->duplicateRelations($model, $duplicate);

                return $duplicate;
            });

            GraphQLDuplicatedModel::dispatch($duplicate);

            return $duplicate;
        };
    }

    private function relations(): Collection
    {
        $typeFactory = $this->service->typeFactory($this->model);

        return $typeFactory->getHasOne()->merge($typeFactory->getHasMany());
    }

    /**
     * Duplicates the relations marked with @duplicateable onto the freshly created
     * duplicate. Relations without the annotation are left alone.
     *
     * Note: relations are only duplicated one level deep — the related models'
     * own relations are not recursively duplicated.
     *
     * @throws EloquentGraphQLException
     */
    protected function duplicateRelations(Model $model, Model $duplicate): void
    {
        foreach ($this->relations() as $relationName => $property) {
            if (! $property->isDuplicateable() || ! $model->isRelation($relationName)) {
                continue;
            }

            $relation = $model->{$relationName}();

            if ($relation instanceof HasOneOrMany) {
                $foreignKeyName = $relation->getForeignKeyName();

                $relation->get()->each(function (Model $related) use ($duplicate, $foreignKeyName) {
                    $relatedDuplicate = $related->replicate();
                    $relatedDuplicate->{$foreignKeyName} = $duplicate->getKey();
                    $relatedDuplicate->save();
                });

                continue;
            }

            if ($relation instanceof BelongsToMany) {
                // Only the associations are copied, not any extra pivot columns.
                $duplicate->{$relationName}()->attach($relation->pluck($relation->getQualifiedRelatedKeyName()));

                continue;
            }

            $relationType = $relation::class;

            throw new EloquentGraphQLException(
                "Relation '$relationName' of {$this->model} is marked @duplicateable, but relations of type $relationType cannot be duplicated."
            );
        }
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
