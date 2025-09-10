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

        $inApi = $request->is('api/v1/*');

        // Allow company management and invitation endpoints to run without tenant context
        if ($inApi && (
            $request->is('api/v1/me/companies*') ||
            $request->is('api/v1/me/invitations') ||
            ($request->is('api/v1/companies') && $request->isMethod('post')) ||
            ($request->is('api/v1/companies/*/invite') && $request->isMethod('post')) ||
            $request->is('api/v1/invitations/*')
        )) {
            return $next($request);
        }

        // Enforce tenant context for API routes (except the ones above), not for web
        $enforce = $inApi;

        $companyId = $this->tenancy->resolveCompanyId($request, $user);

        if (! $companyId) {
            return $enforce
                ? response()->json(['message' => 'Company context required'], 422)
                : $next($request);
        }

        if ($companyId && $request->hasSession() && ! $request->hasHeader('X-Company-Id')) {
            $request->session()->put('current_company_id', $companyId);
        }

        if (! $this->tenancy->verifyMembership($user->getKey(), $companyId)) {
            return $enforce
                ? abort(403, 'Not a member of the selected company')
                : $next($request);
        }

        $this->tenancy->applyDbSessionSettings($user, $companyId);

        app()->instance('tenant.company_id', $companyId);

        return $next($request);
    }
}
