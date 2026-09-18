<?php

namespace App\Providers;

use App\Dashboard\WidgetRegistry;
use App\Services\CompanyContextService;
use App\Services\CurrentCompany;
use App\Support\Database\TenantContextGuard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register CompanyContextService as scoped singleton (one instance per request)
        $this->app->scoped(CompanyContextService::class);
        // CurrentCompany should share request scope too
        $this->app->scoped(CurrentCompany::class);
        // Shared across every module's service provider boot(), so widget
        // registration in one module lands in the same registry every module reads.
        $this->app->singleton(WidgetRegistry::class);

        $this->app->singleton(TenantContextGuard::class, function ($app) {
            $mode = (string) config('database.tenant_context_guard.mode', TenantContextGuard::MODE_OFF);

            // Never in production: the guard costs a query on the failure path
            // and turns a degraded read into a 500. It is a desk tool.
            if ($app->environment('production')) {
                $mode = TenantContextGuard::MODE_OFF;
            }

            return new TenantContextGuard(
                $mode,
                array_values((array) config('database.tenant_context_guard.connections', []))
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $guard = $this->app->make(TenantContextGuard::class);

        if ($guard->enabled()) {
            Event::listen(QueryExecuted::class, fn (QueryExecuted $event) => $guard->handle($event));
        }

        RateLimiter::for('commands', fn($request) =>
            Limit::perMinute(120)->by($request->user()?->id ?: $request->ip())
        );

        RateLimiter::for('catalog', fn($request) =>
            Limit::perMinute(300)->by($request->user()?->id ?: $request->ip())
        );
    }
}
