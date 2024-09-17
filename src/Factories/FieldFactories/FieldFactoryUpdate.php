<?php

namespace EloquentGraphQL\Factories\FieldFactories;

use Closure;
use EloquentGraphQL\Events\GraphQLUpdatedModel;
use EloquentGraphQL\Events\GraphQLUpdatingModel;
use EloquentGraphQL\Exceptions\EloquentGraphQLException;
use GraphQL\Type\Definition\Type;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

        return function ($_, $args) use ($hasMany, $hasOne) {
            // get entry
            $entry = call_user_func("{$this->model}::find", $args['id']);

            // return false if entry does not exist
            if (! $entry) {
                return false;
            }

            // authorize
            $this->service->security()->assertCanUpdate($entry, $args[$this->pureName]);

            // dispatch updating event
            GraphQLUpdatingModel::dispatch($entry);

            // store ids to other relations
            $relationsToAddMany = [];
            $relationsToAddOne = [];
            foreach ($hasMany as $field => $value) {
                if (array_key_exists($field, $args[$this->pureName])) {
                    $relationsToAddMany[$field] = $args[$this->pureName][$field];
                    unset($args[$this->pureName][$field]);
                }
            }
            foreach ($hasOne as $field => $value) {
                if (array_key_exists($field, $args[$this->pureName])) {
                    $relationsToAddOne[$field] = $args[$this->pureName][$field];
                    unset($args[$this->pureName][$field]);
                }
            }

            // update properties
            foreach ($args[$this->pureName] as $property => $value) {
                $entry->{$property} = $value;
            }

            // update relations
            foreach ($relationsToAddMany as $argument => $ids) {
                if ($ids === null) {
                    continue;
                }

                $relationship = $entry->{$argument}();

                if ($relationship instanceof HasMany) {
                    $fkPropertyColumn = $relationship->getForeignKeyName();
                    call_user_func("{$hasMany[$argument]->getType()}::where", $fkPropertyColumn, $entry->id)
                        ->whereNotIn('id', $ids)
                        ->update([$fkPropertyColumn => null]);

                    // connect new entries
                    $models = call_user_func("{$hasMany[$argument]->getType()}::whereIn", 'id', $ids)
                        ->get();
                    $relationship->saveMany($models);
                } elseif ($relationship instanceof BelongsToMany) {
                    $relationship->sync($ids);
                }
            }

            foreach ($relationsToAddOne as $argument => $id) {
                // get the relationship
                $relationship = $entry->{$argument}();

                // check if entries should be connected or disconnected
                if ($id === null) {
                    // disconnect old entries
                    if ($relationship instanceof HasOne) {
                        $relationship->delete();
                    }
                    if ($relationship instanceof BelongsTo) {
                        $relationship->dissociate();
                    }
                } else {
                    // connect new entries
                    if ($relationship instanceof HasOne) {
                        $relationship->save(call_user_func("{$hasOne[$argument]->getType()}::find", $id));
                    }
                    if ($relationship instanceof BelongsTo) {
                        $relationship->associate(call_user_func("{$hasOne[$argument]->getType()}::find", $id));
                    }
                }
            }

            // update timestamps
            if ($entry->timestamps) {
                $entry->updateTimestamps();
            }

            // save entry
            $success = $entry->update();

            // dispatch updated event
            if ($success) {
                GraphQLUpdatedModel::dispatch($entry);
            }

            return $success;
        };
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
