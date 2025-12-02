<?php

namespace App\Actions\User;

use App\Constants\Permissions;
use App\Constants\Tables;
use App\Contracts\PaletteAction;
use App\Models\User;
use App\Facades\CompanyContext;
use Illuminate\Support\Facades\DB;

class DeleteAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'email' => 'required|email',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::COMPANY_DELETE_USER;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        $user = User::where('email', $params['email'])->firstOrFail();

        return DB::transaction(function () use ($user, $company) {
            DB::table(Tables::COMPANY_USER)
                ->where('company_id', $company->id)
                ->where('user_id', $user->id)
                ->delete();

            $otherMemberships = DB::table(Tables::COMPANY_USER)
                ->where('user_id', $user->id)
                ->count();

            if ($otherMemberships === 0) {
                $user->delete();
                $message = "User deleted permanently: {$user->email}";
            } else {
                $message = "User removed from company: {$user->email}";
            }

            return [
                'message' => $message,
                'data' => ['user' => $user->email],
            ];
        });
    }
}
