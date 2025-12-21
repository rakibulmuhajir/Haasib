<?php

namespace App\Http\Middleware;

use App\Services\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireModuleEnabled
{
    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        $company = app(CurrentCompany::class)->get();

        if (! $company) {
            abort(500, 'Company context required but not set.');
        }

        if ($company->isModuleEnabled($moduleKey)) {
            return $next($request);
        }

        $message = 'This module is not enabled for the selected company.';

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'code' => 'MODULE_NOT_ENABLED',
                'message' => $message,
            ], 403);
        }

        return redirect("/{$company->slug}")
            ->with('error', $message);
    }
}

