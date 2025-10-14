<?php

namespace Modules\Accounting\Domain\Actions;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Modules\Accounting\Models\AuditEntry;
use Modules\Accounting\Models\Company;
use Modules\Accounting\Models\User;

class SwitchCompany
{
    /**
     * Switch the current user's active company.
     *
     * @param  Company|string  $company
     */
    public function execute(User $user, $company, ?Request $request = null): bool
    {
        // Get company object if string provided
        if (is_string($company)) {
            $company = Company::find($company);
            if (! $company) {
                throw new \InvalidArgumentException('Company not found');
            }
        }

        // Check if user can access this company
        if (! $user->canAccessCompany($company)) {
            throw new \InvalidArgumentException('User does not have access to this company');
        }

        // Get current company before switching
        $previousCompanyId = $user->getCurrentCompanyIdAttribute();

        // Switch in session
        if ($request && $request->hasSession()) {
            $request->session()->put('current_company_id', $company->id);
        }
        Session::put('current_company_id', $company->id);

        // Set application context
        $this->setApplicationContext($user, $company);

        // Log audit entry
        if ($previousCompanyId !== $company->id) {
            AuditEntry::logAction(
                'company_switched',
                'user',
                $user->id,
                $user,
                $company,
                [
                    'previous_company_id' => $previousCompanyId,
                    'previous_company_name' => $previousCompanyId ? Company::find($previousCompanyId)?->name : null,
                ],
                [
                    'current_company_id' => $company->id,
                    'current_company_name' => $company->name,
                ]
            );
        }

        return true;
    }

    /**
     * Set the application context for the company.
     */
    protected function setApplicationContext(User $user, Company $company): void
    {
        // Set PostgreSQL settings for RLS
        if (config('database.default') === 'pgsql') {
            \DB::statement("SET LOCAL app.current_user_id = '{$user->id}'");
            \DB::statement("SET LOCAL app.current_company_id = '{$company->id}'");
            \DB::statement('SET LOCAL app.is_super_admin = '.($user->isSuperAdmin() ? 'true' : 'false'));
        }

        // Cache user permissions for this company
        $this->cacheUserPermissions($user, $company);
    }

    /**
     * Cache user permissions for the company.
     */
    protected function cacheUserPermissions(User $user, Company $company): void
    {
        $key = "user_{$user->id}_company_{$company->id}_permissions";

        $permissions = [
            'is_owner' => $user->isOwnerOfCompany($company),
            'is_admin' => $user->isAdminOfCompany($company),
            'role' => $user->companies()
                ->where('auth.companies.id', $company->id)
                ->first()?->pivot->role ?? 'member',
            'enabled_modules' => $company->modules()
                ->where('auth.company_modules.is_active', true)
                ->pluck('modules.key')
                ->toArray(),
        ];

        // Cache for 5 minutes
        cache()->put($key, $permissions, now()->addMinutes(5));
    }

    /**
     * Get the current company for a user with fallback logic.
     */
    public function getCurrentCompany(User $user, ?Request $request = null): ?Company
    {
        return $user->currentCompany();
    }

    /**
     * Clear the current company selection.
     */
    public function clearCurrentCompany(User $user, ?Request $request = null): void
    {
        $previousCompanyId = $user->getCurrentCompanyIdAttribute();

        if ($request && $request->hasSession()) {
            $request->session()->forget('current_company_id');
        }
        Session::forget('current_company_id');

        // Clear application context
        if (config('database.default') === 'pgsql') {
            \DB::statement('SET LOCAL app.current_company_id = NULL');
        }

        // Log if had previous company
        if ($previousCompanyId) {
            AuditEntry::logAction(
                'company_cleared',
                'user',
                $user->id,
                $user,
                null,
                [
                    'previous_company_id' => $previousCompanyId,
                    'previous_company_name' => Company::find($previousCompanyId)?->name,
                ]
            );
        }
    }

    /**
     * Switch to the user's default company.
     */
    public function switchToDefaultCompany(User $user, ?Request $request = null): bool
    {
        $defaultCompany = $user->companies()->first();

        if (! $defaultCompany) {
            return false;
        }

        return $this->execute($user, $defaultCompany, $request);
    }

    /**
     * Get all companies accessible to the user.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAccessibleCompanies(User $user)
    {
        if ($user->isSuperAdmin()) {
            return Company::active()->get();
        }

        return $user->companies()->where('auth.companies.is_active', true)->get();
    }

    /**
     * Check if user can switch to a company.
     *
     * @param  Company|string  $company
     */
    public function canSwitchTo(User $user, $company): bool
    {
        if (is_string($company)) {
            $company = Company::find($company);
            if (! $company) {
                return false;
            }
        }

        return $user->canAccessCompany($company) && $company->isActive();
    }

    /**
     * Bulk switch users in a company (for maintenance mode).
     *
     * @return array Results ['success' => int, 'failed' => int]
     */
    public function bulkSwitchUsers(
        Company $fromCompany,
        Company $toCompany,
        ?User $actingUser = null
    ): array {
        $users = $fromCompany->users()->active()->get();
        $results = ['success' => 0, 'failed' => 0];

        foreach ($users as $user) {
            try {
                if ($user->canAccessCompany($toCompany)) {
                    $this->execute($user, $toCompany);
                    $results['success']++;
                } else {
                    $results['failed']++;
                }
            } catch (\Exception $e) {
                $results['failed']++;
            }
        }

        // Log bulk action
        AuditEntry::logAction(
            'bulk_company_switch',
            'company',
            $fromCompany->id,
            $actingUser,
            $fromCompany,
            null,
            [
                'to_company_id' => $toCompany->id,
                'to_company_name' => $toCompany->name,
                'results' => $results,
            ]
        );

        return $results;
    }
}
