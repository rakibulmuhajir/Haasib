<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;

class CompanyContextService
{
    private ?Company $company = null;
    private PermissionRegistrar $permissionRegistrar;

    public function __construct(PermissionRegistrar $permissionRegistrar)
    {
        $this->permissionRegistrar = $permissionRegistrar;
    }

    // ============================================================================
    // CONTEXT MANAGEMENT
    // ============================================================================

    public function setContext(Company $company): void
    {
        $this->company = $company;
        $this->updateSystemContext($company->id, $company->base_currency);

        // Set Spatie team ID for permission checks
        $this->permissionRegistrar->setPermissionsTeamId($company->id);

        Log::debug('Company context set', [
            'company_id' => $company->id,
            'company_name' => $company->name,
        ]);
    }

    public function setContextBySlug(string $slug): void
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        $this->setContext($company);
    }

    public function setContextById(string $id): void
    {
        $company = Company::findOrFail($id);
        $this->setContext($company);
    }

    public function clearContext(): void
    {
        $this->company = null;

        // Reset Spatie team ID
        $this->permissionRegistrar->setPermissionsTeamId(null);

        $conn = DB::connection();
        if ($conn->transactionLevel() > 0) {
            $conn->afterCommit(fn () => $this->updateSystemContextImmediate(null));
            $conn->afterRollback(fn () => $this->updateSystemContextImmediate(null));
        } else {
            $this->updateSystemContextImmediate(null);
        }

        Log::debug('Company context cleared');
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function getCompanyId(): ?string
    {
        return $this->company?->id;
    }

    public function requireCompany(): Company
    {
        if (!$this->company) {
            throw new \RuntimeException('Company context required but not set');
        }
        return $this->company;
    }

    // ============================================================================
    // ROLE OPERATIONS
    // ============================================================================

    public function assignRole(User $user, string|Role $role): void
    {
        $company = $this->requireCompany();

        // Resolve role if string
        if (is_string($role)) {
            $role = $this->resolveRole($role, $company);
        }

        // Set team context and assign
        $previousTeamId = $this->permissionRegistrar->getPermissionsTeamId();
        try {
            $this->permissionRegistrar->setPermissionsTeamId($company->id);
            $user->assignRole($role);  // ✅ CORRECT: No second parameter

            Log::info('Role assigned', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'role' => $role->name,
                'company_id' => $company->id,
            ]);
        } finally {
            $this->permissionRegistrar->setPermissionsTeamId($previousTeamId);
        }
    }

    public function removeRole(User $user, string|Role $role): void
    {
        $company = $this->requireCompany();

        // Resolve role if string
        if (is_string($role)) {
            $role = $this->resolveRole($role, $company);
        }

        // Set team context and remove
        $previousTeamId = $this->permissionRegistrar->getPermissionsTeamId();
        try {
            $this->permissionRegistrar->setPermissionsTeamId($company->id);
            $user->removeRole($role);  // ✅ CORRECT: Role object, not Company

            Log::info('Role removed', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'role' => $role->name,
                'company_id' => $company->id,
            ]);
        } finally {
            $this->permissionRegistrar->setPermissionsTeamId($previousTeamId);
        }
    }

    public function syncRoles(User $user, array $roles): void
    {
        $company = $this->requireCompany();

        $previousTeamId = $this->permissionRegistrar->getPermissionsTeamId();
        try {
            $this->permissionRegistrar->setPermissionsTeamId($company->id);
            $user->syncRoles($roles);

            Log::info('Roles synced', [
                'user_id' => $user->id,
                'roles' => $roles,
                'company_id' => $company->id,
            ]);
        } finally {
            $this->permissionRegistrar->setPermissionsTeamId($previousTeamId);
        }
    }

    // ============================================================================
    // PERMISSION CHECKS
    // ============================================================================

    public function userHasPermission(User $user, string $permission): bool
    {
        $company = $this->getCompany();
        if (!$company) {
            return false;
        }

        // God mode holds every permission in every company, which is what the rest of the
        // application already assumes: HandleInertiaRequests hands these users the
        // 'super_admin' role, and CompanyController::show lets them past the membership
        // check. Only this method disagreed - it waived membership and then asked Spatie for
        // a role assignment the user does not have in that company, so every permission came
        // back false.
        //
        // The effect was silent absence rather than a refusal: a platform admin opening a
        // company saw the page render with the gated parts simply missing, and nothing
        // saying why.
        if (str_starts_with($user->id, '00000000-0000-0000-0000-')) {
            return true;
        }

        $isActiveMember = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();

        if (! $isActiveMember) {
            return false;
        }

        $previousTeamId = $this->permissionRegistrar->getPermissionsTeamId();
        try {
            $this->permissionRegistrar->setPermissionsTeamId($company->id);
            try {
                return $user->hasPermissionTo($permission, 'web');
            } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
                // Gracefully handle missing permission records
                return false;
            }
        } finally {
            $this->permissionRegistrar->setPermissionsTeamId($previousTeamId);
        }
    }

    // ============================================================================
    // NESTED CONTEXT
    // ============================================================================

    public function withContext(Company $company, callable $callback): mixed
    {
        $previousCompany = $this->company;

        // Not every caller arrived through this service: console commands,
        // seeders and tests set app.current_company_id with set_config directly.
        // Clearing the session variable on the way out would strip the context
        // out from under them, and the writes that follow would then be
        // rejected by row level security -- or, worse, silently match nothing.
        $previousCompanyId = $this->currentSetting('app.current_company_id');
        $previousCurrency = $this->currentSetting('app.company_base_currency');

        try {
            $this->setContext($company);
            return $callback();
        } finally {
            if ($previousCompany) {
                $this->setContext($previousCompany);
            } else {
                $this->company = null;
                $this->permissionRegistrar->setPermissionsTeamId(null);
                DB::select("SELECT set_config('app.current_company_id', ?, false)", [$previousCompanyId]);
                DB::select("SELECT set_config('app.company_base_currency', ?, false)", [$previousCurrency]);
            }
        }
    }

    private function currentSetting(string $name): string
    {
        return (string) (DB::selectOne('SELECT current_setting(?, true) AS value', [$name])->value ?? '');
    }

    /**
     * Run work that legitimately spans every company -- console commands,
     * seeders, deployment repairs -- using the policies' own super-admin
     * escape hatch rather than turning row level security off.
     *
     * Session scope on purpose: SET LOCAL outside a transaction silently does
     * nothing, so a failure here leaves a session setting behind rather than a
     * table with its isolation switched off.
     */
    public function crossCompany(callable $callback): mixed
    {
        $previous = $this->currentSetting('app.is_super_admin');

        DB::select("SELECT set_config('app.is_super_admin', 'true', false)");

        try {
            return $callback();
        } finally {
            DB::select("SELECT set_config('app.is_super_admin', ?, false)", [$previous]);
        }
    }

    // ============================================================================
    // PRIVATE HELPERS
    // ============================================================================

    private function updateSystemContext(?string $companyId, ?string $baseCurrency = null): void
    {
        // Update PostgreSQL session variable
        if ($companyId) {
            DB::select("SELECT set_config('app.current_company_id', ?, false)", [$companyId]);
            if ($baseCurrency) {
                DB::select("SELECT set_config('app.company_base_currency', ?, false)", [$baseCurrency]);
            }
        } else {
            $this->updateSystemContextImmediate(null);
        }

        // Update Spatie team context
        $this->permissionRegistrar->setPermissionsTeamId($companyId);
    }

    /**
     * Reset or set config immediately (no deferral).
     */
    private function updateSystemContextImmediate(?string $companyId, ?string $baseCurrency = null): void
    {
        if ($companyId) {
            DB::select("SELECT set_config('app.current_company_id', ?, false)", [$companyId]);
            if ($baseCurrency) {
                DB::select("SELECT set_config('app.company_base_currency', ?, false)", [$baseCurrency]);
            }
        } else {
            DB::statement("RESET app.current_company_id");
            DB::statement("RESET app.company_base_currency");
        }
    }

    private function resolveRole(string $roleName, Company $company): Role
    {
        return Role::where('name', $roleName)
            ->where(fn($q) => $q->where('company_id', $company->id)->orWhereNull('company_id'))
            ->orderByRaw('CASE WHEN company_id = ? THEN 0 ELSE 1 END', [$company->id])
            ->firstOrFail();
    }
}
