<?php

namespace App\Modules\Accounting\Actions\User;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Models\User;
use App\Constants\Tables;
use Illuminate\Support\Facades\DB;

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
        $membership = DB::table(Tables::COMPANY_USER)
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->first();
        if (! $membership) {
            throw new \Exception("User {$params['email']} is not a member of {$company->name}");
        }
        if ($membership->role === 'owner' || $params['role'] === 'owner') {
            throw new \Exception('The owner role cannot be removed.');
        }

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
