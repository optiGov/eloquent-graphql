<?php

namespace EloquentGraphQL\Factories\FieldFactories;

use Closure;
use EloquentGraphQL\Events\GraphQLCreatedModel;
use EloquentGraphQL\Events\GraphQLCreatingModel;
use EloquentGraphQL\Exceptions\EloquentGraphQLException;
use GraphQL\Type\Definition\Type;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

        return function ($parent, $args) use ($hasMany, $hasOne) {
            // authorize
            $this->service->security()->assertCanCreate($this->model, [$args[$this->pureName]]);

            // build blueprint model
            $entry = new $this->model();

            // store ids to other relations
            $relationsToAddMany = [];
            foreach ($hasMany as $field => $value) {
                if (array_key_exists($field, $args[$this->pureName])) {
                    $relationsToAddMany[$field] = $args[$this->pureName][$field];
                    unset($args[$this->pureName][$field]);
                }
            }
            // add one to one or one-to-many relations as direct fields in args
            $relationsToAddHasOne = [];
            foreach ($hasOne as $field => $value) {
                if (array_key_exists($field, $args[$this->pureName])) {
                    $relationship = $entry->{$field}();
                    if ($relationship instanceof HasOne) {
                        $relationsToAddHasOne[$field] = $args[$this->pureName][$field];
                    } else {
                        $args[$this->pureName][$relationship->getForeignKeyName()] = $args[$this->pureName][$field];
                    }
                    unset($args[$this->pureName][$field]);
                }
            }

            // remove null values
            foreach ($args[$this->pureName] as $key => $value) {
                if ($value === null) {
                    unset($args[$this->pureName][$key]);
                }
            }

            // set values
            $entry->fill($args[$this->pureName]);

            // dispatch creating event
            GraphQLCreatingModel::dispatch($entry);

            // create the actual entry
            $entry->save();

            // reload the data from the database to get values inserted by triggers or defaults
            $entry->refresh();

            // connect one-to-one relations
            foreach ($relationsToAddHasOne as $field => $id) {
                if ($id === null) {
                    continue;
                }

                $relationship = $entry->{$field}();
                $model = call_user_func("{$hasOne[$field]->getType()}::find", $id);
                $relationship->save($model);
            }

            // add relations
            foreach ($relationsToAddMany as $argument => $ids) {
                if ($ids === null) {
                    continue;
                }
                $relationship = $entry->{$argument}();
                foreach ($ids as $id) {
                    if ($relationship instanceof HasMany) {
                        $relationship->save(call_user_func("{$hasMany[$argument]->getType()}::find", $id));
                    }
                    if ($relationship instanceof BelongsToMany) {
                        $relationship->save(call_user_func("{$hasMany[$argument]->getType()}::find", $id));
                    }
                }
            }

            // dispatch created event
            GraphQLCreatedModel::dispatch($entry);

            return $entry;
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
            $this->pureName => [
                'type' => $this->service->typeFactory($this->model)->buildInput(),
            ],
        ];
    }
}
