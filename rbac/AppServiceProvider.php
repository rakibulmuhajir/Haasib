<?php

namespace App\Providers;

use App\Services\CurrentCompany;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register CurrentCompany as singleton
        $this->app->singleton(CurrentCompany::class, function () {
            return new CurrentCompany();
        });

        // Alias for convenience
        $this->app->alias(CurrentCompany::class, 'current.company');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
