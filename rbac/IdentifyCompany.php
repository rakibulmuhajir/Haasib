<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Services\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyCompany
{
    public function __construct(
        private CurrentCompany $currentCompany
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $companyParam = $request->route('company');

        if (!$companyParam) {
            abort(404, 'Company not specified.');
        }

        // Resolve company by slug or ID
        $company = $companyParam instanceof Company
            ? $companyParam
            : Company::where('slug', $companyParam)
                ->orWhere('id', $companyParam)
                ->first();

        if (!$company) {
            abort(404, 'Company not found.');
        }

        if (!$company->isActive()) {
            abort(403, 'This company is not active.');
        }

        // Verify user membership
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        // Super admin bypass
        if (!$user->isSuperAdmin() && !$user->belongsToCompany($company)) {
            abort(403, 'You do not have access to this company.');
        }

        // Set company context (also sets Spatie team ID)
        $this->currentCompany->set($company);

        // Share with Inertia
        if (class_exists(\Inertia\Inertia::class)) {
            \Inertia\Inertia::share('currentCompany', [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ]);

            // Share user permissions for this company
            \Inertia\Inertia::share('permissions', $user->getAllPermissions()->pluck('name'));
            \Inertia\Inertia::share('role', $user->getRoleNames()->first());
        }

        return $next($request);
    }
}
