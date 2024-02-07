<?php

namespace EloquentGraphQL\Security;

use EloquentGraphQL\Exceptions\GraphQLError;
use EloquentGraphQL\Reflection\ReflectionProperty;
use EloquentGraphQL\Services\EloquentGraphQLService;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Collection;
use ReflectionException;

class SecurityGuard
{
    private EloquentGraphQLService $service;

    public function __construct(EloquentGraphQLService $service)
    {
        $this->service = $service;
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
     * @throws ReflectionException
     */
    public function assertCanFilter(string $className, array $filter): void
    {
        if (! $this->check('filter', $className, [$filter])) {
            $typeName = $this->service->typeFactory($className)->getName();
            throw new GraphQLError('You are not authorized to filter the type ['.$typeName.'].');
        }

        foreach ($filter as $property => $value) {
            if (! $this->check('filterProperty', $className, [$property])) {
                $typeName = $this->service->typeFactory($className)->getName();
                throw new GraphQLError('You are not authorized to filter the property ['.$property.'] of the type ['.$typeName.'].');
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
            throw new GraphQLError('You are not authorized to create this model.');
        }
    }

    /**
     * @throws BindingResolutionException
     * @throws GraphQLError
     */
    public function assertCanDelete(object $model): void
    {
        if (! $this->check('delete', $model::class, [$model])) {
            throw new GraphQLError('You are not authorized to delete this model.');
        }
    }

    /**
     * @throws BindingResolutionException
     * @throws GraphQLError
     */
    public function assertCanUpdate(object $model, array $data): void
    {
        if (! $this->check('update', $model::class, [$model, $data])) {
            throw new GraphQLError('You are not authorized to update this model.');
        }
    }

    /**
     * @throws BindingResolutionException
     * @throws GraphQLError
     */
    public function assertCanViewProperty(object $model, ReflectionProperty $property): void
    {
        if (! $this->check('viewProperty', $model::class, [$model, $property->getName()])) {
            throw new GraphQLError('You are not authorized to view this property.');
        }
    }

    /**
     * @throws BindingResolutionException
     * @throws GraphQLError
     */
    public function assertCanView(object $model): void
    {
        if (! $this->check('view', $model::class, [$model])) {
            throw new GraphQLError('You are not authorized to view this model.');
        }
    }

    /**
     * @throws BindingResolutionException
     * @throws GraphQLError
     * @throws ReflectionException
     */
    public function assertCanViewAny(string $className): void
    {
        if (! $this->check('viewAny', $className)) {
            $typeName = $this->service->typeFactory($className)->getName();
            throw new GraphQLError('You are not authorized to view any models of the type ['.$typeName.'].');
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
