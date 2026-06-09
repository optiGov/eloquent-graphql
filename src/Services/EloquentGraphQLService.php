<?php

namespace EloquentGraphQL\Services;

use EloquentGraphQL\Factories\TypeFactory\TypeFactory;
use EloquentGraphQL\GraphQL\RootMutation;
use EloquentGraphQL\GraphQL\RootQuery;
use EloquentGraphQL\Language\Vocabulary;
use EloquentGraphQL\Language\VocabularyEnglish;
use EloquentGraphQL\Reflection\ReflectionInspector;
use EloquentGraphQL\Security\SecurityGuard;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Collection;
use ReflectionException;

class EloquentGraphQLService
{
    protected Vocabulary $vocab;

    protected Collection $typeFactories;

    protected Collection $scalarTypes;

    /**
     * Cache of built GraphQL EnumType instances, keyed by PHP enum class name.
     */
    protected Collection $enumTypes;

    /**
     * Cache of built GraphQL InputObjectType filter instances for enum fields,
     * keyed by PHP enum class name.
     */
    protected Collection $enumFilterTypes;

    private SecurityGuard $securityGuard;

    public function __construct()
    {
        $this->vocab = new VocabularyEnglish;
        $this->typeFactories = new Collection;
        $this->scalarTypes = new Collection;
        $this->enumTypes = new Collection;
        $this->enumFilterTypes = new Collection;
        $this->securityGuard = new SecurityGuard($this, $this->vocab);
    }

    public function setVocab(Vocabulary $vocab): EloquentGraphQLService
    {
        $this->vocab = $vocab;
        $this->securityGuard->setVocab($vocab);

        return $this;
    }

    /**
     * Creates a new root query object.
     */
    public function query(): RootQuery
    {
        return new RootQuery($this, $this->vocab);
    }

    /**
     * Creates a new root mutation object.
     */
    public function mutation(): RootMutation
    {
        return new RootMutation($this, $this->vocab);
    }

    /**
     * @throws ReflectionException
     */
    public function typeFactory(string $model): TypeFactory
    {
        if ($this->typeFactories->has($model)) {
            return $this->typeFactories->get($model);
        }

        $typeFactory = (new TypeFactory($this))
            ->setModel($model)
            ->setName(ReflectionInspector::getShortClassName($model));

        $this->typeFactories->put($model, $typeFactory);

        return $typeFactory;
    }

    public function scalarType(string $class): ScalarType
    {
        if ($this->scalarTypes->has($class)) {
            return $this->scalarTypes->get($class);
        }

        $scalarType = new $class;

        $this->scalarTypes->put($class, $scalarType);

        return $scalarType;
    }

    /**
     * @throws ReflectionException
     */
    public function enumType(string $enumClass): EnumType
    {
        if ($this->enumTypes->has($enumClass)) {
            return $this->enumTypes->get($enumClass);
        }

        $shortName = (new \ReflectionEnum($enumClass))->getShortName();

        $values = [];
        foreach ($enumClass::cases() as $case) {
            $values[$case->name] = [
                'value' => $case instanceof \BackedEnum ? $case->value : $case->name,
                'description' => $case->name,
            ];
        }

        $enumType = new EnumType([
            'name' => $shortName,
            'values' => $values,
        ]);

        $this->enumTypes->put($enumClass, $enumType);

        return $enumType;
    }

    /**
     * @throws ReflectionException
     */
    public function enumFilterType(string $enumClass): InputObjectType
    {
        $enumClass = ltrim($enumClass, '\\');

        if ($this->enumFilterTypes->has($enumClass)) {
            return $this->enumFilterTypes->get($enumClass);
        }

        $graphqlEnum = $this->enumType($enumClass);
        $shortName = (new \ReflectionClass($enumClass))->getShortName();

        $filterType = new InputObjectType([
            'name' => $shortName.'FilterInput',
            'fields' => [
                'eq' => ['type' => $graphqlEnum],
                'ne' => ['type' => $graphqlEnum],
                'in' => ['type' => Type::listOf(Type::nonNull($graphqlEnum))],
                'nin' => ['type' => Type::listOf(Type::nonNull($graphqlEnum))],
            ],
        ]);

        $this->enumFilterTypes->put($enumClass, $filterType);

        return $filterType;
    }

    public function security(): SecurityGuard
    {
        return $this->securityGuard;
    }
}
