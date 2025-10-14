<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;

class ContextService
{
    public function __construct(private readonly AuthService $authService) {}

    public function setCurrentCompany(User $user, Company $company): bool
    {
        if (! $this->authService->canAccessCompany($user, $company)) {
            return false;
        }

        if (Request::hasSession()) {
            Request::session()->put('current_company_id', $company->id);
        }
        Session::put('current_company_id', $company->id);

        $this->applyTenantContext($user, $company);
        $this->cacheUserPermissions($user, $company);

        return true;
    }

    public function getCurrentCompany(?User $user = null): ?Company
    {
        $user = $user ?? auth()->user();

        if (! $user) {
            return null;
        }

        $companyId = null;

        if (Request::hasSession()) {
            $companyId = Request::session()->get('current_company_id');
        }

        if (! $companyId) {
            $companyId = Session::get('current_company_id');
        }

        if ($companyId) {
            return $user->isSuperAdmin()
                ? Company::find($companyId)
                : $user->companies()->where('auth.companies.id', $companyId)->first();
        }

        return $user->companies()->first();
    }

    public function clearCurrentCompany(User $user): void
    {
        if (Request::hasSession()) {
            Request::session()->forget('current_company_id');
        }
        Session::forget('current_company_id');

        $this->resetTenantContext();
        $this->clearPermissionCache($user);
    }

    public function cacheUserPermissions(User $user, Company $company, int $minutes = 5): array
    {
        $cacheKey = $this->permissionCacheKey($user->id, $company->id);

        return Cache::remember($cacheKey, $minutes * 60, function () use ($user, $company) {
            return $this->authService->getUserPermissions($user, $company);
        });
    }

    public function getUserPermissions(User $user, Company $company): array
    {
        $cacheKey = $this->permissionCacheKey($user->id, $company->id);

        return Cache::has($cacheKey)
            ? Cache::get($cacheKey)
            : $this->cacheUserPermissions($user, $company);
    }

    public function clearPermissionCache(User $user, ?Company $company = null): void
    {
        if ($company) {
            Cache::forget($this->permissionCacheKey($user->id, $company->id));

            return;
        }

        $user->companies()->pluck('auth.companies.id')->each(function (string $companyId) use ($user) {
            Cache::forget($this->permissionCacheKey($user->id, $companyId));
        });
    }

    public function getActiveCompanies(User $user)
    {
        return $user->companies()
            ->where('auth.companies.is_active', true)
            ->withPivot('role', 'invited_by_user_id', 'is_active')
            ->wherePivot('is_active', true)
            ->orderBy('auth.company_user.created_at')
            ->get();
    }

    public function canAccessCurrentContext(User $user, string $permission): bool
    {
        $company = $this->getCurrentCompany($user);

        return $company ? $this->authService->canAccessCompany($user, $company, $permission) : false;
    }

    public function setCLICompanyContext(Company $company): void
    {
        DB::selectOne("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
        DB::selectOne("SELECT set_config('app.current_user_id', NULL, false)");
    }

    public function clearCLICompanyContext(): void
    {
        $this->resetTenantContext();
    }

    public function getContextMetadata(?User $user = null): array
    {
        $user = $user ?? auth()->user();

        if (! $user) {
            return [
                'user_id' => null,
                'company_id' => null,
                'company_name' => null,
                'user_role' => null,
                'permissions' => [],
                'is_super_admin' => false,
            ];
        }

        $company = $this->getCurrentCompany($user);

        return [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'company_id' => $company?->id,
            'company_name' => $company?->name,
            'user_role' => $company ? $this->authService->getUserRole($user, $company) : null,
            'permissions' => $company ? $this->getUserPermissions($user, $company) : [],
            'is_super_admin' => $user->isSuperAdmin(),
            'available_companies' => $this->getActiveCompanies($user)->count(),
            'session_id' => session()->getId(),
        ];
    }

    public function restoreLastCompany(User $user): bool
    {
        $lastCompanyId = $user->getSetting('last_company_id');

        if (! $lastCompanyId) {
            return false;
        }

        $company = Company::find($lastCompanyId);

        if (! $company || ! $this->authService->canAccessCompany($user, $company)) {
            $user->setSetting('last_company_id', null);

            return false;
        }

        return $this->setCurrentCompany($user, $company);
    }

    public function saveLastCompany(User $user): void
    {
        $company = $this->getCurrentCompany($user);

        if ($company) {
            $user->setSetting('last_company_id', $company->id);
        }
    }

    public function hasCompanyContext(): bool
    {
        return (Request::hasSession() && Request::session()->has('current_company_id'))
            || Session::has('current_company_id');
    }

    public function getAPIContext(?User $user = null): array
    {
        $user = $user ?? auth()->user();
        $company = $this->getCurrentCompany($user);

        return [
            'success' => true,
            'data' => [
                'user' => $user?->only(['id', 'name', 'email', 'system_role']),
                'company' => $company?->only(['id', 'name', 'slug', 'country', 'base_currency']),
                'permissions' => $company && $user ? $this->getUserPermissions($user, $company) : [],
                'role' => $company && $user ? $this->authService->getUserRole($user, $company) : null,
            ],
        ];
    }

    public function getCurrentUser(): ?User
    {
        // For CLI context, get from PostgreSQL config
        $userId = DB::selectOne("SELECT current_setting('app.current_user_id', true) as user_id")->user_id;

        if ($userId) {
            return User::find($userId);
        }

        // For web context, get from auth
        return auth()->user();
    }

    protected function applyTenantContext(User $user, Company $company): void
    {
        DB::selectOne("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
        DB::selectOne("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
        DB::selectOne("SELECT set_config('app.is_super_admin', ?, false)", [$user->isSuperAdmin() ? 'true' : 'false']);
    }

    protected function resetTenantContext(): void
    {
        DB::selectOne("SELECT set_config('app.current_company_id', NULL, false)");
        DB::selectOne("SELECT set_config('app.current_user_id', NULL, false)");
        DB::selectOne("SELECT set_config('app.is_super_admin', NULL, false)");
    }

    protected function permissionCacheKey(string $userId, string $companyId): string
    {
        return "user_{$userId}_company_{$companyId}_permissions";
    }
}
