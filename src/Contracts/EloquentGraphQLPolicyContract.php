<?php

namespace EloquentGraphQL\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;

interface EloquentGraphQLPolicyContract
{
    /**
     * Determine whether the user can view any of the models.
     */
    public function viewAny(?Authenticatable $user): bool;

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
     * Determine whether the user can update the model.
     */
    public function update(Authenticatable $user, Model $model): bool;

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Authenticatable $user, Model $model): bool;
}
