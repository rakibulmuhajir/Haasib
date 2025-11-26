<?php

namespace App\Services;

use App\Constants\Permissions;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;

/**
 * System Role Manager handles system-level role assignments and company bootstrapping.
 */
class SystemRoleManager
{
    // Sentinel team IDs for system roles (from docs/system-users-design.md)
    public const SUPER_ADMIN_TEAM_ID = '00000000-0000-0000-0000-000000000000';
    public const SYSTEMADMIN_TEAM_ID = '00000000-0000-0000-0000-000000000001';
    public const SYSTEM_MANAGER_TEAM_ID = '00000000-0000-0000-0000-000000000002';
    public const SYSTEM_AUDITOR_TEAM_ID = '00000000-0000-0000-0000-000000000003';

    /**
     * Assign system role to user.
     */
    public function assignSystemRole(User $user, string $roleName): bool
    {
        $systemRoles = [
            'super_admin' => self::SUPER_ADMIN_TEAM_ID,
            'systemadmin' => self::SYSTEMADMIN_TEAM_ID,
            'system_manager' => self::SYSTEM_MANAGER_TEAM_ID,
            'system_auditor' => self::SYSTEM_AUDITOR_TEAM_ID,
        ];

        if (!array_key_exists($roleName, $systemRoles)) {
            return false;
        }

        $teamId = $systemRoles[$roleName];
        
        // Find system role
        $role = Role::where('name', $roleName)
                   ->where('team_id', $teamId)
                   ->first();

        if (!$role) {
            return false;
        }

        // Remove any existing system roles
        $this->removeAllSystemRoles($user);

        // Assign new system role
        $user->assignRole($role);

        return true;
    }

    /**
     * Remove all system roles from user.
     */
    public function removeAllSystemRoles(User $user): void
    {
        $systemTeamIds = [
            self::SUPER_ADMIN_TEAM_ID,
            self::SYSTEMADMIN_TEAM_ID,
            self::SYSTEM_MANAGER_TEAM_ID,
            self::SYSTEM_AUDITOR_TEAM_ID,
        ];

        $systemRoles = Role::whereIn('team_id', $systemTeamIds)->get();
        
        foreach ($systemRoles as $role) {
            $user->removeRole($role);
        }
    }

    /**
     * Check if user has system admin privileges.
     */
    public function isSystemAdmin(User $user): bool
    {
        return $user->hasRole('super_admin', self::SUPER_ADMIN_TEAM_ID) ||
               $user->hasRole('systemadmin', self::SYSTEMADMIN_TEAM_ID);
    }

    /**
     * Check if user can perform system operations.
     */
    public function canPerformSystemOperation(User $user, string $permission): bool
    {
        // Check if user has the specific system permission
        return $user->hasPermissionTo($permission) && $user->isSystemUser();
    }

    /**
     * Bootstrap company with default roles.
     * This is called when a new company is created.
     */
    public function bootstrapCompanyRoles(Company $company): void
    {
        $companyRoles = [
            'company_owner',
            'company_admin', 
            'accounting_admin',
            'accounting_operator',
            'accounting_viewer',
            'portal_customer',
            'portal_vendor',
        ];

        foreach ($companyRoles as $roleName) {
            // Create company-scoped role instance
            $role = Role::updateOrCreate([
                'name' => $roleName,
                'team_id' => $company->id,
                'guard_name' => 'web',
            ]);

            // Copy permissions from base role template
            $this->assignPermissionsToCompanyRole($role, $roleName);
        }
    }

    /**
     * Assign permissions to company role based on role template.
     */
    private function assignPermissionsToCompanyRole(Role $role, string $roleName): void
    {
        $rolePermissions = $this->getCompanyRolePermissions();
        
        if (!array_key_exists($roleName, $rolePermissions)) {
            return;
        }

        $permissions = $rolePermissions[$roleName];
        $role->syncPermissions($permissions);
    }

