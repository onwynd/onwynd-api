<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            \App\Repositories\Contracts\UserRepositoryInterface::class,
            \App\Repositories\Eloquent\UserEloquentRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\TherapyRepositoryInterface::class,
            \App\Repositories\Eloquent\TherapyEloquentRepository::class
        );

        $this->app->bind(
            \App\Repositories\Contracts\AIRepositoryInterface::class,
            \App\Repositories\Eloquent\AIEloquentRepository::class
        );
    }

    public function boot(): void
    {
        //
    }
}
