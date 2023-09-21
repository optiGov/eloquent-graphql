<?php

namespace EloquentGraphQL\GraphQL;

use EloquentGraphQL\Factories\Field\FieldFactoryAll;
use EloquentGraphQL\Factories\Field\FieldFactoryView;
use EloquentGraphQL\Reflection\ReflectionInspector;
use GraphQL\Type\Definition\ObjectType;
use ReflectionException;

class RootQuery extends RootType
{
    /**
     * @throws ReflectionException
     */
    public function all(string $model): static
    {
        $className = ReflectionInspector::getShortClassName($model);
        $field = new FieldFactoryAll($this->service);

        $field->name($this->vocab->all($className))
            ->pureName($className)
            ->model($model);

        return $this->field($field->getName(), $field->build());
    }

    /**
     * @throws ReflectionException
     */
    public function view(string $model): static
    {
        $className = ReflectionInspector::getShortClassName($model);
        $field = new FieldFactoryView($this->service);

        $field->name($this->vocab->view($className))
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
            'name' => 'Query',
            'fields' => $this->fields->toArray(),
        ]);
    }
}
