<?php

namespace EloquentGraphQL\Factories\TypeFactory;

use EloquentGraphQL\Exceptions\EloquentGraphQLException;
use EloquentGraphQL\Factories\Pagination\Paginator;
use EloquentGraphQL\Factories\TypeFactory\Field\TypeFieldFactoryFilter;
use EloquentGraphQL\Factories\TypeFactory\Field\TypeFieldFactoryHasMany;
use EloquentGraphQL\Factories\TypeFactory\Field\TypeFieldFactoryHasOne;
use EloquentGraphQL\Factories\TypeFactory\Field\TypeFieldFactoryOrder;
use EloquentGraphQL\Factories\TypeFactory\Field\TypeFieldFactoryScalar;
use EloquentGraphQL\Reflection\ReflectionInspector;
use EloquentGraphQL\Reflection\ReflectionProperty;
use EloquentGraphQL\Services\EloquentGraphQLService;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Collection;
use ReflectionException;

class TypeFactory
{
    /**
     * Name of the GraphQL type.
     */
    private string $name;

    /**
     * Description of the GraphQL type.
     */
    private string $description = '';

    /**
     * Class name of the model class.
     */
    private string $model;

    /**
     * Fields to be ignored.
     */
    private Collection $ignore;

    /**
     * List relations to other model classes, given by their type names.
     * Properties that cannot be detected automatically.
     */
    private Collection $hasMany;

    /**
     * Relations to other model classes, given by their type names.
     * Properties that cannot be detected automatically.
     */
    private Collection $hasOne;

    /**
     * Properties defined by the doc. E.g. for adding model functions as properties
     * or to disable either read or write access to this property.
     */
    private Collection $docProperties;

    /**
     * Class properties, used for caching purposes.
     *
     * @var ?Collection
     */
    private ?Collection $properties = null;

    /**
     * EloquentGraphQLService instance for resolving other type factories.
     */
    private EloquentGraphQLService $service;

    /**
     * The resulting GraphQL type, stored for caching purposes.
     */
    private ?ObjectType $type = null;

    /**
     * The resulting GraphQL connection type, stored for caching purposes.
     */
    private ?ObjectType $paginationType = null;

    /**
     * The resulting GraphQLInputObjectType, stored for caching purposes.
     */
    private ?InputObjectType $inputType = null;

    /**
     * The resulting GraphQLInputObjectType, stored for caching purposes.
     */
    private ?InputObjectType $filterType = null;

    /**
     * The resulting GraphQLInputObjectType, stored for caching purposes.
     */
    private ?InputObjectType $orderType = null;

    public function __construct(EloquentGraphQLService $service)
    {
        $this->service = $service;
        $this->ignore = new Collection(['connector']);
        $this->hasMany = new Collection();
        $this->hasOne = new Collection();
        $this->docProperties = new Collection();
    }

