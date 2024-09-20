<?php

namespace EloquentGraphQL\Factories\FieldFactories;

use EloquentGraphQL\Factories\Pagination\PaginatorQuery;
use EloquentGraphQL\Services\EloquentGraphQLService;
use GraphQL\Executor\Values;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\WrappingType;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class EagerLoadingConstraintBuilder
{
    private array $fieldSelection;

    private ObjectType $returnType;

    private ResolveInfo $resolveInfo;

    private EloquentGraphQLService $service;

    public function __construct()
    {
        //
    }

    public function fieldSelection(array $fieldSelection): EagerLoadingConstraintBuilder
    {
        $this->fieldSelection = $fieldSelection;

        return $this;
    }

    public function returnType(ObjectType $returnType): EagerLoadingConstraintBuilder
    {
        $this->returnType = $returnType;

        return $this;
    }

    public function resolveInfo(ResolveInfo $info): EagerLoadingConstraintBuilder
    {
        $this->resolveInfo = $info;

        return $this;
    }

    public function service(EloquentGraphQLService $service): EagerLoadingConstraintBuilder
    {
        $this->service = $service;

        return $this;
    }

    public function buildRelationConstraints(): array
    {
        return $this->getQueriedRelationConstraints($this->fieldSelection, $this->returnType, $this->resolveInfo->fieldNodes[0]);
    }

    private function getQueriedRelationConstraints(array $fieldSelection, ObjectType $returnType, FieldNode $node, string $prefix = ''): array
    {
        // get all possible fields of the return type that are relations and eager loadable
        $returnTypeFields = $returnType->getFields();
        $returnTypeRelationFields = array_filter($returnTypeFields, function (FieldDefinition $field) {
            $isRelation = array_key_exists('isRelation', $field->config) && $field->config['isRelation'] === true;
            $eagerLoadEnabled = ! array_key_exists('eagerLoadDisabled', $field->config) || $field->config['eagerLoadDisabled'] === false;

            return $isRelation && $eagerLoadEnabled;
        });

        // get the relations that are queried
        $selectedFieldNames = array_keys($fieldSelection);
        $returnTypeRelationFieldNames = array_keys($returnTypeRelationFields);
        $queriedRelations = array_intersect($selectedFieldNames, $returnTypeRelationFieldNames);

        // map the queried relations to an empty function
        $emptyFn = fn (Builder $query) => $query;
        $emptyFunctions = array_fill(0, count($queriedRelations), $emptyFn);
        $relationConstraints = array_combine($queriedRelations, $emptyFunctions);

        // build the constraints for the queried relations
        foreach ($relationConstraints as $name => $_) {

            // get the relation field definition, field type and node from AST
            $relationField = $returnTypeRelationFields[$name];
            $relationFieldType = $relationField->config['type'];
            $relationNode = $this->findNodeInSelectionSet($node->selectionSet, $name);

            // unwrap if the relation field type is a list or non-null
            if ($relationFieldType instanceof WrappingType) {
                $relationFieldType = $relationFieldType->getWrappedType();
            }

            // check if there exists an edges field
            $edgesField = $relationFieldType->findField('edges');

            $fieldType = $relationFieldType;
            $fieldSubSelection = $fieldSelection[$name];
            $fieldNode = $relationNode;

            if ($edgesField) {
                $edgesFieldType = $edgesField->config['type'];
                $edgesNode = $this->findNodeInSelectionSet($relationNode->selectionSet, 'edges');

                // if no edges node is found, no subselection on the relational models is made, so unset the relation constraint and continue with the next relation
                if (! $edgesNode) {
                    unset($relationConstraints[$name]);

                    continue;
                }

                // unwrap if the edges field type is a list or non-null
                if ($edgesFieldType instanceof WrappingType) {
                    $edgesFieldType = $edgesFieldType->getInnermostType();
                }

                // get node field type and node field from AST
                $nodeFieldType = $edgesFieldType->findField('node')->config['type'];
                $nodeNode = $this->findNodeInSelectionSet($edgesNode->selectionSet, 'node');

                // unwrap if the node field type is a list or non-null
                if ($nodeFieldType instanceof WrappingType) {
                    $nodeFieldType = $nodeFieldType->getWrappedType();
                }

                // build the relation query function
                $relationFieldDefinition = $returnType->findField($name);
                $relationFieldNode = $this->findNodeInSelectionSet($node->selectionSet, $name);
                $arguments = Values::getArgumentValues($relationFieldDefinition, $relationFieldNode, $this->resolveInfo->variableValues);

                $relationConstraints[$name] = $this->buildRelationConstraintQuery($arguments, $nodeFieldType);

                // set the field type, sub selection and node to the node field type, sub selection and node
                $fieldType = $nodeFieldType;
                $fieldSubSelection = $fieldSelection[$name]['edges']['node'];
                $fieldNode = $nodeNode;
            }

            // get the child relations of the current relationField and merge them with the current relations
            $childRelations = $this->getQueriedRelationConstraints(
                $fieldSubSelection,
                $fieldType,
                $fieldNode,
                "$name.",
            );

            $relationConstraints = array_merge($relationConstraints, $childRelations);
        }

        // add the prefix to the array keys of the $relationConstraints array to match the eager loading format and return the result
        if ($prefix === '') {
            return $relationConstraints;
        }

        foreach ($relationConstraints as $key => $value) {
            $relationConstraints[$prefix.$key] = $value;
            unset($relationConstraints[$key]);
        }

        return $relationConstraints;
    }

    private function buildRelationConstraintQuery(array $arguments, ObjectType $nodeFieldType)
    {
        if (empty($arguments)) {
            return function (Relation $query) {
                return $query;
            };
        }

        return function (Relation $query) use ($arguments, $nodeFieldType) {
            $paginator = new PaginatorQuery($query);

            return $paginator
                ->className($nodeFieldType->config['model'])
                ->service($this->service)
                ->limit($arguments['limit'] ?? null)
                ->offset($arguments['offset'] ?? null)
                ->filter($arguments['filter'] ?? null)
                ->order($arguments['order'] ?? null)
                ->getQueryBuilder();
        };
    }

    private function findNodeInSelectionSet(SelectionSetNode $selectionSet, string $name): ?FieldNode
    {
        foreach ($selectionSet->selections as $selection) {
            if ($selection->name->value === $name) {
                return $selection;
            }
        }

        return null;
    }
}
