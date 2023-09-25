<?php

namespace EloquentGraphQL\Security;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Container\BindingResolutionException;

class SecurityGuard
{

    /**
     * Checks if the user is authorized to perform the given ability on the given model.
     *
     * @throws BindingResolutionException
     */
    public function check(string $ability, string $model, array $arguments = []): bool
    {
        $gate = app()->make(Gate::class);

        return $gate
            ->forUser(auth()->guard('api')->user())
            ->check($ability, array_merge([$model], $arguments));
    }
}
