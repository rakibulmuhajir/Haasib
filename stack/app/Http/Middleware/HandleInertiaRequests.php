<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\User;
use App\Services\ContextService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $shared = parent::share($request);
        $user = $request->user();
        $contextService = app(ContextService::class);

        $currentCompanyModel = $contextService->getCurrentCompany($user);
        $currentCompany = $this->formatCompanyContext($user, $currentCompanyModel, $contextService, true);

        $companyCollection = $user
            ? $contextService->getActiveCompanies($user)
            : collect();

        $userCompanies = $companyCollection
            ->map(fn (Company $company) => $this->formatCompanyContext(
                $user,
                $company,
                $contextService,
                $currentCompanyModel?->id === $company->id
            ))
            ->filter()
            ->values()
            ->all();

        $auth = $shared['auth'] ?? [];
        $auth['user'] = $user;
        if ($user) {
            $auth['company_id'] = $currentCompanyModel?->id;
            $auth['company_permissions'] = $currentCompanyModel
                ? $contextService->getUserPermissions($user, $currentCompanyModel)
                : [];
        }

        return array_merge($shared, [
            'auth' => $auth,
            'currentCompany' => $currentCompany,
            'userCompanies' => $user ? $userCompanies : [],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
            ],
        ]);
    }

    private function formatCompanyContext(
        ?User $user,
        ?Company $company,
        ContextService $contextService,
        bool $isCurrent = false
    ): ?array {
        if (! $company) {
            return null;
        }

        $pivotRole = null;
        $pivotActive = null;

        if ($user) {
            $relation = $company->pivot ?? null;

            if (! $relation && method_exists($company, 'users')) {
                $relation = $company->users()->where('user_id', $user->id)->first()?->pivot;
            }

            $pivotRole = $relation->role ?? ($user->isSuperAdmin() ? 'super_admin' : null);
            $pivotActive = $relation->is_active ?? true;
        }

        $permissions = $user
            ? $contextService->getUserPermissions($user, $company)
            : [];

        return [
            'id' => $company->id,
            'name' => $company->name,
            'slug' => $company->slug,
            'industry' => $company->industry,
            'currency' => $company->currency ?? 'USD',
            'userRole' => $pivotRole ?? 'member',
            'isActive' => (bool) ($pivotActive ?? true),
            'is_current' => $isCurrent,
            'permissions' => $permissions,
        ];
    }
}
