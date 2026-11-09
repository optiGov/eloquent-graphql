<?php

namespace EloquentGraphQL\Tests\Policies;

use EloquentGraphQL\Contracts\EloquentGraphQLPolicyContract;
use EloquentGraphQL\Security\Visibility;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Authors are viewable when they are visible or named "Öffentlich". Their email must not be filtered or ordered by.
 * Guests may change authors, so that mutations can be tested without a user.
 */
class AuthorPolicy implements EloquentGraphQLPolicyContract
{
    public function filter(?Authenticatable $user, array $filter): bool
    {
        return true;
    }

    public function filterProperty(?Authenticatable $user, string $property): bool
    {
        return $property !== 'email';
    }

    public function viewAny(?Authenticatable $user): bool
    {
        return true;
    }

    public function visibility(?Authenticatable $user): Visibility
    {
        return Visibility::where(fn (Builder $query) => $query->where('visible', true)->orWhere('name', 'Öffentlich'));
    }

    public function viewProperty(?Authenticatable $user, Model $model, string $property): bool
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
