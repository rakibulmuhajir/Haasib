<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\User;
use App\Services\ContextService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';


    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
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

        $payload = array_merge($shared, [
            'auth' => $auth,
            'currentCompany' => $currentCompany,
            'activeCompanyId' => $currentCompanyModel?->id, // This was missing!
            'userCompanies' => $user ? $userCompanies : [],
            'companies' => $user ? $userCompanies : [], // Legacy support
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
            ],
        ]);

        Log::info('[InertiaShare] Company context payload', [
            'user_id' => $user?->id,
            'current_company_id' => $currentCompanyModel?->id,
            'current_company_name' => $currentCompanyModel?->name,
            'active_company_prop' => $currentCompanyModel?->id,
            'user_companies_count' => count($userCompanies),
            'user_company_ids' => collect($userCompanies)->pluck('id')->all(),
            'session_current_company_id' => $request->session()->get('current_company_id'),
            'session_active_company_id' => $request->session()->get('active_company_id'),
        ]);

        return $payload;
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
            'slug' => $company->slug ?? null,
            'industry' => $company->industry,
            'currency' => $company->base_currency ?? 'USD',
            'userRole' => $pivotRole ?? 'member',
            'isActive' => (bool) ($pivotActive ?? true),
            'is_current' => $isCurrent,
            'permissions' => $permissions,
        ];
    }

}
