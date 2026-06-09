<?php

namespace EloquentGraphQL\Factories\TypeFactory\Field;

use EloquentGraphQL\Exceptions\EloquentGraphQLException;
use EloquentGraphQL\Types\FilterBoolean;
use EloquentGraphQL\Types\FilterCarbon;
use EloquentGraphQL\Types\FilterFloat;
use EloquentGraphQL\Types\FilterInteger;
use EloquentGraphQL\Types\FilterString;
use GraphQL\Type\Definition\InputObjectType;

class TypeFieldFactoryFilter extends TypeFieldFactory
{
    /**
     * @throws EloquentGraphQLException
     */
    public function build(): array
    {
        return [
            'type' => $this->getType(),
        ];
    }

    /**
     * @throws EloquentGraphQLException
     */
    protected function getType(): InputObjectType
    {
        // literal 'array' with no element type is not supported for filtering
        if ($this->property->getType() === 'array') {
            throw new EloquentGraphQLException("The property {$this->property->getName()} is of type array which correlates to a GraphQLList, which is not supported in auto-generation.");
        }

        // dedicated enum filter type with eq/ne/in/nin operators
        if ($this->property->isEnumType()) {
            return $this->service->enumFilterType($this->property->getType());
        }

        $filterClass = match (strtolower($this->property->getType())) {
            'string' => FilterString::class,
            'int' => FilterInteger::class,
            'float' => FilterFloat::class,
            'bool', 'boolean' => FilterBoolean::class,
            'carbon' => FilterCarbon::class,
            default => throw new EloquentGraphQLException(
                "Unsupported filter type '{$this->property->getType()}' on property '{$this->property->getName()}'. ".
                'Supported types: string, int, float, bool, boolean, carbon, or enum.'
            ),
        };

        return $this->service->typeFactory($filterClass)->buildInput();
    }
}
