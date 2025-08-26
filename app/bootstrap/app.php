<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
         web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        // add this ↓ line so /login, /register, etc. exist
        //auth: __DIR__.'/../routes/auth.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

         // aliases so you can use 'tenant' and 'txn' in routes
        $middleware->alias([
            'tenant' => \App\Http\Middleware\SetTenantContext::class,
            'txn'    => \App\Http\Middleware\TransactionPerRequest::class,
            'devconsole.enabled' => \App\Http\Middleware\EnsureDevConsoleEnabled::class,
        ]);

         // optional: auto-apply to groups (keeps routes cleaner)
        $middleware->appendToGroup('web', [
            \App\Http\Middleware\SetTenantContext::class,
            \App\Http\Middleware\TransactionPerRequest::class,
        ]);
        $middleware->appendToGroup('api', [
            \App\Http\Middleware\SetTenantContext::class,
            \App\Http\Middleware\TransactionPerRequest::class,
        ]);
    })
    ->withProviders([
        \App\Providers\AppServiceProvider::class,
        \App\Providers\AuthServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
