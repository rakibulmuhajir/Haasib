<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->uiInvoicePermissions() as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name],
                [
                    'guard_name' => 'web',
                    'description' => $description,
                ]
            );
        }

        foreach ($this->uiRoleAssignments() as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->first();
            if (! $role) {
                continue;
            }

            $permissionIds = Permission::whereIn('name', $permissions)->pluck('id');
            foreach ($permissionIds as $permissionId) {
                DB::table('public.role_has_permissions')->updateOrInsert(
                    [
                        'role_id' => $role->id,
                        'permission_id' => $permissionId,
                    ],
                    []
                );
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissionNames = array_keys($this->uiInvoicePermissions());
        $permissionIds = Permission::whereIn('name', $permissionNames)->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        DB::table('public.role_has_permissions')
            ->whereIn('permission_id', $permissionIds)
            ->delete();

        Permission::whereIn('id', $permissionIds)->delete();
    }

    private function uiInvoicePermissions(): array
    {
        return [
            'invoices.view' => 'View invoices (UI)',
            'invoices.create' => 'Create invoices (UI)',
            'invoices.update' => 'Update invoices (UI)',
            'invoices.delete' => 'Delete invoices (UI)',
            'invoices.send' => 'Send invoices (UI)',
            'invoices.export' => 'Export invoices (UI)',
            'invoices.post' => 'Post invoices (UI)',
        ];
    }

    private function uiRoleAssignments(): array
    {
        return [
            'system_owner' => [
                'invoices.view', 'invoices.create', 'invoices.update', 'invoices.delete', 'invoices.send', 'invoices.export', 'invoices.post',
            ],
            'company_owner' => [
                'invoices.view', 'invoices.create', 'invoices.update', 'invoices.delete', 'invoices.send', 'invoices.export', 'invoices.post',
            ],
            'accountant' => [
                'invoices.view', 'invoices.create', 'invoices.update', 'invoices.post',
            ],
            'member' => [
                'invoices.view',
            ],
        ];
    }
};
