<?php

namespace EloquentGraphQL\Factories\Type\Field;

use EloquentGraphQL\Exceptions\EloquentGraphQLException;
use EloquentGraphQL\Exceptions\GraphQLError;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;

class FieldFactoryScalar extends FieldFactory
{
    /**
     * @throws EloquentGraphQLException
     */
    public function build(): array
    {
        return [
            'type' => $this->getType(),
            'resolve' => function ($parent) {
                // authorize
                if (! $this->service->security()->check('viewProperty', $this->model, [$parent, $this->property->getName()])) {
                    throw new GraphQLError('You are not authorized to view this property.');
                }

                // if $source is an iterable (e.g. array), get value by key (field name)
                if (is_iterable($parent)) {
                    return $parent[$this->property->getName()] ?? null;
                }

                // if $source is an object, get value by either calling the getter or trying to access property directly
                if (is_object($parent)) {
                    $methodName = 'get'.ucwords($this->property->getName());
                    // try to use the getter
                    if (method_exists($parent, $methodName)) {
                        return $parent->{$methodName}();
                    }

                    // get the property
                    return $parent->{$this->property->getName()};
                }

                return null;
            },
        ];
    }

    /**
     * @throws EloquentGraphQLException
     */
    protected function getType(): NonNull|ScalarType
    {
        // handle arrays
        if ($this->property->getType() === 'array') {
            throw new EloquentGraphQLException("The property {$this->property->getName()} is of type array which correlates to a GraphQLList, which is not supported in auto-generation.");
        }

        $type = match (strtolower($this->property->getType())) {
            'string' => Type::string(),
            'int' => Type::int(),
            'float' => Type::float(),
            'bool', 'boolean' => Type::boolean(),
        };

        return $this->property->isNullable() ? $type : Type::nonNull($type);
    }
}
