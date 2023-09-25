<?php

namespace EloquentGraphQL\Factories\Field;

use Closure;
use EloquentGraphQL\Exceptions\EloquentGraphQLException;
use GraphQL\Type\Definition\Type;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        return Type::nonNull(
            $this->service->typeFactory($this->model)->build()
        );
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
            if (!$this->service->security()->check("create", $this->model, [$args[$this->pureName]])) {
                throw new EloquentGraphQLException("You are not authorized to create this model.");
            }

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
            foreach ($hasOne as $field => $value) {
                if (array_key_exists($field, $args[$this->pureName])) {
                    $relationship = $entry->{$field}();
                    $args[$this->pureName][$relationship->getForeignKeyName()] = $args[$this->pureName][$field];
                    unset($args[$this->pureName][$field]);
                }
            }

            // remove null values
            foreach ($args[$this->pureName] as $key => $value) {
                if ($value === null) {
                    unset($args[$this->pureName][$key]);
                }
            }

            // create the actual entry
            $entry = $entry->create($args[$this->pureName]);

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
