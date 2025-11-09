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
            'accounting.customers.manage_contacts' => 'Manage customer contacts and addresses',
            'accounting.customers.manage_groups' => 'Manage customer groups and assignments',
            'accounting.customers.manage_comms' => 'Manage customer communications and history',
            'accounting.customers.manage_credit' => 'Manage customer credit limits and adjustments',

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

            // Journal entry permissions
            'accounting.journal_entries.view' => 'View journal entries',
            'accounting.journal_entries.create' => 'Create journal entries',
            'accounting.journal_entries.update' => 'Update journal entries',
            'accounting.journal_entries.delete' => 'Delete journal entries',
            'accounting.journal_entries.post' => 'Post journal entries',
            'accounting.journal_entries.approve' => 'Approve journal entries',
            'accounting.journal_entries.submit' => 'Submit journal entries for approval',
            'accounting.journal_entries.reverse' => 'Create reversing journal entries',
            'accounting.journal_entries.void' => 'Void journal entries',
            'accounting.journal_entries.import' => 'Import journal entries',
            'accounting.journal_entries.export' => 'Export journal entries',

            // Journal batch permissions
            'accounting.journal_batches.view' => 'View journal batches',
            'accounting.journal_batches.create' => 'Create journal batches',
            'accounting.journal_batches.update' => 'Update journal batches',
            'accounting.journal_batches.delete' => 'Delete journal batches',
            'accounting.journal_batches.approve' => 'Approve journal batches',
            'accounting.journal_batches.post' => 'Post journal batches',
            'accounting.journal_batches.schedule' => 'Schedule journal batches',

            // Recurring journal template permissions
            'accounting.journal_templates.view' => 'View recurring journal templates',
            'accounting.journal_templates.create' => 'Create recurring journal templates',
            'accounting.journal_templates.update' => 'Update recurring journal templates',
            'accounting.journal_templates.delete' => 'Delete recurring journal templates',
            'accounting.journal_templates.activate' => 'Activate recurring journal templates',
            'accounting.journal_templates.deactivate' => 'Deactivate recurring journal templates',
            'accounting.journal_templates.preview' => 'Preview recurring journal template generation',

            // Trial balance and reporting permissions
            'accounting.trial_balance.view' => 'View trial balance',
            'accounting.trial_balance.generate' => 'Generate trial balance',
            'accounting.trial_balance.export' => 'Export trial balance',
            'accounting.ledger.view' => 'View general ledger',
            'accounting.ledger.search' => 'Search ledger entries',

            // Journal audit permissions
            'accounting.journal_audit.view' => 'View journal entry audit trail',
            'accounting.journal_audit.export' => 'Export journal audit logs',

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
                    'id' => \Illuminate\Support\Str::uuid(),
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
                    'id' => \Illuminate\Support\Str::uuid(),
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
                'accounting.customers.manage_contacts', 'accounting.customers.manage_groups', 'accounting.customers.manage_comms', 'accounting.customers.manage_credit',
                'accounting.invoices.view', 'accounting.invoices.create', 'accounting.invoices.update', 'accounting.invoices.delete', 'accounting.invoices.approve',
                'accounting.payments.view', 'accounting.payments.create', 'accounting.payments.update', 'accounting.payments.delete', 'accounting.payments.refund',
                'accounting.chart_of_accounts.view', 'accounting.chart_of_accounts.create', 'accounting.chart_of_accounts.update', 'accounting.chart_of_accounts.delete',
                // Full journal entry access
                'accounting.journal_entries.view', 'accounting.journal_entries.create', 'accounting.journal_entries.update', 'accounting.journal_entries.delete', 'accounting.journal_entries.post',
                'accounting.journal_entries.approve', 'accounting.journal_entries.submit', 'accounting.journal_entries.reverse', 'accounting.journal_entries.void', 'accounting.journal_entries.import', 'accounting.journal_entries.export',

                // Full journal batch access
                'accounting.journal_batches.view', 'accounting.journal_batches.create', 'accounting.journal_batches.update', 'accounting.journal_batches.delete',
                'accounting.journal_batches.approve', 'accounting.journal_batches.post', 'accounting.journal_batches.schedule',

                // Full recurring template access
                'accounting.journal_templates.view', 'accounting.journal_templates.create', 'accounting.journal_templates.update', 'accounting.journal_templates.delete',
                'accounting.journal_templates.activate', 'accounting.journal_templates.deactivate', 'accounting.journal_templates.preview',

                // Trial balance and ledger access
                'accounting.trial_balance.view', 'accounting.trial_balance.generate', 'accounting.trial_balance.export',
                'accounting.ledger.view', 'accounting.ledger.search',

                // Full audit access
                'accounting.journal_audit.view', 'accounting.journal_audit.export',

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
                'accounting.customers.manage_contacts', 'accounting.customers.manage_groups', 'accounting.customers.manage_comms', 'accounting.customers.manage_credit',
                'accounting.invoices.view', 'accounting.invoices.create', 'accounting.invoices.update', 'accounting.invoices.delete', 'accounting.invoices.approve',
                'accounting.payments.view', 'accounting.payments.create', 'accounting.payments.update', 'accounting.payments.delete', 'accounting.payments.refund',
                'accounting.chart_of_accounts.view', 'accounting.chart_of_accounts.create', 'accounting.chart_of_accounts.update',
                // Full journal entry access for company
                'accounting.journal_entries.view', 'accounting.journal_entries.create', 'accounting.journal_entries.update', 'accounting.journal_entries.post',
                'accounting.journal_entries.approve', 'accounting.journal_entries.submit', 'accounting.journal_entries.reverse', 'accounting.journal_entries.void', 'accounting.journal_entries.export',

                // Journal batch access
                'accounting.journal_batches.view', 'accounting.journal_batches.create', 'accounting.journal_batches.update', 'accounting.journal_batches.delete',
                'accounting.journal_batches.approve', 'accounting.journal_batches.post', 'accounting.journal_batches.schedule',

                // Recurring template access
                'accounting.journal_templates.view', 'accounting.journal_templates.create', 'accounting.journal_templates.update', 'accounting.journal_templates.delete',
                'accounting.journal_templates.activate', 'accounting.journal_templates.deactivate', 'accounting.journal_templates.preview',

                // Trial balance and ledger access
                'accounting.trial_balance.view', 'accounting.trial_balance.generate', 'accounting.trial_balance.export',
                'accounting.ledger.view', 'accounting.ledger.search',

                // Audit access
                'accounting.journal_audit.view',

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
                'accounting.customers.manage_contacts', 'accounting.customers.manage_comms',
                'accounting.invoices.view', 'accounting.invoices.create', 'accounting.invoices.update', 'accounting.invoices.approve',
                'accounting.payments.view', 'accounting.payments.create', 'accounting.payments.update',
                'accounting.chart_of_accounts.view', 'accounting.chart_of_accounts.update',
                // Full journal entry access
                'accounting.journal_entries.view', 'accounting.journal_entries.create', 'accounting.journal_entries.update', 'accounting.journal_entries.post',
                'accounting.journal_entries.approve', 'accounting.journal_entries.submit', 'accounting.journal_entries.reverse', 'accounting.journal_entries.void', 'accounting.journal_entries.export',

                // Journal batch access
                'accounting.journal_batches.view', 'accounting.journal_batches.create', 'accounting.journal_batches.update',
                'accounting.journal_batches.approve', 'accounting.journal_batches.post', 'accounting.journal_batches.schedule',

                // Recurring template access
                'accounting.journal_templates.view', 'accounting.journal_templates.create', 'accounting.journal_templates.update',
                'accounting.journal_templates.activate', 'accounting.journal_templates.deactivate', 'accounting.journal_templates.preview',

                // Trial balance and ledger access
                'accounting.trial_balance.view', 'accounting.trial_balance.generate', 'accounting.trial_balance.export',
                'accounting.ledger.view', 'accounting.ledger.search',

                // Audit access
                'accounting.journal_audit.view',

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

                // Basic journal batch and template view
                'accounting.journal_batches.view',
                'accounting.journal_templates.view',

                // Basic trial balance view
                'accounting.trial_balance.view',
                'accounting.ledger.view',

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
                $permissionIds = Permission::whereIn('name', $permissions)->pluck('id');
                
                // Sync permissions manually using UUIDs
                \DB::table('public.role_has_permissions')->where('role_id', $role->id)->delete();
                
                foreach ($permissionIds as $permissionId) {
                    \DB::table('public.role_has_permissions')->insert([
                        'role_id' => $role->id,
                        'permission_id' => $permissionId,
                    ]);
                }
                
                $this->command->info('✓ Assigned '.count($permissions)." permissions to role: {$roleName}");
            }
        }
    }
}
