<?php

namespace EloquentGraphQL\GraphQL;

use EloquentGraphQL\Factories\FieldFactories\FieldFactoryCreate;
use EloquentGraphQL\Factories\FieldFactories\FieldFactoryDelete;
use EloquentGraphQL\Factories\FieldFactories\FieldFactoryUpdate;
use EloquentGraphQL\Reflection\ReflectionInspector;
use GraphQL\Type\Definition\ObjectType;
use ReflectionException;

class RootMutation extends RootType
{
    /**
     * @throws ReflectionException
     */
    public function create(string $model): static
    {
        $className = ReflectionInspector::getShortClassName($model);
        $field = new FieldFactoryCreate($this->service);

        $field->name($this->vocab->create($className))
            ->pureName($className)
            ->model($model);

        return $this->field($field->getName(), $field->build());
    }

    /**
     * @throws ReflectionException
     */
    public function delete(string $model): static
    {
        $className = ReflectionInspector::getShortClassName($model);
        $field = new FieldFactoryDelete($this->service);

        $field->name($this->vocab->delete($className))
            ->pureName($className)
            ->model($model);

        return $this->field($field->getName(), $field->build());
    }

    /**
     * @throws ReflectionException
     */
    public function update(string $model): static
    {
        $className = ReflectionInspector::getShortClassName($model);
        $field = new FieldFactoryUpdate($this->service);

        $field->name($this->vocab->update($className))
            ->pureName($className)
            ->model($model);

        return $this->field($field->getName(), $field->build());
    }

    /**
     * {@inheritDoc}
     */
    public function build(): ObjectType
    {
        return new ObjectType([
            'name' => 'Mutation',
            'fields' => $this->fields->toArray(),
        ]);
    }
}
