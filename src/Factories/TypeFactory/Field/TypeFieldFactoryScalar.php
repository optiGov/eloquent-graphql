<?php

namespace EloquentGraphQL\Factories\TypeFactory\Field;

use EloquentGraphQL\Exceptions\EloquentGraphQLException;
use EloquentGraphQL\Types\CarbonType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;

class TypeFieldFactoryScalar extends TypeFieldFactory
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
                $this->service->security()->assertCanViewProperty($parent, $this->property);

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
    protected function getType(): NonNull|ListOfType|ScalarType
    {
        // handle arrays
        if ($this->property->getType() === 'array') {
            throw new EloquentGraphQLException("The property {$this->property->getName()} is of literal type 'array' which correlates to a GraphQLList with no inner type, which is not supported in auto-generation. Please use a scalar type with square brackets (e.g. 'string[]') or a custom field.");
        }

        $type = match (strtolower($this->property->getType())) {
            'string' => Type::string(),
            'int' => Type::int(),
            'float' => Type::float(),
            'bool', 'boolean' => Type::boolean(),
            'carbon' => $this->service->scalarType(CarbonType::class),
        };

        if ($this->property->isArrayType()) {
            $type = Type::listOf(Type::nonNull($type));
        }

        return $this->property->isNullable() ? $type : Type::nonNull($type);
    }
}
