<?php

namespace EloquentGraphQL\Services;

use EloquentGraphQL\Factories\TypeFactory\TypeFactory;
use EloquentGraphQL\GraphQL\RootMutation;
use EloquentGraphQL\GraphQL\RootQuery;
use EloquentGraphQL\Language\Vocabulary;
use EloquentGraphQL\Language\VocabularyEnglish;
use EloquentGraphQL\Reflection\ReflectionInspector;
use EloquentGraphQL\Security\SecurityGuard;
use GraphQL\Type\Definition\ScalarType;
use Illuminate\Support\Collection;
use ReflectionException;

class EloquentGraphQLService
{
    protected Vocabulary $vocab;

    protected Collection $typeFactories;

    protected Collection $scalarTypes;

    private SecurityGuard $securityGuard;

    public function __construct()
    {
        $this->vocab = new VocabularyEnglish();
        $this->typeFactories = new Collection();
        $this->scalarTypes = new Collection();
        $this->securityGuard = new SecurityGuard($this);
    }

    public function setVocab(Vocabulary $vocab): EloquentGraphQLService
    {
        $this->vocab = $vocab;

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

        $scalarType = new $class();

        $this->scalarTypes->put($class, $scalarType);

        return $scalarType;
    }

    public function security(): SecurityGuard
    {
        return $this->securityGuard;
    }
}
