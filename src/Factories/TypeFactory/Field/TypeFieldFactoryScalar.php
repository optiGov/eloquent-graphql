<?php

namespace EloquentGraphQL\Factories\TypeFactory\Field;

use EloquentGraphQL\Exceptions\EloquentGraphQLException;
use EloquentGraphQL\Types\CarbonType;
use GraphQL\Type\Definition\EnumType;
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
                $this->service->security()->assertCanViewProperty($parent, $this->property);

                if (is_iterable($parent)) {
                    $raw = $parent[$this->property->getName()] ?? null;
                } elseif (is_object($parent)) {
                    $methodName = 'get'.ucwords($this->property->getName());
                    $raw = method_exists($parent, $methodName)
                        ? $parent->{$methodName}()
                        : $parent->{$this->property->getName()};
                } else {
                    return null;
                }

                // PHP enum instances must be serialised to their scalar value so
                // that graphql-php can match them against the EnumType value map.
                if ($raw instanceof \BackedEnum) {
                    return $raw->value;
                }
                if ($raw instanceof \UnitEnum) {
                    return $raw->name;
                }

                return $raw;
            },
        ];
    }

    /**
     * @throws EloquentGraphQLException
     */
    protected function getType(): NonNull|ListOfType|ScalarType|EnumType
    {
        // literal 'array' with no element type is not supported
        if ($this->property->getType() === 'array') {
            throw new EloquentGraphQLException("The property {$this->property->getName()} is of literal type 'array' which correlates to a GraphQLList with no inner type, which is not supported in auto-generation. Please use a scalar type with square brackets (e.g. 'string[]') or a custom field.");
        }

        // enum types are mapped to a GraphQL EnumType
        if ($this->property->isEnumType()) {
            $enumType = $this->service->enumType($this->property->getType());

            if ($this->property->isArrayType()) {
                $listType = Type::listOf(Type::nonNull($enumType));

                return $this->property->isNullable() ? $listType : Type::nonNull($listType);
            }

            return $this->property->isNullable() ? $enumType : Type::nonNull($enumType);
        }

        $type = match (strtolower($this->property->getType())) {
            'string' => Type::string(),
            'int' => Type::int(),
            'float' => Type::float(),
            'bool', 'boolean' => Type::boolean(),
            'carbon' => $this->service->scalarType(CarbonType::class),
            default => throw new EloquentGraphQLException(
                "Unsupported scalar type '{$this->property->getType()}' on property '{$this->property->getName()}'. ".
                'Supported types: string, int, float, bool, boolean, carbon, or enum.'
            ),
        };

        if ($this->property->isArrayType()) {
            $type = Type::listOf(Type::nonNull($type));
        }

        return $this->property->isNullable() ? $type : Type::nonNull($type);
    }
}