    /**
     * Get permission sets for company roles.
     */
    private function getCompanyRolePermissions(): array
    {
        return [
            'company_owner' => [
                // Full tenant control
                Permissions::COMPANIES_VIEW,
                Permissions::COMPANIES_UPDATE,
                Permissions::COMPANIES_MANAGE_USERS,
                Permissions::USERS_VIEW,
                Permissions::USERS_CREATE,
                Permissions::USERS_UPDATE,
                Permissions::USERS_MANAGE_ROLES,
                
                // Full access to all company modules
                ...array_merge(
                    Permissions::getAllByModule()['acct.customers'],
                    Permissions::getAllByModule()['acct.invoices'],
                    Permissions::getAllByModule()['acct.payments'],
                    Permissions::getAllByModule()['acct.allocations'],
                    Permissions::getAllByModule()['ledger.entries'],
                    Permissions::getAllByModule()['ledger.accounts'],
                    Permissions::getAllByModule()['ledger.period_close'],
                    Permissions::getAllByModule()['ledger.reconciliation'],
                    Permissions::getAllByModule()['reporting.dashboard'],
                    Permissions::getAllByModule()['reporting.reports'],
                    Permissions::getAllByModule()['commands']
                ),
                
                // Audit access
                Permissions::AUDIT_ENTRIES_VIEW,
                Permissions::AUDIT_ENTRIES_EXPORT,
                Permissions::COMPLIANCE_REPORTS_VIEW,
                Permissions::COMPLIANCE_REPORTS_GENERATE,
                
                // Required system permissions
                Permissions::RLS_CONTEXT,
            ],
            
            'company_admin' => [
                // Company management (no billing/destructive)
                Permissions::COMPANIES_VIEW,
                Permissions::COMPANIES_UPDATE,
                Permissions::COMPANIES_MANAGE_USERS,
                
                // User management within company
                Permissions::USERS_VIEW,
                Permissions::USERS_CREATE,
                Permissions::USERS_UPDATE,
                Permissions::USERS_MANAGE_ROLES,
                
                // Full access to company modules (non-posting/destructive)
                ...array_merge(
                    Permissions::getAllByModule()['acct.customers'],
                    Permissions::getAllByModule()['acct.invoices'],
                    Permissions::getAllByModule()['acct.payments'],
                    Permissions::getAllByModule()['acct.allocations']
                ),
                
                // Reporting access
                Permissions::REPORTING_DASHBOARD_VIEW,
                Permissions::REPORTING_REPORTS_VIEW,
                Permissions::REPORTING_REPORTS_GENERATE,
                
                // Commands
                Permissions::COMMANDS_VIEW_HISTORY,
                
                // Required system permissions
                Permissions::RLS_CONTEXT,
            ],
            
            'accounting_admin' => [
                // Accounting full access
                ...array_merge(
                    Permissions::getAllByModule()['acct.customers'],
                    Permissions::getAllByModule()['acct.invoices'],
                    Permissions::getAllByModule()['acct.payments'],
                    Permissions::getAllByModule()['acct.allocations']
                ),
                
                // Ledger management
                ...Permissions::getAllByModule()['ledger.entries'],
                ...Permissions::getAllByModule()['ledger.accounts'],
                Permissions::LEDGER_PERIOD_CLOSE_VIEW,
                Permissions::LEDGER_PERIOD_CLOSE_EXECUTE,
                Permissions::LEDGER_RECONCILIATION_VIEW,
                Permissions::LEDGER_RECONCILIATION_CREATE,
                Permissions::LEDGER_RECONCILIATION_UPDATE,
                
                // Reporting access
                Permissions::REPORTING_DASHBOARD_VIEW,
                Permissions::REPORTING_REPORTS_VIEW,
                Permissions::REPORTING_REPORTS_GENERATE,
                
                // Commands
                Permissions::COMMANDS_EXECUTE,
                Permissions::COMMANDS_VIEW_HISTORY,
                
                // Required system permissions
                Permissions::RLS_CONTEXT,
            ],
            
            'accounting_operator' => [
                // Customer management
                Permissions::ACCT_CUSTOMERS_VIEW,
                Permissions::ACCT_CUSTOMERS_CREATE,
                Permissions::ACCT_CUSTOMERS_UPDATE,
                
                // Invoice management
                Permissions::ACCT_INVOICES_VIEW,
                Permissions::ACCT_INVOICES_CREATE,
                Permissions::ACCT_INVOICES_UPDATE,
                
                // Payment processing
                Permissions::ACCT_PAYMENTS_VIEW,
                Permissions::ACCT_PAYMENTS_CREATE,
                
                // Basic allocation
                Permissions::ACCT_ALLOCATIONS_VIEW,
                Permissions::ACCT_ALLOCATIONS_CREATE,
                
                // Reporting view only
                Permissions::REPORTING_DASHBOARD_VIEW,
                Permissions::REPORTING_REPORTS_VIEW,
                
                // Required system permissions
                Permissions::RLS_CONTEXT,
            ],
            
            'accounting_viewer' => [
                // Read-only access
                Permissions::ACCT_CUSTOMERS_VIEW,
                Permissions::ACCT_INVOICES_VIEW,
                Permissions::ACCT_PAYMENTS_VIEW,
                Permissions::ACCT_ALLOCATIONS_VIEW,
                Permissions::LEDGER_ENTRIES_VIEW,
                Permissions::LEDGER_ACCOUNTS_VIEW,
                Permissions::REPORTING_DASHBOARD_VIEW,
                Permissions::REPORTING_REPORTS_VIEW,
                
                // Required system permissions
                Permissions::RLS_CONTEXT,
            ],
            
            'portal_customer' => [
                // Portal customer permissions (limited)
                Permissions::ACCT_INVOICES_VIEW,
                Permissions::ACCT_PAYMENTS_VIEW,
                
                // Required system permissions
                Permissions::RLS_CONTEXT,
            ],
            
            'portal_vendor' => [
                // Portal vendor permissions (limited)
                Permissions::ACCT_INVOICES_CREATE, // to submit invoices
                Permissions::ACCT_PAYMENTS_VIEW,
                
                // Required system permissions
                Permissions::RLS_CONTEXT,
            ],
        ];
    }

