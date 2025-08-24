<?php

// app/Http/Middleware/SetTenantContext.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return $next($request); // unauth routes (e.g., /health)
        }

        // Prefer header for API, session for web
        $companyId = $request->header('X-Company-Id') ?: $request->session()->get('current_company_id');

        // Fallback: if exactly one membership, auto-pick
        if (!$companyId) {
            $companyIds = $user->companies()->pluck('companies.id')->all();
            if (count($companyIds) === 1) {
                $companyId = $companyIds[0];
            }
        }

        if (!$companyId) {
            return response()->json(['message' => 'Company context required'], 422);
        }

        $isMember = DB::table('company_user')
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->exists();

        if (!$isMember) {
            abort(403, 'Not a member of the selected company');
        }

        // RLS: scope this request in Postgres
        DB::statement('set local app.current_company_id = ?', [$companyId]);

        // Optional: bind for convenience
        app()->instance('tenant.company_id', $companyId);

        return $next($request);
    }
}
