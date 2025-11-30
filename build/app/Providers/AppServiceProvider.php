<?php

namespace App\Providers;

use App\Services\CurrentCompany;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Share CurrentCompany instance per request so middleware + services see the same context.
        $this->app->scoped(CurrentCompany::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('commands', fn($request) =>
            Limit::perMinute(120)->by($request->user()?->id ?: $request->ip())
        );

        RateLimiter::for('catalog', fn($request) =>
            Limit::perMinute(300)->by($request->user()?->id ?: $request->ip())
        );
    }
}
