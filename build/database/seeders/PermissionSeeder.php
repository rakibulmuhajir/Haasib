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
            'super_admin' => 'Super Administrator with full system access',
            'company_admin' => 'Company administrator with full company access',
            'accounting_manager' => 'Accounting manager with financial oversight',
            'accounting_clerk' => 'Accounting clerk with data entry access',
            'viewer' => 'Read-only access to company data',
        ];

        foreach ($roles as $name => $description) {
            Role::updateOrCreate(['name' => $name], [
                'guard_name' => 'web',
                'description' => $description,
            ]);
        }

        $this->command->info('✓ Created '.count($roles).' roles');
    }

    private function assignPermissionsToRoles(): void
    {
        $rolePermissions = Permissions::getRolePresets();

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
