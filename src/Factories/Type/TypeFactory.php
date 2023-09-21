<?php

namespace EloquentGraphQL\Factories\Type;

use EloquentGraphQL\Exceptions\EloquentGraphQLException;
use EloquentGraphQL\Reflection\ReflectionInspector;
use EloquentGraphQL\Reflection\ReflectionProperty;
use EloquentGraphQL\Services\EloquentGraphQLService;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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
     * The resulting GraphQLInputObjectType, stored for caching purposes.
     */
    private ?InputObjectType $inputType = null;

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
     * Adds a field with a GraphQLList of a TypeFactory.
     */
    public function hasMany(string $fieldName, string|ReflectionProperty $ofType): static
    {
        if (is_string($ofType)) {
            $ofType = (new ReflectionProperty())
                ->setType($ofType)
                ->setName($fieldName);
        }

        $this->hasMany->put($fieldName, $ofType);

        return $this;
    }

    /**
     * Adds a field of a TypeFactory.
     */
    public function hasOne(string $fieldName, string|ReflectionProperty $ofType): static
    {
        if (is_string($ofType)) {
            $ofType = (new ReflectionProperty())
                ->setType($ofType)
                ->setName($fieldName);
        }

        $this->hasOne->put($fieldName, $ofType);

        return $this;
    }

    /**
     * Returns the class properties.
     */
    private function getProperties(): Collection
    {
        if ($this->properties !== null) {
            return $this->properties;
        }

        return $this->properties = ReflectionInspector::getProperties($this->model);
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
        $properties = $this->getProperties();

        $this->type = new ObjectType([
            'name' => $this->name,
            'description' => $this->description,
            'fields' => function () use (&$fields) {
                return $fields->toArray();
            },
        ]);

        $this->collectFieldsFromClassDoc();

        $fields = $fields->merge($this->buildFieldsFromProperties($properties))
            ->merge($this->buildFieldsFromHasOne())
            ->merge($this->buildFieldsFromHasMany());

        return $this->type;
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
        $properties = $this->getProperties();

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
        $fields = $fields->merge($this->buildFieldsFromProperties($properties, true))
            ->merge($this->buildInputFieldsFromHasOne())
            ->merge($this->buildInputFieldsFromHasMany());

        return $this->inputType;
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
     */
    private function buildInputFieldsFromHasOne(): Collection
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
    private function buildInputFieldsFromHasMany(): Collection
    {
        $fields = new Collection();

        $this->hasMany
            ->filter(fn (ReflectionProperty $property) => $property->isWritable())
            ->each(function (ReflectionProperty $property, string $fieldName) use (&$fields) {
                $fields->put($fieldName, [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull(Type::int()))),
                ]);
            });

        return $fields;
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
                $type = $this->service->typeFactory($property->getType())->build();
                $fields->put($fieldName, [
                    'type' => $property->isNullable() ? $type : Type::nonNull($type),
                    'resolve' => function ($parent) use ($fieldName) {
                        // TODO: protect field with policy
                        return $parent->{$fieldName};
                    },
                ]);
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
                $type = $this->service->typeFactory($property->getType())->build();
                $fields->put($fieldName, [
                    'type' => Type::nonNull(Type::listOf(Type::nonNull($type))),
                    'resolve' => function ($parent) use ($fieldName) {
                        // TODO: protect field with policy
                        return $parent->{$fieldName};
                    },
                ]);
            });

        return $fields;
    }

    /**
     * Builds several GraphQL typeFields from the properties.
     *
     * @throws EloquentGraphQLException
     */
    private function buildFieldsFromProperties(Collection $properties, bool $forInputType = false): Collection
    {
        $fields = new Collection();
        $seenDocProperties = new Collection();

        $properties
            ->when($forInputType, fn (Collection $properties) => $properties->filter(fn (ReflectionProperty $property) => $property->getName() !== 'id'))
            ->each(function (ReflectionProperty $property) use ($fields, $seenDocProperties, $forInputType) {

                // check if property is read or write only via checking existence in $this->docProperties
                if ($this->docProperties->has($property->getName())) {
                    // note that property was already taken into account
                    $seenDocProperties->add($property->getName());

                    // continue if is not writable on input type
                    if ($forInputType && ! $this->docProperties[$property->getName()]->isWritable()) {
                        return;
                    }

                    // continue if is not readable on default type
                    if (! $forInputType && ! $this->docProperties[$property->getName()]->isReadable()) {
                        return;
                    }
                }

                // check if property is allowed and not in $hasMany or $hasOne
                $inIgnore = $this->ignore->contains($property->getName());
                $inHasMany = $this->hasMany->has(Str::remove('_id', $property->getName()));
                $inHasOne = $this->hasOne->has(Str::remove('_id', $property->getName()));
                if (! $inIgnore
                    && ! $inHasMany
                    && ($forInputType || ! $inHasOne)) {
                    $fields->put($property->getName(), $this->buildField($property));
                }
            });

        $this->docProperties
            ->when($forInputType, fn (Collection $properties) => $properties->filter(fn (ReflectionProperty $property) => $property->getName() !== 'id'))
            ->each(function (ReflectionProperty $property, string $name) use ($fields, $seenDocProperties, $forInputType) {
                $inSeenDocProperties = $seenDocProperties->contains($name);
                if (! $inSeenDocProperties) {
                    // continue if is not writable on input type
                    if ($forInputType && ! $property->isWritable()) {
                        return;
                    }

                    // continue if is not readable on default type
                    if (! $forInputType && ! $property->isReadable()) {
                        return;
                    }

                    $fields->put($property->getName(), $this->buildField($property));
                }
            });

        return $fields;
    }

    /**
     * Builds a GraphQL typeField from a property.
     *
     * @throws EloquentGraphQLException
     */
    private function buildField(ReflectionProperty $property): array
    {
        return [
            'type' => $this->buildTypeFromProperty($property),
            'resolve' => function ($parent) use ($property) {
                // TODO: protect field with policy

                // if $source is an iterable (e.g. array), get value by key (field name)
                if (is_iterable($parent)) {
                    return $parent[$property->getName()] ?? null;
                }

                // if $source is an object, get value by either calling the getter or trying to access property directly
                if (is_object($parent)) {
                    $methodName = 'get'.ucwords($property->getName());
                    // try to use the getter
                    if (method_exists($parent, $methodName)) {
                        return $parent->{$methodName}();
                    }

                    // get the property
                    return $parent->{$property->getName()};
                }

                return null;
            },
        ];
    }

    /**
     * Builds a GraphQL type from a property.
     *
     * @throws EloquentGraphQLException
     */
    private function buildTypeFromProperty(ReflectionProperty $property): Type
    {
        // handle arrays
        if ($property->getType() === 'array') {
            throw new EloquentGraphQLException("The property {$property->getName()} is of type array which correlates to a GraphQLList, which is not supported in auto-generation.");
        }

        $type = match (strtolower($property->getType())) {
            'string' => Type::string(),
            'int' => Type::int(),
            'float' => Type::float(),
            'bool', 'boolean' => Type::boolean(),
        };

        return $property->isNullable() ? $type : Type::nonNull($type);
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
