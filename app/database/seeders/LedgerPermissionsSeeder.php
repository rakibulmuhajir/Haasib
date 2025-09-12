<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class LedgerPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'ledger.view',
            'ledger.create',
            'ledger.edit',
            'ledger.delete',
            'ledger.post',
            'ledger.void',
            'ledger.accounts.view',
            'ledger.accounts.create',
            'ledger.accounts.edit',
            'ledger.accounts.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Assign permissions to roles
        $ownerRole = Role::firstWhere('name', 'owner');
        $adminRole = Role::firstWhere('name', 'admin');
        $accountantRole = Role::firstWhere('name', 'accountant');
        $viewerRole = Role::firstWhere('name', 'viewer');

        // Owner gets all permissions
        if ($ownerRole) {
            $ownerRole->givePermissionTo($permissions);
        }

        // Admin gets all permissions except delete
        if ($adminRole) {
            $adminRole->givePermissionTo(array_diff($permissions, [
                'ledger.delete',
                'ledger.accounts.delete',
            ]));
        }

        // Accountant gets view, create, edit, post
        if ($accountantRole) {
            $accountantRole->givePermissionTo([
                'ledger.view',
                'ledger.create',
                'ledger.edit',
                'ledger.post',
                'ledger.accounts.view',
                'ledger.accounts.create',
                'ledger.accounts.edit',
            ]);
        }

        // Viewer only gets view permissions
        if ($viewerRole) {
            $viewerRole->givePermissionTo([
                'ledger.view',
                'ledger.accounts.view',
            ]);
        }
    }
}
