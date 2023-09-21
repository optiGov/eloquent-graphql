<?php

namespace EloquentGraphQL\GraphQL;

use EloquentGraphQL\Language\Vocabulary;
use EloquentGraphQL\Services\EloquentGraphQLService;
use GraphQL\Type\Definition\ObjectType;
use Illuminate\Support\Collection;

abstract class RootType
{
    protected Collection $fields;

    protected Vocabulary $vocab;

    protected EloquentGraphQLService $service;

    public function __construct(EloquentGraphQLService $service, Vocabulary $vocab)
    {
        $this->fields = new Collection();
        $this->vocab = $vocab;
        $this->service = $service;
    }

    /**
     * Adds a field to the root type.
     */
    public function field(string $name, array $field): static
    {
        $this->fields->put($name, $field);

        return $this;
    }

    /**
     * Returns the root type.
     */
    abstract public function build(): ObjectType;
}
