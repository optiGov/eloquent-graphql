<?php

namespace EloquentGraphQL\Services;

use EloquentGraphQL\Factories\Type\TypeFactory;
use EloquentGraphQL\GraphQL\RootMutation;
use EloquentGraphQL\GraphQL\RootQuery;
use EloquentGraphQL\Language\Vocabulary;
use EloquentGraphQL\Language\VocabularyEnglish;
use EloquentGraphQL\Reflection\ReflectionInspector;
use EloquentGraphQL\Security\SecurityGuard;
use Illuminate\Support\Collection;
use ReflectionException;

class EloquentGraphQLService
{
    protected Vocabulary $vocab;

    protected Collection $typeFactories;

    private SecurityGuard $securityGuard;

    public function __construct()
    {
        $this->vocab = new VocabularyEnglish();
        $this->typeFactories = new Collection();
        $this->securityGuard = new SecurityGuard();
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

    public function security(): SecurityGuard
    {
        return $this->securityGuard;
    }
}
