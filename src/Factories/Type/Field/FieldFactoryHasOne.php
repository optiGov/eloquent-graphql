<?php

namespace EloquentGraphQL\Factories\Type\Field;

use EloquentGraphQL\Exceptions\EloquentGraphQLException;
use EloquentGraphQL\Exceptions\GraphQLError;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use ReflectionException;

class FieldFactoryHasOne extends FieldFactory
{
    /**
     * @throws ReflectionException
     * @throws EloquentGraphQLException
     */
    public function build(): array
    {
        return [
            'type' => $this->getType(),
            'resolve' => function ($parent) {
                // authorize property
                if (! $this->service->security()->check('viewProperty', $this->model, [$parent, $this->property->getName()])) {
                    throw new GraphQLError('You are not authorized to view this property.');
                }

                // get entry
                $entry = $parent->{$this->fieldName};

                // return null if entry does not exist
                if (! $entry) {
                    return null;
                }

                // authorize entry
                if (! $this->service->security()->check('view', $this->property->getType(), [$entry])) {
                    throw new GraphQLError('You are not authorized to view this model.');
                }

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
        $innerType = $this->service->typeFactory($this->property->getType())->build();

        return $this->property->isNullable() ? $innerType : Type::nonNull($innerType);
    }
}
