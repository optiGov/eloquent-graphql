<?php

namespace EloquentGraphQL\Factories\FieldFactories;

use Closure;
use EloquentGraphQL\Events\GraphQLCreatedModel;
use EloquentGraphQL\Events\GraphQLCreatingModel;
use EloquentGraphQL\Exceptions\EloquentGraphQLException;
use GraphQL\Type\Definition\Type;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use ReflectionException;

class FieldFactoryCreate extends FieldFactory
{
    /**
     * Builds the return type for the field.
     *
     * @throws EloquentGraphQLException|ReflectionException
     */
    protected function buildReturnType(): Type
    {
        return $this->service->typeFactory($this->model)->buildNonNull();
    }

    /**
     * Builds the resolve function for the field.
     */
    protected function buildResolve(): Closure
    {
        $hasOne = $this->service->typeFactory($this->model)->getHasOne();
        $hasMany = $this->service->typeFactory($this->model)->getHasMany();

        return function ($parent, $args) use ($hasOne, $hasMany) {
            $this->service->security()->assertCanCreate($this->model, [$args[$this->pureName]]);

            $entry = new $this->model();

            [$manyRelations, $hasOneRelations, $scalarFields] = $this->separateRelations(
                $args[$this->pureName], $entry, $hasMany, $hasOne
            );

            $entry->fill(array_filter($scalarFields, fn ($value) => $value !== null));

            GraphQLCreatingModel::dispatch($entry);
            $entry->save();
            $entry->refresh();

            $this->attachHasOneRelations($entry, $hasOneRelations, $hasOne);
            $this->attachManyRelations($entry, $manyRelations, $hasMany);

            GraphQLCreatedModel::dispatch($entry);

            return $entry;
        };
    }

    /**
     * Splits the raw input into three buckets:
     * - has-many relation IDs (deferred until after save)
     * - has-one relation IDs (deferred until after save, FK is on the related model)
     * - scalar fields (ready to fill, including inlined BelongsTo FKs)
     *
     * BelongsTo relations are resolved here immediately: the FK is written
     * directly into $scalarFields so that it is persisted with the model itself.
     *
     * @return array{0: array, 1: array, 2: array}
     */
    private function separateRelations(array $input, Model $entry, Collection $hasMany, Collection $hasOne): array
    {
        $manyRelations = [];
        $hasOneRelations = [];
        $scalarFields = $input;

        foreach ($hasMany->keys() as $field) {
            if (array_key_exists($field, $scalarFields)) {
                $manyRelations[$field] = $scalarFields[$field];
                unset($scalarFields[$field]);
            }
        }

        foreach ($hasOne->keys() as $field) {
            if (! array_key_exists($field, $scalarFields)) {
                continue;
            }

            $relationship = $entry->{$field}();

            if ($relationship instanceof HasOne) {
                // FK lives on the related model — must be saved after the entry exists.
                $hasOneRelations[$field] = $scalarFields[$field];
            } else {
                // BelongsTo: FK lives on this model — inline it into scalar fields now.
                $scalarFields[$relationship->getForeignKeyName()] = $scalarFields[$field];
            }

            unset($scalarFields[$field]);
        }

        return [$manyRelations, $hasOneRelations, $scalarFields];
    }

    /**
     * Saves each deferred has-one relation onto the newly created entry.
     * Skips null IDs and IDs that resolve to a non-existent model.
     */
    private function attachHasOneRelations(Model $entry, array $hasOneRelations, Collection $hasOne): void
    {
        foreach ($hasOneRelations as $field => $id) {
            if ($id === null) {
                continue;
            }

            $relatedClass = $hasOne[$field]->getType();
            $relatedModel = $relatedClass::find($id);

            if ($relatedModel === null) {
                continue;
            }

            $entry->{$field}()->save($relatedModel);
        }
    }

    /**
     * Attaches all has-many / belongs-to-many relations to the newly created entry.
     * Uses a single whereIn query per relation instead of one find() per ID.
     */
    private function attachManyRelations(Model $entry, array $manyRelations, Collection $hasMany): void
    {
        foreach ($manyRelations as $field => $ids) {
            if (empty($ids)) {
                continue;
            }

            $relationship = $entry->{$field}();
            $relatedClass = $hasMany[$field]->getType();

            if ($relationship instanceof HasMany || $relationship instanceof BelongsToMany) {
                $relationship->saveMany(
                    $relatedClass::whereIn('id', $ids)->get()
                );
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
            $this->pureName => [
                'type' => $this->service->typeFactory($this->model)->buildInput(),
            ],
        ];
    }
}