    /**
     * Assign company owner to newly created company.
     */
    public function assignCompanyOwner(Company $company, User $user): void
    {
        // Assign as company owner
        $user->assignToCompany($company, 'company_owner');

        // Log the assignment for audit
        \Log::info('Company owner assigned', [
            'company_id' => $company->id,
            'company_name' => $company->name,
            'user_id' => $user->id,
            'user_email' => $user->email,
        ]);
    }

    /**
     * Get all system users.
     */
    public function getSystemUsers(): \Illuminate\Database\Eloquent\Collection
    {
        $systemTeamIds = [
            self::SUPER_ADMIN_TEAM_ID,
            self::SYSTEMADMIN_TEAM_ID,
            self::SYSTEM_MANAGER_TEAM_ID,
            self::SYSTEM_AUDITOR_TEAM_ID,
        ];

        return User::whereHas('roles', function ($query) use ($systemTeamIds) {
            $query->whereIn('team_id', $systemTeamIds);
        })->get();
    }

    /**
     * Check if company has all required roles set up.
     */
    public function isCompanyRoleSetupComplete(Company $company): bool
    {
        $requiredRoles = [
            'company_owner',
            'company_admin', 
            'accounting_admin',
            'accounting_operator',
            'accounting_viewer',
        ];

        foreach ($requiredRoles as $roleName) {
            if (!Role::where('name', $roleName)->where('team_id', $company->id)->exists()) {
                return false;
            }
        }

        return true;
    }
}