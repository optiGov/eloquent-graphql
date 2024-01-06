<?php

namespace EloquentGraphQL\Factories\TypeFactory\Field;

use EloquentGraphQL\Exceptions\EloquentGraphQLException;
use EloquentGraphQL\Types\FilterBoolean;
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
        // handle arrays
        if ($this->property->getType() === 'array') {
            throw new EloquentGraphQLException("The property {$this->property->getName()} is of type array which correlates to a GraphQLList, which is not supported in auto-generation.");
        }

        $filterClass = match (strtolower($this->property->getType())) {
            'string' => FilterString::class,
            'int' => FilterInteger::class,
            'float' => FilterFloat::class,
            'bool', 'boolean' => FilterBoolean::class,
        };

        return $this->service->typeFactory($filterClass)->buildInput();
    }
}
