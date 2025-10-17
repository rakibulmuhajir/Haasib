<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CompanyPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding company management permissions...');

        DB::beginTransaction();

        try {
            $this->createCompanyPermissions();
            $this->createCompanyInvitationPermissions();
            $this->createFiscalYearPermissions();
            $this->createChartOfAccountsPermissions();
            $this->createPeriodClosePermissions();
            $this->createBankReconciliationPermissions();
            $this->assignPermissionsToRoles();

            DB::commit();
            $this->command->info('✓ Company permissions seeded successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Company permission seeding failed: '.$e->getMessage());
            throw $e;
        }
    }

    private function createCompanyPermissions(): void
    {
        $permissions = [
            // Company management
            'companies.view' => 'View company information',
            'companies.create' => 'Create new companies',
            'companies.update' => 'Update company information',
            'companies.delete' => 'Delete companies',
            'companies.manage' => 'Full company management access',
            'companies.switch' => 'Switch between company contexts',
            'companies.invite_users' => 'Invite users to companies',
            'companies.assign_users' => 'Assign users to companies',
            'companies.remove_users' => 'Remove users from companies',
            'companies.view_members' => 'View company member list',
            'companies.manage_roles' => 'Manage user roles in companies',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ], [
                'description' => $description,
            ]);
        }

        $this->command->info('✓ Company permissions created');
    }

    private function createCompanyInvitationPermissions(): void
    {
        $permissions = [
            // Company invitations
            'company_invitations.view' => 'View company invitations',
            'company_invitations.create' => 'Send company invitations',
            'company_invitations.accept' => 'Accept company invitations',
            'company_invitations.reject' => 'Reject company invitations',
            'company_invitations.resend' => 'Resend pending invitations',
            'company_invitations.revoke' => 'Revoke company invitations',
            'company_invitations.view_all' => 'View all company invitations (admin only)',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ], [
                'description' => $description,
            ]);
        }

        $this->command->info('✓ Company invitation permissions created');
    }

    private function createFiscalYearPermissions(): void
    {
        $permissions = [
            // Fiscal years
            'fiscal_years.view' => 'View fiscal years',
            'fiscal_years.create' => 'Create fiscal years',
            'fiscal_years.update' => 'Update fiscal years',
            'fiscal_years.delete' => 'Delete fiscal years',
            'fiscal_years.close' => 'Close fiscal years',
            'fiscal_years.reopen' => 'Reopen fiscal years',

            // Accounting periods
            'accounting_periods.view' => 'View accounting periods',
            'accounting_periods.create' => 'Create accounting periods',
            'accounting_periods.update' => 'Update accounting periods',
            'accounting_periods.delete' => 'Delete accounting periods',
            'accounting_periods.close' => 'Close accounting periods',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ], [
                'description' => $description,
            ]);
        }

        $this->command->info('✓ Fiscal year permissions created');
    }

    private function createChartOfAccountsPermissions(): void
    {
        $permissions = [
            // Chart of accounts
            'charts_of_accounts.view' => 'View charts of accounts',
            'charts_of_accounts.create' => 'Create charts of accounts',
            'charts_of_accounts.update' => 'Update charts of accounts',
            'charts_of_accounts.delete' => 'Delete charts of accounts',
            'charts_of_accounts.import' => 'Import chart of accounts templates',

            // Accounts
            'accounts.view' => 'View accounts',
            'accounts.create' => 'Create accounts',
            'accounts.update' => 'Update accounts',
            'accounts.delete' => 'Delete accounts',
            'accounts.activate' => 'Activate/deactivate accounts',

            // Account types (read-only for most)
            'account_types.view' => 'View account types',

            // Account groups
            'account_groups.view' => 'View account groups',
            'account_groups.create' => 'Create account groups',
            'account_groups.update' => 'Update account groups',
            'account_groups.delete' => 'Delete account groups',
            'account_groups.assign_accounts' => 'Assign accounts to groups',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ], [
                'description' => $description,
            ]);
        }

        $this->command->info('✓ Chart of accounts permissions created');
    }

    private function createPeriodClosePermissions(): void
    {
        $permissions = [
            // Period close workflow
            'period-close.view' => 'View period close dashboard and checklists',
            'period-close.start' => 'Start period close workflow',
            'period-close.validate' => 'Run period close validations',
            'period-close.lock' => 'Lock period for final approval',
            'period-close.complete' => 'Complete and finalize period close',
            'period-close.reopen' => 'Reopen closed periods',
            'period-close.adjust' => 'Create period adjusting entries',

            // Period close templates
            'period-close-templates.view' => 'View period close templates',
            'period-close-templates.create' => 'Create period close templates',
            'period-close-templates.update' => 'Update period close templates',
            'period-close-templates.delete' => 'Delete period close templates',
            'period-close-templates.manage' => 'Full template management access',
            'period-close-templates.sync' => 'Sync template tasks to period closes',

            // Period close tasks
            'period-close-tasks.update' => 'Update period close task status',
            'period-close-tasks.complete' => 'Mark period close tasks as completed',
            'period-close-tasks.waive' => 'Waive required period close tasks',
            'period-close-tasks.block' => 'Block period close tasks with notes',

            // Period close reporting
            'period-close.reports.view' => 'View period close reports',
            'period-close.reports.generate' => 'Generate period close reports',
            'period-close.reports.export' => 'Export period close reports',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ], [
                'description' => $description,
            ]);
        }

        $this->command->info('✓ Period close permissions created');
    }

    private function createBankReconciliationPermissions(): void
    {
        $permissions = [
            // Bank statements
            'bank_statements.view' => 'View bank statements and statement lines',
            'bank_statements.import' => 'Import bank statements (CSV, OFX, QFX)',
            'bank_statements.delete' => 'Delete bank statements',
            'bank_statements.process' => 'Process and normalize bank statement lines',

            // Bank reconciliations
            'bank_reconciliations.view' => 'View bank reconciliations and workspace',
            'bank_reconciliations.create' => 'Start new bank reconciliations',
            'bank_reconciliations.update' => 'Update bank reconciliation details and notes',
            'bank_reconciliations.delete' => 'Delete bank reconciliations',
            'bank_reconciliations.complete' => 'Complete bank reconciliations',
            'bank_reconciliations.lock' => 'Lock completed reconciliations',
            'bank_reconciliations.reopen' => 'Reopen locked reconciliations',

            // Bank reconciliation matching
            'bank_reconciliation_matches.view' => 'View bank reconciliation matches',
            'bank_reconciliation_matches.create' => 'Create manual matches between statements and transactions',
            'bank_reconciliation_matches.delete' => 'Remove bank reconciliation matches',
            'bank_reconciliation_matches.auto_match' => 'Run auto-matching algorithms',

            // Bank reconciliation adjustments
            'bank_reconciliation_adjustments.view' => 'View bank reconciliation adjustments',
            'bank_reconciliation_adjustments.create' => 'Create bank adjustments (fees, interest, write-offs)',
            'bank_reconciliation_adjustments.update' => 'Update bank reconciliation adjustments',
            'bank_reconciliation_adjustments.delete' => 'Delete bank reconciliation adjustments',

            // Bank reconciliation reporting
            'bank_reconciliation_reports.view' => 'View bank reconciliation reports',
            'bank_reconciliation_reports.generate' => 'Generate bank reconciliation reports',
            'bank_reconciliation_reports.export' => 'Export bank reconciliation reports and statements',

            // Bank reconciliation audit
            'bank_reconciliation_audit.view' => 'View bank reconciliation audit trail',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ], [
                'description' => $description,
            ]);
        }

        $this->command->info('✓ Bank reconciliation permissions created');
    }

    private function assignPermissionsToRoles(): void
    {
        // Get or create roles
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $owner = Role::firstOrCreate(['name' => 'owner']);
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $accountant = Role::firstOrCreate(['name' => 'accountant']);
        $viewer = Role::firstOrCreate(['name' => 'viewer']);

        // Super Admin permissions (all permissions)
        $superAdminPermissions = [
            // Company management
            'companies.view', 'companies.create', 'companies.update', 'companies.delete',
            'companies.manage', 'companies.switch', 'companies.invite_users', 'companies.assign_users',
            'companies.remove_users', 'companies.view_members', 'companies.manage_roles',

            // Company invitations
            'company_invitations.view', 'company_invitations.create', 'company_invitations.accept',
            'company_invitations.reject', 'company_invitations.resend', 'company_invitations.revoke',
            'company_invitations.view_all',

            // Fiscal years
            'fiscal_years.view', 'fiscal_years.create', 'fiscal_years.update', 'fiscal_years.delete',
            'fiscal_years.close', 'fiscal_years.reopen',

            // Accounting periods
            'accounting_periods.view', 'accounting_periods.create', 'accounting_periods.update',
            'accounting_periods.delete', 'accounting_periods.close',

            // Period close
            'period-close.view', 'period-close.start', 'period-close.validate', 'period-close.lock',
            'period-close.complete', 'period-close.reopen', 'period-close.adjust',

            // Period close templates
            'period-close-templates.view', 'period-close-templates.create', 'period-close-templates.update',
            'period-close-templates.delete', 'period-close-templates.manage', 'period-close-templates.sync',

            // Period close tasks
            'period-close-tasks.update', 'period-close-tasks.complete', 'period-close-tasks.waive',
            'period-close-tasks.block',

            // Period close reporting
            'period-close.reports.view', 'period-close.reports.generate', 'period-close.reports.export',

            // Chart of accounts
            'charts_of_accounts.view', 'charts_of_accounts.create', 'charts_of_accounts.update',
            'charts_of_accounts.delete', 'charts_of_accounts.import',

            // Accounts
            'accounts.view', 'accounts.create', 'accounts.update', 'accounts.delete', 'accounts.activate',

            // Account types
            'account_types.view',

            // Account groups
            'account_groups.view', 'account_groups.create', 'account_groups.update',
            'account_groups.delete', 'account_groups.assign_accounts',

            // Bank statements
            'bank_statements.view', 'bank_statements.import', 'bank_statements.delete',
            'bank_statements.process',

            // Bank reconciliations
            'bank_reconciliations.view', 'bank_reconciliations.create', 'bank_reconciliations.update',
            'bank_reconciliations.delete', 'bank_reconciliations.complete', 'bank_reconciliations.lock',
            'bank_reconciliations.reopen',

            // Bank reconciliation matching
            'bank_reconciliation_matches.view', 'bank_reconciliation_matches.create',
            'bank_reconciliation_matches.delete', 'bank_reconciliation_matches.auto_match',

            // Bank reconciliation adjustments
            'bank_reconciliation_adjustments.view', 'bank_reconciliation_adjustments.create',
            'bank_reconciliation_adjustments.update', 'bank_reconciliation_adjustments.delete',

            // Bank reconciliation reporting
            'bank_reconciliation_reports.view', 'bank_reconciliation_reports.generate',
            'bank_reconciliation_reports.export',

            // Bank reconciliation audit
            'bank_reconciliation_audit.view',
        ];

        // Owner permissions (full company management)
        $ownerPermissions = [
            // Company management
            'companies.view', 'companies.create', 'companies.update', 'companies.manage',
            'companies.switch', 'companies.invite_users', 'companies.assign_users', 'companies.remove_users',
            'companies.view_members', 'companies.manage_roles',

            // Company invitations
            'company_invitations.view', 'company_invitations.create', 'company_invitations.resend',
            'company_invitations.revoke',

            // Fiscal years
            'fiscal_years.view', 'fiscal_years.create', 'fiscal_years.update', 'fiscal_years.delete',
            'fiscal_years.close', 'fiscal_years.reopen',

            // Accounting periods
            'accounting_periods.view', 'accounting_periods.create', 'accounting_periods.update',
            'accounting_periods.delete', 'accounting_periods.close',

            // Period close
            'period-close.view', 'period-close.start', 'period-close.validate', 'period-close.lock',
            'period-close.complete', 'period-close.reopen', 'period-close.adjust',

            // Period close templates
            'period-close-templates.view', 'period-close-templates.create', 'period-close-templates.update',
            'period-close-templates.delete', 'period-close-templates.manage', 'period-close-templates.sync',

            // Period close tasks
            'period-close-tasks.update', 'period-close-tasks.complete', 'period-close-tasks.waive',
            'period-close-tasks.block',

            // Period close reporting
            'period-close.reports.view', 'period-close.reports.generate', 'period-close.reports.export',

            // Chart of accounts
            'charts_of_accounts.view', 'charts_of_accounts.create', 'charts_of_accounts.update',
            'charts_of_accounts.delete', 'charts_of_accounts.import',

            // Accounts
            'accounts.view', 'accounts.create', 'accounts.update', 'accounts.delete', 'accounts.activate',

            // Account types
            'account_types.view',

            // Account groups
            'account_groups.view', 'account_groups.create', 'account_groups.update',
            'account_groups.delete', 'account_groups.assign_accounts',

            // Bank statements
            'bank_statements.view', 'bank_statements.import', 'bank_statements.delete',
            'bank_statements.process',

            // Bank reconciliations
            'bank_reconciliations.view', 'bank_reconciliations.create', 'bank_reconciliations.update',
            'bank_reconciliations.complete', 'bank_reconciliations.lock', 'bank_reconciliations.reopen',

            // Bank reconciliation matching
            'bank_reconciliation_matches.view', 'bank_reconciliation_matches.create',
            'bank_reconciliation_matches.delete', 'bank_reconciliation_matches.auto_match',

            // Bank reconciliation adjustments
            'bank_reconciliation_adjustments.view', 'bank_reconciliation_adjustments.create',
            'bank_reconciliation_adjustments.update', 'bank_reconciliation_adjustments.delete',

            // Bank reconciliation reporting
            'bank_reconciliation_reports.view', 'bank_reconciliation_reports.generate',
            'bank_reconciliation_reports.export',

            // Bank reconciliation audit
            'bank_reconciliation_audit.view',
        ];

        // Admin permissions (company management without deletion)
        $adminPermissions = [
            // Company management
            'companies.view', 'companies.update', 'companies.manage', 'companies.switch',
            'companies.invite_users', 'companies.assign_users', 'companies.remove_users',
            'companies.view_members',

            // Company invitations
            'company_invitations.view', 'company_invitations.create', 'company_invitations.resend',
            'company_invitations.revoke',

            // Fiscal years
            'fiscal_years.view', 'fiscal_years.create', 'fiscal_years.update', 'fiscal_years.close',

            // Accounting periods
            'accounting_periods.view', 'accounting_periods.create', 'accounting_periods.update',
            'accounting_periods.close',

            // Period close (admin cannot reopen periods)
            'period-close.view', 'period-close.start', 'period-close.validate', 'period-close.lock',
            'period-close.complete', 'period-close.adjust',

            // Period close templates
            'period-close-templates.view', 'period-close-templates.create', 'period-close-templates.update',
            'period-close-templates.sync',

            // Period close tasks
            'period-close-tasks.update', 'period-close-tasks.complete', 'period-close-tasks.block',

            // Period close reporting
            'period-close.reports.view', 'period-close.reports.generate',

            // Chart of accounts
            'charts_of_accounts.view', 'charts_of_accounts.create', 'charts_of_accounts.update',
            'charts_of_accounts.import',

            // Accounts
            'accounts.view', 'accounts.create', 'accounts.update', 'accounts.activate',

            // Account types
            'account_types.view',

            // Account groups
            'account_groups.view', 'account_groups.create', 'account_groups.update',
            'account_groups.assign_accounts',

            // Bank statements
            'bank_statements.view', 'bank_statements.import', 'bank_statements.process',

            // Bank reconciliations
            'bank_reconciliations.view', 'bank_reconciliations.create', 'bank_reconciliations.update',
            'bank_reconciliations.complete', 'bank_reconciliations.lock',

            // Bank reconciliation matching
            'bank_reconciliation_matches.view', 'bank_reconciliation_matches.create',
            'bank_reconciliation_matches.delete', 'bank_reconciliation_matches.auto_match',

            // Bank reconciliation adjustments
            'bank_reconciliation_adjustments.view', 'bank_reconciliation_adjustments.create',
            'bank_reconciliation_adjustments.update', 'bank_reconciliation_adjustments.delete',

            // Bank reconciliation reporting
            'bank_reconciliation_reports.view', 'bank_reconciliation_reports.generate',

            // Bank reconciliation audit
            'bank_reconciliation_audit.view',
        ];

        // Accountant permissions (financial management)
        $accountantPermissions = [
            // Company management (view only)
            'companies.view', 'companies.switch', 'companies.view_members',

            // Fiscal years
            'fiscal_years.view', 'fiscal_years.update',

            // Accounting periods
            'accounting_periods.view', 'accounting_periods.update',

            // Period close (accountant can handle most tasks except reopening)
            'period-close.view', 'period-close.start', 'period-close.validate',
            'period-close.adjust',

            // Period close tasks
            'period-close-tasks.update', 'period-close-tasks.complete', 'period-close-tasks.block',

            // Period close reporting
            'period-close.reports.view', 'period-close.reports.generate',

            // Chart of accounts
            'charts_of_accounts.view', 'charts_of_accounts.update',

            // Accounts
            'accounts.view', 'accounts.update', 'accounts.activate',

            // Account types
            'account_types.view',

            // Account groups
            'account_groups.view', 'account_groups.update', 'account_groups.assign_accounts',

            // Bank statements
            'bank_statements.view', 'bank_statements.import', 'bank_statements.process',

            // Bank reconciliations
            'bank_reconciliations.view', 'bank_reconciliations.create', 'bank_reconciliations.update',
            'bank_reconciliations.complete',

            // Bank reconciliation matching
            'bank_reconciliation_matches.view', 'bank_reconciliation_matches.create',
            'bank_reconciliation_matches.delete', 'bank_reconciliation_matches.auto_match',

            // Bank reconciliation adjustments
            'bank_reconciliation_adjustments.view', 'bank_reconciliation_adjustments.create',
            'bank_reconciliation_adjustments.update',

            // Bank reconciliation reporting
            'bank_reconciliation_reports.view', 'bank_reconciliation_reports.generate',

            // Bank reconciliation audit
            'bank_reconciliation_audit.view',
        ];

        // Viewer permissions (read-only)
        $viewerPermissions = [
            // Company management (view only)
            'companies.view', 'companies.switch', 'companies.view_members',

            // Fiscal years
            'fiscal_years.view',

            // Accounting periods
            'accounting_periods.view',

            // Chart of accounts
            'charts_of_accounts.view',

            // Accounts
            'accounts.view',

            // Account types
            'account_types.view',

            // Account groups
            'account_groups.view',

            // Bank statements
            'bank_statements.view',

            // Bank reconciliations
            'bank_reconciliations.view',

            // Bank reconciliation matching
            'bank_reconciliation_matches.view',

            // Bank reconciliation adjustments
            'bank_reconciliation_adjustments.view',

            // Bank reconciliation reporting
            'bank_reconciliation_reports.view',

            // Bank reconciliation audit
            'bank_reconciliation_audit.view',
        ];

        // Assign permissions to roles
        $superAdmin->givePermissionTo($superAdminPermissions);
        $owner->givePermissionTo($ownerPermissions);
        $admin->givePermissionTo($adminPermissions);
        $accountant->givePermissionTo($accountantPermissions);
        $viewer->givePermissionTo($viewerPermissions);

        $this->command->info('✓ Permissions assigned to roles');
    }
}
