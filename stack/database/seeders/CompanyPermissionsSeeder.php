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
        ];

        // Accountant permissions (financial management)
        $accountantPermissions = [
            // Company management (view only)
            'companies.view', 'companies.switch', 'companies.view_members',

            // Fiscal years
            'fiscal_years.view', 'fiscal_years.update',

            // Accounting periods
            'accounting_periods.view', 'accounting_periods.update',

            // Chart of accounts
            'charts_of_accounts.view', 'charts_of_accounts.update',

            // Accounts
            'accounts.view', 'accounts.update', 'accounts.activate',

            // Account types
            'account_types.view',

            // Account groups
            'account_groups.view', 'account_groups.update', 'account_groups.assign_accounts',
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