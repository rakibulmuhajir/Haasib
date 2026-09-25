<?php

namespace App\Providers;

use App\Dashboard\WidgetRegistry;
use App\Services\CompanyContextService;
use App\Services\CurrentCompany;
use App\Support\Database\TenantContextGuard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
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
        // exists/unique rules on "fuel.nozzles" & co. must query the default connection, which
        // carries the company context, not a schema-named connection that doesn't. See the class.
        $this->app->extend('validation.presence', fn ($verifier, $app) => new \App\Support\Database\SchemaAwarePresenceVerifier($app['db']));

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

        $this->runMigrationsAcrossEveryCompany();

        RateLimiter::for('commands', fn($request) =>
            Limit::perMinute(120)->by($request->user()?->id ?: $request->ip())
        );

        RateLimiter::for('catalog', fn($request) =>
            Limit::perMinute(300)->by($request->user()?->id ?: $request->ip())
        );
    }

    /**
     * A migration is cross-company work by definition.
     *
     * Data migrations enumerate auth.companies and backfill their rows. Under
     * enforced row level security, run by a role that has no company context,
     * that enumeration returns **zero rows** and the migration reports a clean
     * run having touched nothing -- and where it does write, the write is
     * refused. Neither failure is visible in a deploy log.
     *
     * So the migration session is put into the policies' own super-admin
     * escape hatch for its duration, and taken back out afterwards. This is
     * the same hatch console commands and seeders use; it is not a bypass of
     * row level security, it is the policies' documented answer to "this
     * caller legitimately spans every tenant".
     */
    private function runMigrationsAcrossEveryCompany(): void
    {
        $previous = null;

        Event::listen(MigrationsStarted::class, function () use (&$previous) {
            if (DB::connection()->getDriverName() !== 'pgsql') {
                return;
            }

            $previous = (string) (DB::selectOne(
                "SELECT current_setting('app.is_super_admin', true) AS value"
            )->value ?? '');

            DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
        });

        Event::listen(MigrationsEnded::class, function () use (&$previous) {
            if (DB::connection()->getDriverName() !== 'pgsql') {
                return;
            }

            DB::select("SELECT set_config('app.is_super_admin', ?, false)", [$previous ?? '']);
            $previous = null;
        });
    }
}
