<?php

namespace EloquentGraphQL\Factories\FieldFactories;

use Closure;
use EloquentGraphQL\Exceptions\EloquentGraphQLException;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
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

            $fieldSelection = $info->getFieldSelection(10);
            $returnType = $this->buildReturnType();
            $relations = $this->getQueriedRelations($fieldSelection, $returnType);

            // $entry = call_user_func("{$this->model}::find", $args['id']);
            $entry = call_user_func("{$this->model}::with", $relations)->find($args['id']);

            // return null if entry does not exist
            if (! $entry) {
                return null;
            }

            // authorize
            $this->service->security()->assertCanView($entry);

            return $entry;
        };
    }

    private function getQueriedRelations(array $fieldSelection, Type $returnType, string $prefix = ''): array
    {
        // find the relations that are queried
        $returnTypeFields = $returnType->getFields();
        $returnTypeRelations = array_filter($returnTypeFields, fn (array $field) => array_key_exists('isRelation', $field->config));

        $fieldSelectionNames = array_keys($fieldSelection);
        $returnTypeRelationNames = array_keys($returnTypeRelations);
        $queriedRelations = array_intersect($fieldSelectionNames, $returnTypeRelationNames);

        foreach ($queriedRelations as $key) {
            // get the inner type of the relation (the node type)
            $relation = $returnTypeRelations[$key];
            $relationType = $relation->config['type'];

            if ($relationType instanceof NonNull) {
                $relationType = $relationType->getWrappedType();
            }

            $relationFields = $relationType->getFields();
            $edgesType = $relationFields['edges']->config['type'];

            if ($edgesType instanceof NonNull) {
                $edgesType = $edgesType->getWrappedType();
            }
            if ($edgesType instanceof ListOfType) {
                $edgesType = $edgesType->getWrappedType();
            }
            if ($edgesType instanceof NonNull) {
                $edgesType = $edgesType->getWrappedType();
            }

            $nodeType = $edgesType->getFields()['node']->config['type'];

            if ($nodeType instanceof NonNull) {
                $nodeType = $nodeType->getWrappedType();
            }

            // get the child relations of the current relation and merge them with the current relations
            $childRelations = $this->getQueriedRelations(
                $fieldSelection[$key]['edges']['node'],
                $nodeType,
                $key.'.'
            );

            $queriedRelations = array_merge($queriedRelations, $childRelations);
        }

        // add the prefix to the relations to match the eager loading format and return the result
        return array_map(fn ($key) => $prefix.$key, $queriedRelations);
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
