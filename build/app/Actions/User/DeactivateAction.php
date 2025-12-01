<?php

namespace App\Actions\User;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Models\User;
use App\Facades\CompanyContext;
use Illuminate\Support\Facades\DB;

class DeactivateAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'email' => 'required|email',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::COMPANY_MANAGE_USERS;
    }

    public function handle(array $params): array
    {
        
        $company = CompanyContext::requireCompany();

        if (!$company) {
            throw new \Exception('No company context set');
        }

        $user = User::where('email', $params['email'])->firstOrFail();

        DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->update(['is_active' => false, 'updated_at' => now()]);

        return [
            'message' => "User deactivated: {$user->email}",
            'data' => ['user' => $user->email],
        ];
    }
}
