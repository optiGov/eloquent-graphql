<?php

namespace EloquentGraphQL\Security;

use EloquentGraphQL\Exceptions\GraphQLError;
use EloquentGraphQL\Language\Vocabulary;
use EloquentGraphQL\Reflection\ReflectionProperty;
use EloquentGraphQL\Services\EloquentGraphQLService;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Collection;

class SecurityGuard
{
    private EloquentGraphQLService $service;

    private Vocabulary $vocab;

    public function __construct(EloquentGraphQLService $service, Vocabulary $vocab)
    {
        $this->service = $service;
        $this->vocab = $vocab;
    }

    /**
     * Checks if the user is authorized to perform the given ability on the given model.
     *
     * @throws BindingResolutionException
     */
    private function check(string $ability, string $model, array $arguments = []): bool
    {
        $gate = app()->make(Gate::class);

        return $gate
            ->forUser(auth()->guard('api')->user())
            ->check($ability, array_merge([$model], $arguments));
    }

    /**
     * @throws BindingResolutionException
     * @throws GraphQLError
     */
    public function assertCanFilter(string $className, array $filter): void
    {
        if (! $this->check('filter', $className, [$filter])) {
            throw new GraphQLError($this->vocab->errorUnauthorizedFilter());
        }

        foreach ($filter as $property => $value) {
            if (! $this->check('filterProperty', $className, [$property])) {
                throw new GraphQLError($this->vocab->errorUnauthorizedFilterProperty($property));
            }
        }
    }

    /**
     * @throws BindingResolutionException
     * @throws GraphQLError
     */
    public function assertCanCreate(string $className, array $data): void
    {
        if (! $this->check('create', $className, $data)) {
            throw new GraphQLError($this->vocab->errorUnauthorizedCreate());
        }
    }

    /**
     * @throws BindingResolutionException
     * @throws GraphQLError
     */
    public function assertCanDelete(object $model): void
    {
        if (! $this->check('delete', $model::class, [$model])) {
            throw new GraphQLError($this->vocab->errorUnauthorizedDelete());
        }
    }

    /**
     * @throws BindingResolutionException
     * @throws GraphQLError
     */
    public function assertCanUpdate(object $model, array $data): void
    {
        if (! $this->check('update', $model::class, [$model, $data])) {
            throw new GraphQLError($this->vocab->errorUnauthorizedUpdate());
        }
    }

    /**
     * @throws BindingResolutionException
     * @throws GraphQLError
     */
    public function assertCanViewProperty(object $model, ReflectionProperty $property): void
    {
        if (! $this->check('viewProperty', $model::class, [$model, $property->getName()])) {
            throw new GraphQLError($this->vocab->errorUnauthorizedViewProperty());
        }
    }

    /**
     * @throws BindingResolutionException
     * @throws GraphQLError
     */
    public function assertCanView(object $model): void
    {
        if (! $this->check('view', $model::class, [$model])) {
            throw new GraphQLError($this->vocab->errorUnauthorizedView());
        }
    }

    /**
     * @throws BindingResolutionException
     * @throws GraphQLError
     */
    public function assertCanViewAny(string $className): void
    {
        if (! $this->check('viewAny', $className)) {
            throw new GraphQLError($this->vocab->errorUnauthorizedViewAny());
        }
    }

    /**
     * @throws BindingResolutionException
     * @throws GraphQLError
     */
    public function assertCanViewAll(Collection|iterable $models): Collection
    {
        if (is_iterable($models)) {
            $models = collect($models);
        }

        return $models->each(fn (object $model) => $this->assertCanView($model));
    }
}
