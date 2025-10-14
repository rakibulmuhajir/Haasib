<?php

namespace App\Http\Middleware;

use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;

class SetTenantContext
{
    public function __construct(private Tenancy $tenancy) {}

    public function handle(Request $request, Closure $next)
    {
        if (! $user = $request->user()) {
            return $next($request);
        }

        $this->tenancy->applyDbSessionSettings($user);

        // Allow company management and invitation endpoints to run without tenant context
        if ($this->isNonTenantRoute($request)) {
            return $next($request);
        }

        $companyId = $this->tenancy->resolveCompanyId($request, $user);

        if (! $companyId) {
            // If no company context can be resolved, we cannot proceed for tenant-aware routes.
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Company context required. Please provide X-Company-Id header.'], 422);
            }

            // For web requests, redirect to a page where the user can select a company.
            // Your technical brief mentions a `NoCompany.vue` page, which this redirect would trigger.
            if ($user->companies()->exists()) {
                // A route to a page with the CompanySwitcher component
                return redirect()->route('dashboard', ['select_company' => true]);
            } else {
                // A route to a "Create your first company" wizard
                // For now, aborting is a safe default if no "create company" route exists.
                // return redirect()->route('company.create');
                abort(403, 'You do not belong to any company and cannot proceed.');
            }
        }

        if ($companyId && $request->hasSession() && ! $request->hasHeader('X-Company-Id')) {
            $request->session()->put('current_company_id', $companyId);
        }

        if (! $this->tenancy->verifyMembership($user->getKey(), $companyId)) {
            abort(403, 'You do not have permission to access this company.');
        }

        $this->tenancy->applyDbSessionSettings($user, $companyId);

        app()->instance('tenant.company_id', $companyId);

        return $next($request);
    }

    /**
     * Determines if the current route should be excluded from tenant context checks.
     * These routes can be accessed by an authenticated user without an active company.
     */
    private function isNonTenantRoute(Request $request): bool
    {
        $excludedPatterns = [
            'profile.*',
            'company.switch',
            'company.set-first',
            'api/v1/me/companies*',
            'api/v1/me/invitations*',
            'api/v1/invitations/*',
            'api/v1/companies', // Assuming POST to create a company
        ];

        foreach ($excludedPatterns as $pattern) {
            if ($request->routeIs($pattern) || $request->is($pattern)) {
                return true;
            }
        }

        return false;
    }
}
