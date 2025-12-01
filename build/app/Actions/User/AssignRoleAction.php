<?php

namespace App\Actions\User;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Models\User;
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
        $company = CompanyContext::requireCompany();

        $user = User::where('email', $params['email'])->firstOrFail();

        $membership = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$membership) {
            throw new \Exception("User {$params['email']} is not a member of {$company->name}");
        }

        CompanyContext::assignRole($user, $params['role']);

        return [
            'message' => "Role assigned: {$params['role']} \u2192 {$user->email}",
            'data' => [
                'user' => $user->email,
                'role' => $params['role'],
            ],
        ];
    }
}
