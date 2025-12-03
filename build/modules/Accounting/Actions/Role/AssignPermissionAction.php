<?php

namespace App\Modules\Accounting\Actions\Role;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Models\Permission;
use App\Models\Role;
use App\Facades\CompanyContext;

class AssignPermissionAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'permission' => 'required|string',
            'role' => 'required|string',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::COMPANY_MANAGE_ROLES;
    }

    public function handle(array $params): array
    {
        
        $company = CompanyContext::requireCompany();

        $role = Role::where('name', $params['role'])
            ->where(fn($q) => $q->where('company_id', $company?->id)->orWhereNull('company_id'))
            ->firstOrFail();

        $permission = Permission::where('name', $params['permission'])->firstOrFail();

        $role->givePermissionTo($permission);

        return [
            'message' => "Permission assigned: {$permission->name} \u2192 {$role->name}",
            'data' => [
                'role' => $role->name,
                'permission' => $permission->name,
            ],
        ];
    }
}
