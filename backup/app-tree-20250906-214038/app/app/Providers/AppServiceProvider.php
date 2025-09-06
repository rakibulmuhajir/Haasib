<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use App\Support\CommandBus;
use App\Support\Tenancy;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CommandBus::class, fn () => new CommandBus());
        $this->app->singleton(Tenancy::class, fn () => new Tenancy());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

         RateLimiter::for('devcli', fn($request) => [
        Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip()),
    ]);
    }
}
