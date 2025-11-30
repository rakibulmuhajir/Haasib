<?php

namespace App\Actions\User;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Models\Role;
use App\Models\User;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\DB;

class AssignRoleAction implements PaletteAction
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

        $membership = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$membership) {
            throw new \Exception("User {$params['email']} is not a member of {$company->name}");
        }

        $role = Role::where('name', $params['role'])
            ->where(fn($q) => $q->where('company_id', $company->id)->orWhereNull('company_id'))
            ->orderByRaw('CASE WHEN company_id = ? THEN 0 ELSE 1 END', [$company->id])
            ->firstOrFail();

        $user->assignRole($role, $company);

        return [
            'message' => "Role assigned: {$role->name} \u2192 {$user->email}",
            'data' => [
                'user' => $user->email,
                'role' => $role->name,
            ],
        ];
    }
}
