<?php

namespace EloquentGraphQL\Factories\FieldFactories;

use Closure;
use EloquentGraphQL\Exceptions\EloquentGraphQLException;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use ReflectionException;

class FieldFactoryView extends FieldFactory
{
    /**
     * Builds the return type for the field.
     *
     * @throws EloquentGraphQLException|ReflectionException
     */
    protected function buildReturnType(): Type
    {
        return $this->service->typeFactory($this->model)->build();
    }

    /**
     * Builds the resolve function for the field.
     */
    protected function buildResolve(): Closure
    {
        return function ($_, array $args, $context, ResolveInfo $info) {

            // get eager loading constraints
            $fieldSelection = $info->getFieldSelection(100);
            $returnType = $this->service->typeFactory($this->model)->build();

            $eagerLoadingConstraints = (new EagerLoadingConstraintBuilder())
                ->service($this->service)
                ->fieldSelection($fieldSelection)
                ->returnType($returnType)
                ->resolveInfo($info)
                ->buildRelationConstraints();

            // apply eager loading constraints to query and get entry
            $entry = call_user_func("{$this->model}::with", $eagerLoadingConstraints)->find($args['id']);

            // return null if entry does not exist
            if (! $entry) {
                return null;
            }

            // authorize
            $this->service->security()->assertCanView($entry);

            return $entry;
        };
    }

    /**
     * Builds the arguments for the field.
     */
    protected function buildArgs(): array
    {
        return [
            'id' => [
                'type' => Type::nonNull(Type::int()),
            ],
        ];
    }
}
