<?php

namespace App\Actions\User;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Models\Role;
use App\Models\User;
use App\Services\CurrentCompany;

class RemoveRoleAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'role' => 'required|string',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::COMPANY_MANAGE_ROLES;
    }

    public function handle(array $params): array
    {
        $company = app(CurrentCompany::class)->get();

        if (!$company) {
            throw new \Exception('No company context set');
        }

        $user = User::where('email', $params['email'])->firstOrFail();
        $role = Role::where('name', $params['role'])
            ->where(fn($q) => $q->where('company_id', $company->id)->orWhereNull('company_id'))
            ->orderByRaw('CASE WHEN company_id = ? THEN 0 ELSE 1 END', [$company->id])
            ->firstOrFail();

        $user->removeRole($role, $company);

        return [
            'message' => "Role removed: {$role->name} \u2190 {$user->email}",
            'data' => [
                'user' => $user->email,
                'role' => $role->name,
            ],
        ];
    }
}
