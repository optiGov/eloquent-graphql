<?php

namespace EloquentGraphQL\Contracts;

use EloquentGraphQL\Security\Visibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;

interface EloquentGraphQLPolicyContract
{
    /**
     * Determine whether the user can filter the models.
     */
    public function filter(?Authenticatable $user, array $filter): bool;

    /**
     * Determine whether the user can filter for a specific property.
     */
    public function filterProperty(?Authenticatable $user, string $property): bool;

    /**
     * Determine whether the user can view any of the models.
     */
    public function viewAny(?Authenticatable $user): bool;

    /**
     * Determine which models the user can view. Every way the package loads, filters, counts or orders models is
     * restricted to them, and view() is derived from it. Non-Eloquent types may only return all() or none().
     */
    public function visibility(?Authenticatable $user): Visibility;

    /**
     * Determine which models the user can connect to another model in a mutation. Usually the same as visibility().
     */
    public function connectability(?Authenticatable $user): Visibility;

    /**
     * Determine whether the user can view a specific model's property.
     */
    public function viewProperty(?Authenticatable $user, object $model, string $property): bool;

    /**
     * Determine whether the user can create models.
     */
    public function create(Authenticatable $user, array $data): bool;

    /**
     * Determine whether the user can duplicate the model.
     */
    public function duplicate(Authenticatable $user, Model $model): bool;

    /**
     * Determine whether the user can update the model.
     */
    public function update(Authenticatable $user, Model $model, array $data): bool;

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Authenticatable $user, Model $model): bool;
}
