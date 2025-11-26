<?php

namespace Database\Seeders;

use App\Constants\Permissions;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding roles and permissions...');

        DB::beginTransaction();

        try {
            $this->createPermissions();
            $this->createRoles();
            $this->assignPermissionsToRoles();
            $this->assignSystemRoles();
            $this->assignCompanyRoles();

            DB::commit();
            $this->command->info('✓ Roles and permissions seeded successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Permission seeding failed: '.$e->getMessage());
            throw $e;
        }
    }

    private function createPermissions(): void
    {
        // Get all permissions from our standardized constants
        $allPermissions = Permissions::getAll();
        
        // Create permissions with standardized descriptions
        $permissionDescriptions = [
            // System permissions
            Permissions::SYSTEM_ADMIN => 'Full system administration access',
            Permissions::SYSTEM_AUDIT => 'Access to system audit logs',
            Permissions::RLS_CONTEXT => 'Required for RLS context validation',
            
            // Company permissions
            Permissions::COMPANIES_VIEW => 'View companies',
            Permissions::COMPANIES_CREATE => 'Create new companies',
            Permissions::COMPANIES_UPDATE => 'Update company information',
            Permissions::COMPANIES_DELETE => 'Delete companies',
            Permissions::COMPANIES_MANAGE_USERS => 'Manage company user assignments',
            
            // User permissions
            Permissions::USERS_VIEW => 'View users',
            Permissions::USERS_CREATE => 'Create new users',
            Permissions::USERS_UPDATE => 'Update user information',
            Permissions::USERS_DELETE => 'Delete users',
            Permissions::USERS_MANAGE_ROLES => 'Manage user roles and permissions',
            
            // Customer permissions
            Permissions::ACCT_CUSTOMERS_VIEW => 'View customers',
            Permissions::ACCT_CUSTOMERS_CREATE => 'Create new customers',
            Permissions::ACCT_CUSTOMERS_UPDATE => 'Update customer information',
            Permissions::ACCT_CUSTOMERS_DELETE => 'Delete customers',
            Permissions::ACCT_CUSTOMERS_MANAGE_CREDIT => 'Manage customer credit limits',
            
            // Invoice permissions
            Permissions::ACCT_INVOICES_VIEW => 'View invoices',
            Permissions::ACCT_INVOICES_CREATE => 'Create new invoices',
            Permissions::ACCT_INVOICES_UPDATE => 'Update invoice information',
            Permissions::ACCT_INVOICES_DELETE => 'Delete invoices',
            Permissions::ACCT_INVOICES_VOID => 'Void invoices',
            Permissions::ACCT_INVOICES_APPROVE => 'Approve invoices',
            
            // Payment permissions
            Permissions::ACCT_PAYMENTS_VIEW => 'View payments',
            Permissions::ACCT_PAYMENTS_CREATE => 'Process new payments',
            Permissions::ACCT_PAYMENTS_UPDATE => 'Update payment information',
            Permissions::ACCT_PAYMENTS_DELETE => 'Delete payments',
            Permissions::ACCT_PAYMENTS_VOID => 'Void payments',
            Permissions::ACCT_PAYMENTS_PROCESS_BATCH => 'Process batch payments',
        ];

        foreach ($allPermissions as $permission) {
            $description = $permissionDescriptions[$permission] ?? ucfirst(str_replace(['_', '.'], ' ', $permission));
            
            Permission::updateOrCreate(['name' => $permission], [
                'guard_name' => 'web',
                'description' => $description,
            ]);
        }

        $this->command->info('✓ Created '.count($allPermissions).' permissions');
    }

    private function createRoles(): void
    {
        $roles = [
            // System roles (team_id will be set to sentinel UUIDs later)
            'super_admin',
            'systemadmin',
            'system_manager',
            'system_auditor',
            
            // Company roles (team_id will be set to company_id when assigned)
            'company_owner',
            'company_admin',
            'accounting_admin',
            'accounting_operator',
            'accounting_viewer',
            'portal_customer',
            'portal_vendor',
        ];

        foreach ($roles as $name) {
            Role::updateOrCreate(['name' => $name], [
                'guard_name' => 'web',
            ]);
        }

        $this->command->info('✓ Created '.count($roles).' roles');
    }

    private function assignPermissionsToRoles(): void
    {
        // Define company-tier role permissions based on RBAC brief
        $companyRolePermissions = [
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

        // Assign permissions to system roles first
        $this->assignSystemRolePermissions();

        foreach ($companyRolePermissions as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $permissionModels = Permission::whereIn('name', $permissions)->get();
                $role->syncPermissions($permissionModels);
                
                $this->command->info('✓ Assigned '.count($permissions)." permissions to role: {$roleName}");
            }
        }
    }

    private function assignSystemRoles(): void
    {
        // System roles don't need team_id - they're global
        $systemRoles = ['super_admin', 'systemadmin', 'system_manager', 'system_auditor'];

        foreach ($systemRoles as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                // System roles remain without team_id (global)
                $this->command->info("✓ System role '{$roleName}' configured");
            }
        }

        // Create system users if they don't exist (for development)
        $this->createSystemUsers();
    }

    private function assignCompanyRoles(): void
    {
        // Get all companies to assign roles
        $companies = \App\Models\Company::all();
        
        if ($companies->isEmpty()) {
            $this->command->info('⚠ No companies found. Company roles will be assigned when companies are created.');
            return;
        }

        $companyRoles = ['company_owner', 'company_admin', 'accounting_admin', 'accounting_operator', 'accounting_viewer', 'portal_customer', 'portal_vendor'];
        $rolePermissions = $this->getCompanyRolePermissions();

        foreach ($companies as $company) {
            foreach ($companyRoles as $roleName) {
                // Create company-specific role with clear naming
                $companyScopedRoleName = "{$roleName}_{$company->id}";
                
                $companyRole = Role::updateOrCreate([
                    'name' => $companyScopedRoleName,
                    'guard_name' => 'web',
                ]);
                
                // Assign permissions to company-scoped role
                if (isset($rolePermissions[$roleName])) {
                    $permissions = Permission::whereIn('name', $rolePermissions[$roleName])->get();
                    $companyRole->syncPermissions($permissions);
                }
            }
            $this->command->info("✓ Created company-scoped roles for: {$company->name}");
        }
    }

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

    private function assignSystemRolePermissions(): void
    {
        $systemRolePermissions = [
            'super_admin' => Permissions::getAll(), // Super admin gets everything
            
            'systemadmin' => [
                // System administration (but not super admin powers)
                Permissions::SYSTEM_AUDIT,
                Permissions::RLS_CONTEXT,
                
                // Company management
                Permissions::COMPANIES_VIEW,
                Permissions::COMPANIES_CREATE,
                Permissions::COMPANIES_UPDATE,
                Permissions::COMPANIES_MANAGE_USERS,
                
                // User management
                Permissions::USERS_VIEW,
                Permissions::USERS_CREATE,
                Permissions::USERS_UPDATE,
                Permissions::USERS_MANAGE_ROLES,
                
                // System-level access to all modules (for support)
                ...array_merge(
                    Permissions::getAllByModule()['acct.customers'],
                    Permissions::getAllByModule()['acct.invoices'],
                    Permissions::getAllByModule()['acct.payments'],
                    Permissions::getAllByModule()['acct.allocations'],
                    Permissions::getAllByModule()['ledger.entries'],
                    Permissions::getAllByModule()['ledger.accounts'],
                    Permissions::getAllByModule()['reporting.dashboard'],
                    Permissions::getAllByModule()['reporting.reports']
                ),
                
                // Audit access
                Permissions::AUDIT_ENTRIES_VIEW,
                Permissions::AUDIT_ENTRIES_EXPORT,
                Permissions::COMPLIANCE_REPORTS_VIEW,
                Permissions::COMPLIANCE_REPORTS_GENERATE,
            ],
            
            'system_manager' => [
                // Limited system operations
                Permissions::SYSTEM_AUDIT,
                Permissions::RLS_CONTEXT,
                Permissions::COMPANIES_VIEW,
                Permissions::USERS_VIEW,
                Permissions::USERS_MANAGE_ROLES,
                
                // Read access to modules
                Permissions::ACCT_CUSTOMERS_VIEW,
                Permissions::ACCT_INVOICES_VIEW,
                Permissions::ACCT_PAYMENTS_VIEW,
                Permissions::REPORTING_DASHBOARD_VIEW,
                Permissions::REPORTING_REPORTS_VIEW,
                
                // Audit access
                Permissions::AUDIT_ENTRIES_VIEW,
                Permissions::COMPLIANCE_REPORTS_VIEW,
            ],
            
            'system_auditor' => [
                // Audit-only access
                Permissions::SYSTEM_AUDIT,
                Permissions::RLS_CONTEXT,
                Permissions::COMPANIES_VIEW,
                Permissions::USERS_VIEW,
                
                // Read-only access for auditing
                Permissions::ACCT_CUSTOMERS_VIEW,
                Permissions::ACCT_INVOICES_VIEW,
                Permissions::ACCT_PAYMENTS_VIEW,
                Permissions::ACCT_ALLOCATIONS_VIEW,
                Permissions::LEDGER_ENTRIES_VIEW,
                Permissions::LEDGER_ACCOUNTS_VIEW,
                
                // Full audit access
                Permissions::AUDIT_ENTRIES_VIEW,
                Permissions::AUDIT_ENTRIES_EXPORT,
                Permissions::COMPLIANCE_REPORTS_VIEW,
                Permissions::COMPLIANCE_REPORTS_GENERATE,
                
                // Reporting access
                Permissions::REPORTING_DASHBOARD_VIEW,
                Permissions::REPORTING_REPORTS_VIEW,
                Permissions::REPORTING_REPORTS_GENERATE,
                Permissions::REPORTING_REPORTS_EXPORT,
            ],
        ];

        foreach ($systemRolePermissions as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $permissionModels = Permission::whereIn('name', $permissions)->get();
                $role->syncPermissions($permissionModels);
                
                $this->command->info('✓ Assigned '.count($permissions)." permissions to system role: {$roleName}");
            }
        }
    }

    private function createSystemUsers(): void
    {
        // Create system users for development/testing (only if they don't exist)
        $systemUsers = [
            [
                'name' => 'Super Admin',
                'email' => 'super@haasib.local',
                'role' => 'super_admin',
            ],
            [
                'name' => 'System Admin',
                'email' => 'admin@haasib.local',
                'role' => 'systemadmin',
            ],
        ];

        foreach ($systemUsers as $userData) {
            $user = \App\Models\User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => bcrypt('password'), // Change in production
                    'email_verified_at' => now(),
                ]
            );

            // Assign system role (global role, no team scoping)
            $role = Role::where('name', $userData['role'])->first();
            
            if ($role && !$user->hasRole($userData['role'])) {
                $user->assignRole($role);
                $this->command->info("✓ Assigned role '{$userData['role']}' to user: {$userData['email']}");
            }
        }
    }
}
