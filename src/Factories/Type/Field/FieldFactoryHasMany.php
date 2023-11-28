<?php

namespace EloquentGraphQL\Factories\Type\Field;

use EloquentGraphQL\Exceptions\EloquentGraphQLException;
use EloquentGraphQL\Exceptions\GraphQLError;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Collection;
use ReflectionException;

class FieldFactoryHasMany extends FieldFactory
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
                // authorize
                if (! $this->service->security()->check('viewProperty', $this->model, [$parent, $this->property->getName()])) {
                    throw new GraphQLError('You are not authorized to view this property.');
                }

                // check if user can view any entry
                if (! $this->service->security()->check('viewAny', $this->property->getType())) {
                    throw new GraphQLError('You are not authorized to view any of these models.');
                }

                // get entries
                $entries = $parent->{$this->fieldName};

                // filter entries
                if ($entries instanceof Collection) {
                    return $entries->filter(fn ($entry) => $this->service->security()->check('view', $this->property->getType(), [$entry]));
                } elseif (is_array($entries)) {
                    return array_filter($entries, fn ($entry) => $this->service->security()->check('view', $this->property->getType(), [$entry]));
                } else {
                    throw new EloquentGraphQLException('The hasMany property '.$this->property->getName().' is neither a Collection nor an array.');
                }
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

        return Type::nonNull(Type::listOf($innerType));
    }
}
