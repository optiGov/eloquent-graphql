<?php

namespace EloquentGraphQL\Security;

use EloquentGraphQL\Contracts\EloquentGraphQLPolicyContract;
use EloquentGraphQL\Exceptions\GraphQLError;
use EloquentGraphQL\Language\Vocabulary;
use EloquentGraphQL\Reflection\ReflectionProperty;
use EloquentGraphQL\Services\EloquentGraphQLService;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use LogicException;
use WeakMap;

class SecurityGuard
{
    private EloquentGraphQLService $service;

    private Vocabulary $vocab;

    /**
     * @var WeakMap<object, array<string, bool>>
     */
    private WeakMap $viewableEntries;

    public function __construct(EloquentGraphQLService $service, Vocabulary $vocab)
    {
        $this->service = $service;
        $this->vocab = $vocab;
        $this->viewableEntries = new WeakMap();
    }

    public function setVocab(Vocabulary $vocab): static
    {
        $this->vocab = $vocab;

        return $this;
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
    public function assertCanOrder(string $className, array $order): void
    {
        foreach ($order as $property => $value) {
            if (! $this->check('filterProperty', $className, [$property])) {
                throw new GraphQLError($this->vocab->errorUnauthorizedOrderProperty($property));
            }
        }
    }

    /**
     * Determines which models of the given class the user can view. Classes without a policy are not viewable.
     *
     * @throws BindingResolutionException
     */
    public function getVisibility(string $className): Visibility
    {
        return $this->getVisibilityFor($className, $this->getUser());
    }

    /**
     * Restricts the query to the models the user can connect to another model. They are not remembered as viewable.
     *
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     *
     * @throws BindingResolutionException
     */
    public function constrainConnectable(Builder $query): Builder
    {
        $policy = app()->make(Gate::class)->getPolicyFor($query->getModel()::class);

        $connectability = $policy instanceof EloquentGraphQLPolicyContract
            ? $policy->connectability($this->getUser())
            : Visibility::none();

        return $this->constrain($query, $connectability, rememberViewable: false);
    }

    /**
     * Restricts the query to the models the user can view and remembers the loaded models as viewable.
     *
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     *
     * @throws BindingResolutionException
     */
    public function constrain(Builder $query, ?Visibility $visibility = null, bool $rememberViewable = true): Builder
    {
        $visibility ??= $this->getVisibility($query->getModel()::class);

        if ($visibility->isNone()) {
            return $query->whereRaw('1 = 0');
        }

        $constraint = $visibility->getConstraint();

        if ($constraint !== null) {
            $constraint($query);
        }

        if (! $rememberViewable) {
            return $query;
        }

        $userKey = $this->getUserKey($this->getUser());

        return $query->afterQuery(function (iterable $results) use ($userKey) {
            foreach ($results as $result) {
                if ($result instanceof Model) {
                    $this->rememberCanView($result, $userKey, true);
                }
            }
        });
    }

    /**
     * Determines whether the user can view the model. Models created within the request are viewable, so that
     * mutations can return them.
     *
     * @throws BindingResolutionException
     */
    public function canView(object $model): bool
    {
        if ($model instanceof Model && $model->wasRecentlyCreated) {
            return true;
        }

        return $this->canViewAs($model, $this->getUser());
    }

    /**
     * Determines whether the given user can view the model. Models loaded through constrain() are known to be
     * viewable, all others are checked against the visibility once.
     *
     * @throws BindingResolutionException
     */
    public function canViewAs(object $model, ?Authenticatable $user): bool
    {
        $userKey = $this->getUserKey($user);
        $canView = $this->viewableEntries[$model][$userKey] ?? null;

        if ($canView === null) {
            $canView = $this->determineCanView($model, $user);
            $this->rememberCanView($model, $userKey, $canView);
        }

        return $canView;
    }

    /**
     * Returns only the models the user can view.
     *
     * @throws BindingResolutionException
     */
    public function filterViewable(Collection|iterable $models): Collection
    {
        return collect($models)
            ->filter(fn (object $model) => $this->canView($model))
            ->values();
    }

    /**
     * @throws BindingResolutionException
     */
    private function getVisibilityFor(string $className, ?Authenticatable $user): Visibility
    {
        $policy = app()->make(Gate::class)->getPolicyFor($className);

        if (! $policy instanceof EloquentGraphQLPolicyContract) {
            return Visibility::none();
        }

        return $policy->visibility($user);
    }

    /**
     * @throws BindingResolutionException
     */
    private function determineCanView(object $model, ?Authenticatable $user): bool
    {
        $visibility = $this->getVisibilityFor($model::class, $user);

        if ($visibility->isAll() || $visibility->isNone()) {
            return $visibility->isAll();
        }

        if (! $model instanceof Model) {
            throw new LogicException('The visibility of ['.$model::class.'] must be all() or none(), as it is no Eloquent model.');
        }

        $constraint = $visibility->getConstraint();

        return $model->newQueryWithoutScopes()
            ->whereKey($model->getKey())
            ->where($constraint)
            ->exists();
    }

    private function rememberCanView(object $model, string $userKey, bool $canView): void
    {
        $entries = $this->viewableEntries[$model] ?? [];
        $entries[$userKey] = $canView;
        $this->viewableEntries[$model] = $entries;
    }

    private function getUser(): ?Authenticatable
    {
        return auth()->guard('api')->user();
    }

    private function getUserKey(?Authenticatable $user): string
    {
        return $user === null ? 'guest' : $user::class.':'.$user->getAuthIdentifier();
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
    public function assertCanDuplicate(object $model): void
    {
        if (! $this->check('duplicate', $model::class, [$model])) {
            throw new GraphQLError($this->vocab->errorUnauthorizedDuplicate());
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
        $this->assertCanView($model);

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
        if (! $this->canView($model)) {
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
}
