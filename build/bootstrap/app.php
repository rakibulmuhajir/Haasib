<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IdentifyCompany;
use App\Http\Middleware\CheckFirstTimeUser;
use App\Http\Middleware\RequireIndustry;
use App\Http\Middleware\RequireModuleEnabled;
use App\Console\Commands\CleanupIdempotencyCommand;
use App\Console\Commands\SyncPermissions;
use App\Console\Commands\SyncRolePermissions;
use App\Console\Commands\SyncCompanyUserRoles;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            CheckFirstTimeUser::class,
        ]);

        $middleware->alias([
            'identify.company' => IdentifyCompany::class,
            'require.industry' => RequireIndustry::class,
            'require.module' => RequireModuleEnabled::class,
        ]);
    })
    ->withCommands([
        CleanupIdempotencyCommand::class,
        SyncPermissions::class,
        SyncRolePermissions::class,
        SyncCompanyUserRoles::class,
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $exception, Request $request) {
            if (app()->hasDebugModeEnabled() || $request->expectsJson() || ! $request->acceptsHtml()) {
                return $response;
            }

            $status = $response->getStatusCode();

            if ($status === 419) {
                return redirect()->back()->with('error', 'Your session expired. Please try again.');
            }

            if (in_array($status, [500, 503, 403, 404], true)) {
                // Rendering the Inertia page re-runs HandleInertiaRequests'
                // shared data, which resolves the current company. When that
                // resolution is what failed in the first place, the error page
                // throws the same exception again and the user gets nothing.
                // Fall back to the plain response rather than cascade.
                try {
                    return Inertia::render('Error', ['status' => $status])
                        ->toResponse($request)
                        ->setStatusCode($status);
                } catch (\Throwable) {
                    return $response;
                }
            }

            return $response;
        });
    })->create();
