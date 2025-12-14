<?php

namespace App\Modules\Accounting\Actions\User;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Models\User;

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
        $company = CompanyContext::requireCompany();

        $user = User::where('email', $params['email'])->firstOrFail();

        CompanyContext::removeRole($user, $params['role']);

        return [
            'message' => "Role removed: {$params['role']} \u2190 {$user->email}",
            'data' => [
                'user' => $user->email,
                'role' => $params['role'],
            ],
        ];
    }
}
