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

    public function handle(Request $request, Closure $next): Response
    {
        $companyParam = $request->route('company');

        if (!$companyParam) {
            abort(404, 'Company not specified.');
        }

        $company = $companyParam instanceof Company
            ? $companyParam
            : Company::where('slug', $companyParam)
                ->orWhere('id', $companyParam)
                ->first();

        if (!$company) {
            abort(404, 'Company not found.');
        }

        if (!$company->is_active) {
            abort(403, 'This company is not active.');
        }

        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        if (!$user->isGodMode() && !$this->userBelongsToCompany($user, $company)) {
            abort(403, 'You do not have access to this company.');
        }

        $this->currentCompany->set($company);

        if (class_exists(\Inertia\Inertia::class)) {
            \Inertia\Inertia::share('currentCompany', [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ]);

            if ($user->isGodMode()) {
                \Inertia\Inertia::share('permissions', ['*']);
                \Inertia\Inertia::share('role', $user->isSuperAdmin() ? 'super_admin' : 'system_admin');
            } else {
                \Inertia\Inertia::share('permissions', $user->getAllPermissions()->pluck('name'));
                \Inertia\Inertia::share('role', $user->getRoleNames()->first());
            }
        }

        return $next($request);
    }

    private function userBelongsToCompany($user, Company $company): bool
    {
        return $user->companies()
            ->where('companies.id', $company->id)
            ->wherePivot('is_active', true)
            ->exists();
    }
}
