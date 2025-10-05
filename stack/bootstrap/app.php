<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \Illuminate\Session\Middleware\StartSession::class,
            // Do NOT force auth on all web routes; guest pages must be accessible
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\SetCompanyContext::class,
        ]);

        $middleware->api(append: [
            \Illuminate\Auth\Middleware\Authenticate::class,
            \App\Http\Middleware\ApiRateLimit::class,
            \App\Http\Middleware\EnsureApiHasCompanyContext::class,
        ]);

        $middleware->alias([
            'idempotent' => \App\Http\Middleware\EnsureIdempotency::class,
            'permission' => \App\Http\Middleware\RequirePermission::class,
        ]);
    })
    ->withSchedule(function ($schedule): void {
        $schedule->command('ar:update-aging')->daily()->at('23:59');
        $schedule->command('fx:sync ecb')->daily()->at('02:00');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
