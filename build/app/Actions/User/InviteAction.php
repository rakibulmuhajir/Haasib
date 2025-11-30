<?php

namespace App\Actions\User;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Models\Role;
use App\Models\User;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InviteAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'role' => 'nullable|string',
            'name' => 'nullable|string|max:255',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::COMPANY_INVITE_USER;
    }

    public function handle(array $params): array
    {
        $company = app(CurrentCompany::class)->get();

        if (!$company) {
            throw new \Exception('No company context set');
        }

        $role = $params['role'] ?? 'member';

        return DB::transaction(function () use ($params, $company, $role) {
            $user = User::where('email', $params['email'])->first();

            if (!$user) {
                $user = User::create([
                    'name' => $params['name'] ?? explode('@', $params['email'])[0],
                    'email' => $params['email'],
                    'username' => Str::slug(explode('@', $params['email'])[0]) . '-' . Str::random(4),
                    'password' => Hash::make(Str::random(32)),
                ]);
            }

            $existing = DB::table('auth.company_user')
                ->where('company_id', $company->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                throw new \Exception("User {$params['email']} is already a member");
            }

            DB::table('auth.company_user')->insert([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'role' => $role,
                'invited_by_user_id' => Auth::id(),
                'joined_at' => now(),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $roleModel = Role::where('name', $role)
                ->where(fn($q) => $q->where('company_id', $company->id)->orWhereNull('company_id'))
                ->orderByRaw('CASE WHEN company_id = ? THEN 0 ELSE 1 END', [$company->id])
                ->first();
            if ($roleModel) {
                $user->assignRole($roleModel, $company);
            }

            return [
                'message' => "User invited: {$user->email} (" . ucfirst($role) . ")",
                'data' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                    'role' => $role,
                ],
            ];
        });
    }
}
