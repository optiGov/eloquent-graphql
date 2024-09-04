<?php

namespace EloquentGraphQL\Factories\TypeFactory\Field;

use EloquentGraphQL\Exceptions\EloquentGraphQLException;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use ReflectionException;

class TypeFieldFactoryHasOne extends TypeFieldFactory
{
    /**
     * @throws ReflectionException
     * @throws EloquentGraphQLException
     */
    public function build(): array
    {
        return [
            'isRelation' => true,
            'type' => $this->getType(),
            'resolve' => function ($parent) {
                // authorize property
                $this->service->security()->assertCanViewProperty($parent, $this->property);

                // get entry
                $entry = $parent->{$this->fieldName};

                // return null if entry does not exist
                if (! $entry) {
                    return null;
                }

                // authorize entry
                $this->service->security()->assertCanView($entry);

                return $entry;
            },
        ];
    }

    /**
     * @throws ReflectionException
     * @throws EloquentGraphQLException
     */
    protected function getType(): NonNull|ObjectType|ScalarType
    {
        $factory = $this->service->typeFactory($this->property->getType());

        return $this->property->isNullable()
            ? $factory->build()
            : $factory->buildNonNull();
    }
}
