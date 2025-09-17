<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnsureApiHasCompanyContext
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required.',
            ], 401);
        }

        // Check for company ID in header or session
        $companyId = $request->header('X-Company-Id') ??
                    $request->input('company_id') ??
                    $user->current_company_id;

        if (! $companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company context required. Please provide X-Company-Id header.',
                'available_companies' => $user->companies->map(function ($company) {
                    return [
                        'id' => $company->id,
                        'name' => $company->name,
                    ];
                }),
            ], 422);
        }

        // Verify user has access to this company
        if (! $user->companies()->where('companies.id', $companyId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to access this company.',
            ], 403);
        }

        // Set company context
        $request->merge(['current_company_id' => $companyId]);

        // Set in config for easy access
        config(['app.current_company_id' => $companyId]);

        // Set database session variable if using PostgreSQL RLS
        if (config('database.default') === 'pgsql') {
            DB::statement("SET SESSION app.current_company_id = '{$companyId}'");
        }

        return $next($request);
    }
}
