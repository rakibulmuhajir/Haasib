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
        $this->updateSystemContext($company->id);

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
        $this->updateSystemContext(null);

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

        $previousTeamId = $this->permissionRegistrar->getPermissionsTeamId();
        try {
            $this->permissionRegistrar->setPermissionsTeamId($company->id);
            return $user->hasPermissionTo($permission, 'web');
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
        $previousTeamId = $this->permissionRegistrar->getPermissionsTeamId();

        try {
            $this->setContext($company);
            return $callback();
        } finally {
            // Restore previous context
            if ($previousCompany) {
                $this->setContext($previousCompany);
            } else {
                $this->clearContext();
            }
            $this->permissionRegistrar->setPermissionsTeamId($previousTeamId);
        }
    }

    // ============================================================================
    // PRIVATE HELPERS
    // ============================================================================

    private function updateSystemContext(?string $companyId): void
    {
        // Update PostgreSQL session variable
        if ($companyId) {
            DB::select("SELECT set_config('app.current_company_id', ?, true)", [$companyId]);
        } else {
            DB::select("SELECT set_config('app.current_company_id', NULL, true)");
        }

        // Update Spatie team context
        $this->permissionRegistrar->setPermissionsTeamId($companyId);
    }

    private function resolveRole(string $roleName, Company $company): Role
    {
        return Role::where('name', $roleName)
            ->where(fn($q) => $q->where('company_id', $company->id)->orWhereNull('company_id'))
            ->orderByRaw('CASE WHEN company_id = ? THEN 0 ELSE 1 END', [$company->id])
            ->firstOrFail();
    }
}
