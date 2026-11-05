<?php

namespace EloquentGraphQL\Contracts;

use Illuminate\Contracts\Database\Eloquent\Builder;
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
     * Restrict the query to the models the user can view. The query must yield exactly the models for which
     * view() returns true, otherwise filters and counts would reveal models the user cannot view.
     */
    public function scopeViewable(?Authenticatable $user, Builder $query): void;

    /**
     * Determine whether the user can view the model.
     */
    public function view(?Authenticatable $user, Model $model): bool;

    /**
     * Determine whether the user can view a specific model's property.
     */
    public function viewProperty(?Authenticatable $user, Model $model, string $property): bool;

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
