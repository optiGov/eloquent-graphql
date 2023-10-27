<?php

namespace EloquentGraphQL\Factories\Field;

use Closure;
use EloquentGraphQL\Events\GraphQLUpdatedModel;
use EloquentGraphQL\Events\GraphQLUpdatingModel;
use EloquentGraphQL\Exceptions\EloquentGraphQLException;
use EloquentGraphQL\Exceptions\GraphQLError;
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
            if (! $this->service->security()->check('update', $this->model, [$entry, $args[$this->pureName]])) {
                throw new GraphQLError('You are not authorized to update this model.');
            }

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

                // remove old entries
                if ($relationship instanceof HasMany) {
                    // set foreign key to null for all entries that are not in the new list of entries to connect.
                    $fkPropertyColumn = $relationship->getForeignKeyName();
                    call_user_func("{$hasMany[$argument]->getType()}::where", $fkPropertyColumn, $entry->id)
                        ->whereNotIn('id', $ids)
                        ->update([$fkPropertyColumn => null]);
                }

                if ($relationship instanceof BelongsToMany) {
                    $relationship->detach();
                }

                // connect new entries
                foreach ($ids as $id) {
                    if ($relationship instanceof HasMany) {
                        $relationship->save(call_user_func("{$hasMany[$argument]->getType()}::find", $id));
                    }
                    if ($relationship instanceof BelongsToMany) {
                        $relationship->save(call_user_func("{$hasMany[$argument]->getType()}::find", $id));
                    }
                }
            }

            foreach ($relationsToAddOne as $argument => $id) {
                // get the relationship
                $relationship = $entry->{$argument}();

                // check if entries should be connected or disconnected
                if ($id === null) {
                    // disconnect old entries
                    if ($relationship instanceof HasOne) {
                        continue;
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

            $success = $entry->update();

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
