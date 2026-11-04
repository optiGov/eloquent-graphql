<?php

namespace EloquentGraphQL\Tests\Policies;

use EloquentGraphQL\Contracts\EloquentGraphQLPolicyContract;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Books are always viewable.
 */
class BookPolicy implements EloquentGraphQLPolicyContract
{
    public function filter(?Authenticatable $user, array $filter): bool
    {
        return true;
    }

    public function filterProperty(?Authenticatable $user, string $property): bool
    {
        return true;
    }

    public function viewAny(?Authenticatable $user): bool
    {
        return true;
    }

    public function scopeViewable(?Authenticatable $user, Builder $query): void {}

    public function view(?Authenticatable $user, Model $model): bool
    {
        return true;
    }

    public function viewProperty(?Authenticatable $user, Model $model, string $property): bool
    {
        return true;
    }

    public function create(Authenticatable $user, array $data): bool
    {
        return true;
    }

    public function duplicate(Authenticatable $user, Model $model): bool
    {
        return true;
    }

    public function update(Authenticatable $user, Model $model, array $data): bool
    {
        return true;
    }

    public function delete(Authenticatable $user, Model $model): bool
    {
        return true;
    }
}
