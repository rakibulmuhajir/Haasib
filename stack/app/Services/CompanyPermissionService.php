<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class CompanyPermissionService extends BaseService
{
    public function __construct(ServiceContext $context)
    {
        parent::__construct($context);
    }

    /**
     * Check if a user has a specific permission within a company context
     */
    public function userHasCompanyPermission(User $user, Company $company, string $permission): bool
    {
        // Validate company access for current context user
        $this->validateCompanyAccess($company->id);
        
        // First check if user has access to the company
        if (! $this->userHasAccessToCompany($user, $company)) {
            return false;
        }

        // Get user's role in this company
        $companyUser = $company->users()->where('user_id', $user->id)->first();
        if (! $companyUser || ! $companyUser->pivot->is_active) {
            return false;
        }

        // Check permissions based on company role
        return $this->checkRolePermission($companyUser->pivot->role, $permission, $user, $company);
    }

    /**
     * Check if a user has any access to a company
     */
    public function userHasAccessToCompany(User $user, Company $company): bool
    {
        $cacheKey = "user_{$user->id}_company_access_{$company->id}";

        return Cache::remember($cacheKey, 300, function () use ($user, $company) {
            return $company->users()
                ->where('user_id', $user->id)
                ->where('company_user.is_active', true)
                ->exists();
        });
    }

    /**
     * Get user's role in a specific company
     */
    public function getUserRoleInCompany(User $user, Company $company): ?string
    {
        $companyUser = $company->users()->where('user_id', $user->id)->first();

        return $companyUser?->pivot?->role;
    }

    /**
     * Check if user can perform a specific action in company based on their role
     */
    private function checkRolePermission(string $role, string $permission, User $user, Company $company): bool
    {
        // Super admins have all permissions
        if (in_array($user->system_role, ['system_owner', 'super_admin'])) {
            // Log admin access for audit
            $this->audit('permission.admin_access_granted', [
                'company_id' => $company->id,
                'user_id' => $user->id,
                'permission' => $permission,
                'role' => $user->system_role,
                'checked_by_user_id' => $this->getUserId(),
            ]);
            
            return true;
        }

        // Role-based permission mapping
        $rolePermissions = $this->getRolePermissions();

        // Get permissions for user's role in this company
        $permissions = $rolePermissions[$role] ?? [];

        // Check if the requested permission is in the role's permissions
        if (in_array($permission, $permissions)) {
            return true;
        }

        // Check for wildcard permissions
        foreach ($permissions as $rolePermission) {
            if ($this->matchesWildcardPermission($permission, $rolePermission)) {
                return true;
            }
        }

        // Fall back to Laravel's native permission system
        return $user->hasPermissionTo($permission);
    }

    /**
     * Get permission mapping for company roles
     */
    private function getRolePermissions(): array
    {
        return [
            'owner' => [
                'company.manage',
                'company.delete',
                'company.users.manage',
                'company.invite',
                'company.modules.manage',
                'accounting.manage',
                'invoices.manage',
                'invoices.view',
                'invoices.create',
                'invoices.update',
                'invoices.delete',
                'invoices.send',
                'invoices.export',
                'reports.view',
                'settings.manage',
                '*',
            ],
            'admin' => [
                'company.manage',
                'company.users.manage',
                'company.invite',
                'company.modules.manage',
                'accounting.manage',
                'invoices.manage',
                'invoices.view',
                'invoices.create',
                'invoices.update',
                'invoices.delete',
                'invoices.send',
                'invoices.export',
                'reports.view',
                'settings.manage',
            ],
            'accountant' => [
                'accounting.manage',
                'accounting.entries.create',
                'accounting.entries.edit',
                'accounting.entries.delete',
                'invoices.manage',
                'invoices.view',
                'invoices.create',
                'invoices.edit',
                'invoices.update',
                'reports.view',
                'journal_entries.view',
                'chart_of_accounts.view',
            ],
            'member' => [
                'accounting.view',
                'invoices.view',
                'reports.view',
                'journal_entries.view',
                'chart_of_accounts.view',
            ],
            'viewer' => [
                'accounting.view',
                'invoices.view',
                'reports.view',
            ],
        ];
    }

    /**
     * Check if a permission matches a wildcard pattern
     */
    private function matchesWildcardPermission(string $permission, string $pattern): bool
    {
        if ($pattern === '*') {
            return true;
        }

        if (str_ends_with($pattern, '.*')) {
            $prefix = str_replace('.*', '', $pattern);

            return str_starts_with($permission, $prefix.'.');
        }

        return false;
    }

    /**
     * Get all permissions available to a user in a company
     */
    public function getUserPermissionsInCompany(User $user, Company $company): array
    {
        if (! $this->userHasAccessToCompany($user, $company)) {
            return [];
        }

        $companyUser = $company->users()->where('user_id', $user->id)->first();
        if (! $companyUser || ! $companyUser->is_active) {
            return [];
        }

        // Super admins have all permissions
        if (in_array($user->system_role, ['system_owner', 'super_admin'])) {
            return ['*'];
        }

        $rolePermissions = $this->getRolePermissions();
        $permissions = $rolePermissions[$companyUser->role] ?? [];

        // Add native Laravel permissions
        $nativePermissions = $user->getAllPermissions()->pluck('name')->toArray();

        return array_unique(array_merge($permissions, $nativePermissions));
    }

    /**
     * Clear permission cache for a user-company pair
     */
    public function clearPermissionCache(User $user, Company $company): void
    {
        // Validate company access
        $this->validateCompanyAccess($company->id);
        
        $cacheKey = "user_{$user->id}_company_access_{$company->id}";
        Cache::forget($cacheKey);
        
        // Log cache invalidation for audit
        $this->audit('permission.cache_cleared', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'cache_key' => $cacheKey,
            'cleared_by_user_id' => $this->getUserId(),
        ]);
    }
}
