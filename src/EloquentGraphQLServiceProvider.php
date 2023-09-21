<?php

namespace EloquentGraphQL;

use EloquentGraphQL\Services\EloquentGraphQLService;
use Illuminate\Support\ServiceProvider;

class EloquentGraphQLServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(EloquentGraphQLService::class, fn () => new EloquentGraphQLService());
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
