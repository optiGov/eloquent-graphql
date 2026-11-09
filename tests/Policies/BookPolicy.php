<?php

namespace EloquentGraphQL\Tests\Policies;

use EloquentGraphQL\Contracts\EloquentGraphQLPolicyContract;
use EloquentGraphQL\Security\Visibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Books are always viewable, and guests may change them, so that mutations can be tested without a user.
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

    public function visibility(?Authenticatable $user): Visibility
    {
        return Visibility::all();
    }

    public function viewProperty(?Authenticatable $user, object $model, string $property): bool
    {
        return true;
    }

    public function create(?Authenticatable $user, array $data): bool
    {
        return true;
    }

    public function duplicate(?Authenticatable $user, Model $model): bool
    {
        return true;
    }

    public function update(?Authenticatable $user, Model $model, array $data): bool
    {
        return true;
    }

    public function delete(?Authenticatable $user, Model $model): bool
    {
        return true;
    }
}