    /**
     * Sets the type name.
     */
    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Sets the type description.
     */
    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Sets the model class name.
     */
    public function setModel(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Ignore properties of model.
     */
    public function ignore(string|array $properties): static
    {
        $this->ignore = $this->ignore->merge($properties);

        return $this;
    }

    /**
     * Builds the GraphQLObjectType.
     *
     * @throws ReflectionException
     * @throws EloquentGraphQLException
     */
    public function build(): ObjectType
    {
        // check if cache can be used
        if ($this->type !== null) {
            return $this->type;
        }

        $fields = new Collection();

        $this->type = new ObjectType([
            'name' => $this->name,
            'model' => $this->model,
            'description' => $this->description,
            'fields' => function () use (&$fields) {
                return $fields->toArray();
            },
        ]);

        $this->collectFieldsFromClassDoc();

        $fields = $fields->merge($this->buildFieldsFromProperties())
            ->merge($this->buildFieldsFromHasOne())
            ->merge($this->buildFieldsFromHasMany());

        return $this->type;
    }

    /**
     * @throws ReflectionException
     * @throws EloquentGraphQLException
     */
    public function buildNonNull(): NonNull
    {
        return Type::nonNull($this->build());
    }

    /**
     * @throws ReflectionException
     * @throws EloquentGraphQLException
     */
    public function buildList(): NonNull
    {
        return Type::nonNull(Type::listOf(Type::nonNull($this->build())));
    }

    /**
     * @throws ReflectionException
     * @throws EloquentGraphQLException
     */
    public function buildPaginated(): ObjectType
    {
        // check if cache can be used
        if ($this->paginationType !== null) {
            return $this->paginationType;
        }

        $fields = new Collection();

        $this->paginationType = new ObjectType([
            'name' => $this->name.'Connection',
            'fields' => function () use (&$fields) {
                return $fields->toArray();
            },
        ]);

        $fields = $fields->merge([
            'totalCount' => [
                'type' => Type::nonNull(Type::int()),
                'resolve' => function (Paginator $paginator) {
                    $this->service->security()->assertCanViewAny($this->model);

                    return $paginator->count();
                },
            ],
            'edges' => [
                'type' => Type::nonNull(Type::listOf(Type::nonNull(
                    new ObjectType([
                        'name' => $this->name.'Edge',
                        'fields' => [
                            'node' => [
                                'type' => $this->buildNonNull(),
                                'resolve' => fn (mixed $object) => $object,
                            ],
                        ],
                    ]),
                ))),
                'resolve' => fn (Paginator $paginator) => $this->service->security()->assertCanViewAll($paginator->get()),
            ],
        ]);

        return $this->paginationType;
    }

    /**
     * @throws ReflectionException
     * @throws EloquentGraphQLException
     */
    public function buildListPaginated(): NonNull
    {
        return Type::nonNull($this->buildPaginated());
    }

    /**
     * Builds the GraphQLInputObjectType.
     *
     * @throws ReflectionException
     * @throws EloquentGraphQLException
     */
    public function buildInput(): InputObjectType
    {
        // check if cache can be used
        if ($this->inputType !== null) {
            return $this->inputType;
        }

        $fields = new Collection();

        $this->inputType = new InputObjectType([
            'name' => $this->name.'Input',
            'description' => $this->description,
            'fields' => function () use (&$fields) {
                return $fields->toArray();
            },
        ]);

        $this->collectFieldsFromClassDoc();

        // collect fields from properties but do not ignore the field if it is the id of a hasOne relation
        // because those fields can be directly filled with a scalar value and need no extra input type like
        // a hasMany relationship does with a GraphQLList(GraphQLInt).
        $fields = $fields->merge($this->buildInputTypeFieldsFromProperties(true))
            ->merge($this->buildInputTypeFieldsFromHasOne())
            ->merge($this->buildInputTypeFieldsFromHasMany());

        return $this->inputType;
    }

    /**
     * @throws ReflectionException
     * @throws EloquentGraphQLException
     */
    public function buildFilter(): InputObjectType
    {
        // check if cache can be used
        if ($this->filterType !== null) {
            return $this->filterType;
        }

        $fields = new Collection();

        $this->filterType = new InputObjectType([
            'name' => $this->name.'FilterInput',
            'fields' => function () use (&$fields) {
                return $fields->toArray();
            },
        ]);

        $this->collectFieldsFromClassDoc();

        $fields = $fields->merge([
            'and' => [
                'type' => Type::listOf(Type::nonNull($this->filterType)),
            ],
            'or' => [
                'type' => Type::listOf(Type::nonNull($this->filterType)),
            ],
            'not' => [
                'type' => $this->filterType,
            ],
        ])
            ->merge($this->buildFilterTypeFieldsFromProperties())
            ->merge($this->buildFilterTypeFieldsFromHasOne())
            ->merge($this->buildFilterTypeFieldsFromHasMany());

        return $this->filterType;
    }

    /**
     * @throws ReflectionException
     * @throws EloquentGraphQLException
     */
    public function buildOrder(): InputObjectType
    {
        // check if cache can be used
        if ($this->orderType !== null) {
            return $this->orderType;
        }

        $fields = new Collection();

        $this->orderType = new InputObjectType([
            'name' => $this->name.'OrderInput',
            'fields' => function () use (&$fields) {
                return $fields->toArray();
            },
        ]);

        $this->collectFieldsFromClassDoc();

        $fields = $fields->merge($this->buildOrderTypeFieldsFromProperties())
            ->merge($this->buildOrderTypeFieldsFromHasOne());

        return $this->orderType;
    }

    /**
     * Collects field from the class doc and uses those to add hasMany and hasOne relations.
     *
     * @throws ReflectionException
     */
    private function collectFieldsFromClassDoc(): void
    {
        ReflectionInspector::getPropertiesFromClassDoc($this->model)
            ->each(function (ReflectionProperty $property) {
                if ($property->isPrimitiveType()) {
                    $this->docProperties->put($property->getName(), $property);
                } elseif ($property->isArrayType()) {
                    $this->hasMany->put($property->getName(), $property);
                } else {
                    $this->hasOne->put($property->getName(), $property);
                }
            });
    }

    /**
     * Builds several GraphQL typeFields from the has-one relationships.
     *
     * @throws ReflectionException|EloquentGraphQLException
     */
    private function buildFieldsFromHasOne(): Collection
    {
        $fields = new Collection();

        $this->hasOne
            ->filter(fn (ReflectionProperty $property) => $property->isReadable())
            ->each(function (ReflectionProperty $property, string $fieldName) use (&$fields) {
                $fields->put(
                    $fieldName,
                    (new TypeFieldFactoryHasOne($this->service))
                        ->setFieldName($fieldName)
                        ->setProperty($property)
                        ->setModel($this->model)
                        ->build()
                );
            });

        return $fields;
    }

    /**
     * Builds several GraphQL typeFields from the has-many relationships.
     *
     * @throws ReflectionException|EloquentGraphQLException
     */
    private function buildFieldsFromHasMany(): Collection
    {
        $fields = new Collection();

        $this->hasMany
            ->filter(fn (ReflectionProperty $property) => $property->isReadable())
            ->each(function (ReflectionProperty $property, string $fieldName) use (&$fields) {
                $fields->put(
                    $fieldName,
                    (new TypeFieldFactoryHasMany($this->service))
                        ->setFieldName($fieldName)
                        ->setProperty($property)
                        ->setModel($this->model)
                        ->build()
                );
            });

        return $fields;
    }

    /**
     * Builds several GraphQL typeFields from the properties.
     *
     * @throws EloquentGraphQLException
     */
    private function buildFieldsFromProperties(): Collection
    {
        $fields = new Collection();

        $this->docProperties
            ->filter(fn (ReflectionProperty $property) => $property->isReadable())
            ->each(function (ReflectionProperty $property, string $name) use ($fields) {
                $fields->put(
                    $property->getName(),
                    (new TypeFieldFactoryScalar($this->service))
                        ->setFieldName($property->getName())
                        ->setProperty($property)
                        ->setModel($this->model)
                        ->build()
                );
            });

        return $fields;
    }

    /**
     * Builds several GraphQL typeFields from the has-one relationships.
     */
    private function buildInputTypeFieldsFromHasOne(): Collection
    {
        $fields = new Collection();

        $this->hasOne
            ->filter(fn (ReflectionProperty $property) => $property->isWritable())
            ->each(function (ReflectionProperty $property, string $fieldName) use (&$fields) {
                $fields->put($fieldName, [
                    'type' => $property->isNullable() ? Type::int() : Type::nonNull(Type::int()),
                ]);
            });

        return $fields;
    }

    /**
     * Builds several GraphQL typeFields from the has-many relationships.
     */
    private function buildInputTypeFieldsFromHasMany(): Collection
    {
        $fields = new Collection();

        $this->hasMany
            ->filter(fn (ReflectionProperty $property) => $property->isWritable())
            ->each(function (ReflectionProperty $property, string $fieldName) use (&$fields) {
                $fields->put($fieldName, [
                    'type' => Type::listOf(Type::nonNull(Type::int())),
                ]);
            });

        return $fields;
    }

    /**
     * Builds several GraphQL typeFields for the input type from the properties.
     *
     * @throws EloquentGraphQLException
     */
    private function buildInputTypeFieldsFromProperties(): Collection
    {
        $fields = new Collection();

        $this->docProperties
            ->filter(fn (ReflectionProperty $property) => $property->getName() !== 'id')
            ->filter(fn (ReflectionProperty $property) => $property->isWritable())
            ->each(function (ReflectionProperty $property, string $name) use ($fields) {
                $fields->put(
                    $property->getName(),
                    (new TypeFieldFactoryScalar($this->service))
                        ->setFieldName($property->getName())
                        ->setProperty($property)
                        ->setModel($this->model)
                        ->build()
                );
            });

        return $fields;
    }

    /**
     * Builds several GraphQL typeFields for the input type from the properties.
     *
     * @throws EloquentGraphQLException
     */
    private function buildFilterTypeFieldsFromProperties(): Collection
    {
        $fields = new Collection();

        $this->docProperties
            ->filter(fn (ReflectionProperty $property) => ! $property->isComputed())
            ->each(function (ReflectionProperty $property, string $name) use ($fields) {
                $fields->put(
                    $property->getName(),
                    (new TypeFieldFactoryFilter($this->service))
                        ->setFieldName($property->getName())
                        ->setProperty($property)
                        ->setModel($this->model)
                        ->build()
                );
            });

        return $fields;
    }

    /**
     * Builds several GraphQL typeFields from the has-one relationships.
     */
    private function buildFilterTypeFieldsFromHasOne(): Collection
    {
        $fields = new Collection();

        $this->hasOne
            ->filter(fn (ReflectionProperty $property) => ! $property->isComputed())
            ->each(function (ReflectionProperty $property, string $fieldName) use (&$fields) {
                $fields->put($fieldName, [
                    'type' => $this->service->typeFactory($property->getType())->buildFilter(),
                ]);
            });

        return $fields;
    }

    /**
     * Builds several GraphQL typeFields from the has-many relationships.
     */
    private function buildFilterTypeFieldsFromHasMany(): Collection
    {
        $fields = new Collection();

        $this->hasMany
            ->filter(fn (ReflectionProperty $property) => ! $property->isComputed())
            ->each(function (ReflectionProperty $property, string $fieldName) use (&$fields) {
                $fields->put($fieldName, [
                    'type' => $this->service->typeFactory($property->getType())->buildFilter(),
                ]);
            });

        return $fields;
    }

    /**
     * Builds several GraphQL typeFields for the order type from the properties.
     *
     * @throws EloquentGraphQLException
     */
    private function buildOrderTypeFieldsFromProperties(): Collection
    {
        $fields = new Collection();

        $this->docProperties
            ->filter(fn (ReflectionProperty $property) => ! $property->isComputed())
            ->each(function (ReflectionProperty $property) use ($fields) {
                $fields->put(
                    $property->getName(),
                    (new TypeFieldFactoryOrder($this->service))
                        ->setFieldName($property->getName())
                        ->setProperty($property)
                        ->setModel($this->model)
                        ->build()
                );
            });

        return $fields;
    }

    /**
     * Builds several GraphQL typeFields from the has-one relationships.
     */
    private function buildOrderTypeFieldsFromHasOne(): Collection
    {
        $fields = new Collection();

        $this->hasOne
            ->filter(fn (ReflectionProperty $property) => ! $property->isComputed())
            ->each(function (ReflectionProperty $property, string $fieldName) use (&$fields) {
                $fields->put($fieldName, [
                    'type' => $this->service->typeFactory($property->getType())->buildOrder(),
                ]);
            });

        return $fields;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getHasMany(): Collection
    {
        return $this->hasMany;
    }

    public function getHasOne(): Collection
    {
        return $this->hasOne;
    }
}
