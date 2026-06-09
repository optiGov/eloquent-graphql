<?php

namespace EloquentGraphQL\Factories\FieldFactories;

use Closure;
use EloquentGraphQL\Events\GraphQLUpdatedModel;
use EloquentGraphQL\Events\GraphQLUpdatingModel;
use EloquentGraphQL\Exceptions\EloquentGraphQLException;
use GraphQL\Type\Definition\Type;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use ReflectionException;

class FieldFactoryUpdate extends FieldFactory
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
        $hasOne = $this->service->typeFactory($this->model)->getHasOne();
        $hasMany = $this->service->typeFactory($this->model)->getHasMany();

        return function ($_, $args) use ($hasOne, $hasMany) {
            $entry = call_user_func("{$this->model}::find", $args['id']);

            if (! $entry) {
                return false;
            }

            $input = $args[$this->pureName];

            $this->service->security()->assertCanUpdate($entry, $input);
            GraphQLUpdatingModel::dispatch($entry);

            [$manyRelations, $oneRelations, $scalarFields] = $this->separateRelations($input, $hasMany, $hasOne);

            $this->applyScalarFields($entry, $scalarFields);
            $this->syncManyRelations($entry, $manyRelations, $hasMany);
            $this->syncOneRelations($entry, $oneRelations, $hasOne);

            if ($entry->timestamps) {
                $entry->updateTimestamps();
            }

            $success = $entry->update();

            if ($success) {
                GraphQLUpdatedModel::dispatch($entry);
            }

            return $success;
        };
    }

    /**
     * Splits the raw input array into three buckets:
     * has-many relation IDs, has-one relation IDs, and plain scalar fields.
     *
     * @return array{0: array, 1: array, 2: array}
     */
    private function separateRelations(array $input, Collection $hasMany, Collection $hasOne): array
    {
        $manyRelations = [];
        $oneRelations = [];
        $scalarFields = $input;

        foreach ($hasMany->keys() as $field) {
            if (array_key_exists($field, $scalarFields)) {
                $manyRelations[$field] = $scalarFields[$field];
                unset($scalarFields[$field]);
            }
        }

        foreach ($hasOne->keys() as $field) {
            if (array_key_exists($field, $scalarFields)) {
                $oneRelations[$field] = $scalarFields[$field];
                unset($scalarFields[$field]);
            }
        }

        return [$manyRelations, $oneRelations, $scalarFields];
    }

    /**
     * Assigns scalar field values directly onto the model.
     */
    private function applyScalarFields(Model $entry, array $scalarFields): void
    {
        foreach ($scalarFields as $property => $value) {
            $entry->{$property} = $value;
        }
    }

    /**
     * Syncs all has-many / belongs-to-many relations.
     */
    private function syncManyRelations(Model $entry, array $manyRelations, Collection $hasMany): void
    {
        foreach ($manyRelations as $field => $ids) {
            if ($ids === null) {
                continue;
            }

            $relationship = $entry->{$field}();
            $relatedClass = $hasMany[$field]->getType();

            if ($relationship instanceof HasMany) {
                $this->syncHasMany($entry, $relationship, $relatedClass, $ids);
            } elseif ($relationship instanceof BelongsToMany) {
                $relationship->sync($ids);
            }
        }
    }

    /**
     * Detaches entries no longer in $ids, then attaches the ones that are.
     */
    private function syncHasMany(Model $entry, HasMany $relationship, string $relatedClass, array $ids): void
    {
        $fk = $relationship->getForeignKeyName();

        // Nullify the FK on entries that have been removed from the relation.
        $relatedClass::where($fk, $entry->id)
            ->whereNotIn('id', $ids)
            ->update([$fk => null]);

        // Attach the current set.
        $relationship->saveMany(
            $relatedClass::whereIn('id', $ids)->get()
        );
    }

    /**
     * Syncs all has-one / belongs-to relations.
     */
    private function syncOneRelations(Model $entry, array $oneRelations, Collection $hasOne): void
    {
        foreach ($oneRelations as $field => $id) {
            $relationship = $entry->{$field}();
            $relatedClass = $hasOne[$field]->getType();

            if ($id === null) {
                $this->disconnectOneRelation($relationship);
            } else {
                $this->connectOneRelation($relationship, $relatedClass, $id);
            }
        }
    }

    /**
     * Removes the link for a has-one or belongs-to relation.
     */
    private function disconnectOneRelation(HasOne|BelongsTo $relationship): void
    {
        if ($relationship instanceof HasOne) {
            $fk = $relationship->getForeignKeyName();
            $relationship->get()->each(fn (Model $model) => $model->update([$fk => null]));
        } elseif ($relationship instanceof BelongsTo) {
            $relationship->dissociate();
        }
    }

    /**
     * Links the model identified by $id to a has-one or belongs-to relation.
     * Does nothing if the target model does not exist.
     */
    private function connectOneRelation(HasOne|BelongsTo $relationship, string $relatedClass, int $id): void
    {
        if ($relationship instanceof HasOne) {
            $newModel = $relatedClass::find($id);

            if ($newModel === null) {
                return;
            }

            $connectedModel = $relationship->first();
            $alreadyLinked = $connectedModel && $connectedModel->is($newModel);

            if (! $alreadyLinked) {
                $relationship->update([$relationship->getForeignKeyName() => null]);
                $relationship->save($newModel);
            }
        } elseif ($relationship instanceof BelongsTo) {
            $newModel = $relatedClass::find($id);

            if ($newModel !== null) {
                $relationship->associate($newModel);
            }
        }
    }

    /**
     * Builds the arguments for the field.
     *
     * @throws EloquentGraphQLException
     * @throws ReflectionException
     */
    protected function buildArgs(): array
    {
        return [
            'id' => [
                'type' => Type::nonNull(Type::int()),
            ],
            $this->pureName => [
                'type' => $this->service->typeFactory($this->model)->buildInput(),
            ],
        ];
    }
}
