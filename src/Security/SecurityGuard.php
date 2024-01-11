<?php

namespace EloquentGraphQL\Security;

use EloquentGraphQL\Exceptions\GraphQLError;
use EloquentGraphQL\Reflection\ReflectionProperty;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class SecurityGuard
{
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
            throw new GraphQLError('You are not authorized to filter this model.');
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
    public function assertCanDelete(Model $model): void
    {
        if (! $this->check('delete', $model::class, [$model])) {
            throw new GraphQLError('You are not authorized to delete this model.');
        }
    }

    /**
     * @throws BindingResolutionException
     * @throws GraphQLError
     */
    public function assertCanUpdate(Model $model, array $data): void
    {
        if (! $this->check('update', $model::class, [$model, $data])) {
            throw new GraphQLError('You are not authorized to update this model.');
        }
    }

    /**
     * @throws BindingResolutionException
     * @throws GraphQLError
     */
    public function assertCanViewProperty(Model $model, ReflectionProperty $property): void
    {
        if (! $this->check('viewProperty', $model::class, [$model, $property->getName()])) {
            throw new GraphQLError('You are not authorized to view this property.');
        }
    }

    /**
     * @throws BindingResolutionException
     * @throws GraphQLError
     */
    public function assertCanView(Model $model): void
    {
        if (! $this->check('view', $model::class, [$model])) {
            throw new GraphQLError('You are not authorized to view this model.');
        }
    }

    /**
     * @throws BindingResolutionException
     * @throws GraphQLError
     */
    public function assertCanViewAny(string $className): void
    {
        if (! $this->check('viewAny', $className)) {
            throw new GraphQLError('You are not authorized to view any of these models.');
        }
    }

    public function filterViewable(Collection|iterable $models): Collection
    {
        if (is_iterable($models)) {
            $models = collect($models);
        }

        return $models->filter(fn (Model $model) => $this->check('view', $model::class, [$model]));
    }
}
