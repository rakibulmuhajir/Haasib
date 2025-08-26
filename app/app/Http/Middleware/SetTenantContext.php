<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SetTenantContext
{
    public function handle(Request $request, Closure $next)
    {
        if (! $user = $request->user()) {
            return $next($request);
        }

        $inApi = $request->is('api/v1/*');

        // Allow company management endpoints to run without tenant context
        if ($inApi && $request->is('api/v1/me/companies*')) {
            return $next($request);
        }

        // Enforce tenant context for API routes (except the ones above), not for web
        $enforce = $inApi;

        // Prefer header for API, session for web – but only touch session if it exists
        $companyId = $request->header('X-Company-Id');
        if (! $companyId && $request->hasSession()) {
            $companyId = $request->session()->get('current_company_id');
        }

        // Fallback: if the user has any companies, pick the first (no session write on API)
        if (! $companyId) {
            $companyId = $user->companies()
                ->limit(1)
                ->pluck($user->companies()->getRelated()->getQualifiedKeyName()) // e.g. "auth.companies.id"
                ->first();
        }

        if (! $companyId) {
            return $enforce
                ? response()->json(['message' => 'Company context required'], 422)
                : $next($request);
        }

        if ($companyId && $request->hasSession() && ! $request->hasHeader('X-Company-Id')) {
    $request->session()->put('current_company_id', $companyId);
}

        // Verify membership via schema-qualified pivot
        $isMember = DB::table('auth.company_user')
            ->where('user_id', $user->getKey())
            ->where('company_id', $companyId)
            ->exists();

        if (! $isMember) {
            return $enforce
                ? abort(403, 'Not a member of the selected company')
                : $next($request);
        }

        // Set Postgres LOCAL setting for RLS; safe no-op on unsupported drivers
        try {
            DB::select("select set_config('app.current_company_id', ?, true)", [$companyId]);
        } catch (\Throwable $e) {
            // noop
        }

        app()->instance('tenant.company_id', $companyId);

        return $next($request);
    }
}
