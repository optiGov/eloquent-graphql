<?php

namespace EloquentGraphQL\Factories\TypeFactory\Field;

use EloquentGraphQL\Exceptions\EloquentGraphQLException;
use EloquentGraphQL\Types\Order;
use GraphQL\Type\Definition\InputObjectType;

class TypeFieldFactoryOrder extends TypeFieldFactory
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

        return $this->service->typeFactory(Order::class)->buildInput();
    }
}
