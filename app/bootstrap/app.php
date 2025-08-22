<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
         // aliases so you can use 'tenant' and 'txn' in routes
        $middleware->alias([
            'tenant' => \App\Http\Middleware\SetTenantContext::class,
            'txn'    => \App\Http\Middleware\TransactionPerRequest::class,
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
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
