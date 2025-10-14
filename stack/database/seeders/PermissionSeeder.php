<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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
        $permissions = [
            // System permissions
            'system.admin' => 'Full system administration',
            'system.setup' => 'Initialize system setup',
            'system.reset' => 'Reset system to clean state',

            // User management
            'users.view' => 'View users',
            'users.create' => 'Create users',
            'users.update' => 'Update users',
            'users.delete' => 'Delete users',
            'users.switch' => 'Switch to different user context',

            // Company management
            'companies.view' => 'View companies',
            'companies.create' => 'Create companies',
            'companies.update' => 'Update companies',
            'companies.delete' => 'Delete companies',
            'companies.switch' => 'Switch between companies',
            'companies.manage_users' => 'Manage company user assignments',

            // Module management
            'modules.view' => 'View available modules',
            'modules.enable' => 'Enable modules for company',
            'modules.disable' => 'Disable modules for company',
            'modules.configure' => 'Configure module settings',

            // Accounting module permissions
            'accounting.customers.view' => 'View customers',
            'accounting.customers.create' => 'Create customers',
            'accounting.customers.update' => 'Update customers',
            'accounting.customers.delete' => 'Delete customers',

            'accounting.invoices.view' => 'View invoices',
            'accounting.invoices.create' => 'Create invoices',
            'accounting.invoices.update' => 'Update invoices',
            'accounting.invoices.delete' => 'Delete invoices',
            'accounting.invoices.approve' => 'Approve invoices',

            'accounting.payments.view' => 'View payments',
            'accounting.payments.create' => 'Record payments',
            'accounting.payments.update' => 'Update payments',
            'accounting.payments.delete' => 'Delete payments',
            'accounting.payments.refund' => 'Process refunds',

            'accounting.chart_of_accounts.view' => 'View chart of accounts',
            'accounting.chart_of_accounts.create' => 'Create chart accounts',
            'accounting.chart_of_accounts.update' => 'Update chart accounts',
            'accounting.chart_of_accounts.delete' => 'Delete chart accounts',

            'accounting.journal_entries.view' => 'View journal entries',
            'accounting.journal_entries.create' => 'Create journal entries',
            'accounting.journal_entries.update' => 'Update journal entries',
            'accounting.journal_entries.delete' => 'Delete journal entries',
            'accounting.journal_entries.post' => 'Post journal entries',

            // Reporting permissions
            'reports.view' => 'View reports',
            'reports.financial' => 'View financial reports',
            'reports.sales' => 'View sales reports',
            'reports.audit' => 'View audit reports',

            // Dashboard permissions
            'dashboard.view' => 'View dashboard',
            'dashboard.analytics' => 'View analytics dashboard',

            // API permissions
            'api.access' => 'Access API endpoints',
            'api.admin' => 'Administrative API access',

            // CLI permissions
            'cli.access' => 'Access CLI commands',
            'cli.admin' => 'Administrative CLI access',

            // Audit permissions
            'audit.view' => 'View audit trail',
            'audit.export' => 'Export audit data',
        ];

        foreach ($permissions as $name => $description) {
            Permission::updateOrCreate(
                ['name' => $name],
                [
                    'guard_name' => 'web',
                    'description' => $description,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info('✓ Created '.count($permissions).' permissions');
    }

    private function createRoles(): void
    {
        $roles = [
            'system_owner' => 'Full system access across all companies',
            'company_owner' => 'Full access to owned company',
            'accountant' => 'Financial management and reporting',
            'member' => 'Basic company access',
        ];

        foreach ($roles as $name => $description) {
            Role::updateOrCreate(
                ['name' => $name],
                [
                    'guard_name' => 'web',
                    'description' => $description,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info('✓ Created '.count($roles).' roles');
    }

    private function assignPermissionsToRoles(): void
    {
        $rolePermissions = [
            'system_owner' => [
                // System permissions
                'system.admin', 'system.setup', 'system.reset',

                // User management
                'users.view', 'users.create', 'users.update', 'users.delete', 'users.switch',

                // Company management
                'companies.view', 'companies.create', 'companies.update', 'companies.delete', 'companies.switch', 'companies.manage_users',

                // Module management
                'modules.view', 'modules.enable', 'modules.disable', 'modules.configure',

                // Full accounting access
                'accounting.customers.view', 'accounting.customers.create', 'accounting.customers.update', 'accounting.customers.delete',
                'accounting.invoices.view', 'accounting.invoices.create', 'accounting.invoices.update', 'accounting.invoices.delete', 'accounting.invoices.approve',
                'accounting.payments.view', 'accounting.payments.create', 'accounting.payments.update', 'accounting.payments.delete', 'accounting.payments.refund',
                'accounting.chart_of_accounts.view', 'accounting.chart_of_accounts.create', 'accounting.chart_of_accounts.update', 'accounting.chart_of_accounts.delete',
                'accounting.journal_entries.view', 'accounting.journal_entries.create', 'accounting.journal_entries.update', 'accounting.journal_entries.delete', 'accounting.journal_entries.post',

                // Reporting
                'reports.view', 'reports.financial', 'reports.sales', 'reports.audit',

                // Dashboard
                'dashboard.view', 'dashboard.analytics',

                // API and CLI
                'api.access', 'api.admin', 'cli.access', 'cli.admin',

                // Audit
                'audit.view', 'audit.export',
            ],

            'company_owner' => [
                // Company management (except delete)
                'companies.view', 'companies.update', 'companies.switch', 'companies.manage_users',

                // User management for company users
                'users.view', 'users.create', 'users.update', 'users.switch',

                // Module management
                'modules.view', 'modules.enable', 'modules.disable', 'modules.configure',

                // Full accounting access for company
                'accounting.customers.view', 'accounting.customers.create', 'accounting.customers.update', 'accounting.customers.delete',
                'accounting.invoices.view', 'accounting.invoices.create', 'accounting.invoices.update', 'accounting.invoices.delete', 'accounting.invoices.approve',
                'accounting.payments.view', 'accounting.payments.create', 'accounting.payments.update', 'accounting.payments.delete', 'accounting.payments.refund',
                'accounting.chart_of_accounts.view', 'accounting.chart_of_accounts.create', 'accounting.chart_of_accounts.update',
                'accounting.journal_entries.view', 'accounting.journal_entries.create', 'accounting.journal_entries.update', 'accounting.journal_entries.post',

                // Company reporting
                'reports.view', 'reports.financial', 'reports.sales',

                // Dashboard
                'dashboard.view', 'dashboard.analytics',

                // API access
                'api.access', 'cli.access',

                // Limited audit
                'audit.view',
            ],

            'accountant' => [
                // Company view access
                'companies.view', 'companies.switch',

                // User view
                'users.view',

                // Module view
                'modules.view',

                // Accounting permissions
                'accounting.customers.view', 'accounting.customers.create', 'accounting.customers.update',
                'accounting.invoices.view', 'accounting.invoices.create', 'accounting.invoices.update', 'accounting.invoices.approve',
                'accounting.payments.view', 'accounting.payments.create', 'accounting.payments.update',
                'accounting.chart_of_accounts.view', 'accounting.chart_of_accounts.update',
                'accounting.journal_entries.view', 'accounting.journal_entries.create', 'accounting.journal_entries.update', 'accounting.journal_entries.post',

                // Reporting
                'reports.view', 'reports.financial', 'reports.sales',

                // Dashboard
                'dashboard.view', 'dashboard.analytics',

                // API access
                'api.access', 'cli.access',
            ],

            'member' => [
                // Basic view permissions
                'companies.view', 'companies.switch',
                'users.view',
                'modules.view',

                // Basic accounting view
                'accounting.customers.view',
                'accounting.invoices.view',
                'accounting.payments.view',
                'accounting.chart_of_accounts.view',
                'accounting.journal_entries.view',

                // Basic reports
                'reports.view',

                // Dashboard
                'dashboard.view',

                // Basic API access
                'api.access',
            ],
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->syncPermissions($permissions);
                $this->command->info('✓ Assigned '.count($permissions)." permissions to role: {$roleName}");
            }
        }
    }
}
