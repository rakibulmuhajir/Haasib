<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated');
        }

        // Super admins can access everything
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Check if it's a system permission (starts with 'system.')
        if (str_starts_with($permission, 'system.')) {
            // System permissions are checked without team context
            if (! $user->hasPermissionTo($permission)) {
                abort(403, 'Unauthorized');
            }

            return $next($request);
        }

        // For company-specific permissions, get company context
        $company = $request->route('company')
                  ?? $request->session()->get('current_company_id')
                  ?? $request->input('current_company_id')
                  ?? $user->current_company;

        // If we have a company ID as string, fetch the model
        if (is_string($company)) {
            $company = \App\Models\Company::find($company);
        }

        if (! $company) {
            abort(403, 'Company not found');
        }

        // Set the team context for permission checking
        $previousTeamId = getPermissionsTeamId();
        setPermissionsTeamId($company->id);

        try {
            // Refresh the user to get team-scoped permissions
            $user->refresh();

            if (! $user->hasPermissionTo($permission)) {
                abort(403, 'Unauthorized');
            }

            return $next($request);
        } finally {
            // Always restore the original team context
            setPermissionsTeamId($previousTeamId);
        }
    }
}
