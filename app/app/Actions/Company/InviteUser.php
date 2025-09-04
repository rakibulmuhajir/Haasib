<?php

namespace App\Actions\Company;

use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InviteUser
{
    public function __construct(private Tenancy $tenancy)
    {
    }

    public function handle(string $company, array $data, User $actor): array
    {
        $q = Company::query();
        if (Str::isUuid($company)) {
            $q->where('id', $company);
        } else {
            $q->where(function ($w) use ($company) {
                $w->where('slug', $company)->orWhere('name', $company);
            });
        }
        $co = $q->firstOrFail(['id']);

        if (! $actor->isSuperAdmin()) {
            $previous = app()->bound('tenant.company_id') ? app('tenant.company_id') : null;
            app()->instance('tenant.company_id', $co->id);
            $role = $this->tenancy->userRoleInCurrentCompany($actor->id);
            if ($previous) {
                app()->instance('tenant.company_id', $previous);
            } else {
                app()->forgetInstance('tenant.company_id');
            }
            abort_unless(in_array($role, ['owner', 'admin']), 403);
        }

        $email = Str::lower($data['email']);
        $role = $data['role'];
        $expiresAt = now()->addDays($data['expires_in_days'] ?? 14);
        $token = Str::lower(Str::random(48));

        $inv = null;
        DB::transaction(function () use ($co, $email, $role, $actor, $token, $expiresAt, &$inv) {
            $existing = DB::table('auth.company_invitations')
                ->where('company_id', $co->id)
                ->where('invited_email', $email)
                ->where('status', 'pending')
                ->first();

            if ($existing) {
                DB::table('auth.company_invitations')->where('id', $existing->id)->update([
                    'role' => $role,
                    'invited_by_user_id' => $actor->id,
                    'token' => $token,
                    'expires_at' => $expiresAt,
                    'updated_at' => now(),
                ]);
                $inv = (object) array_merge((array) $existing, [
                    'role' => $role,
                    'invited_by_user_id' => $actor->id,
                    'token' => $token,
                    'expires_at' => $expiresAt,
                ]);
            } else {
                $id = (string) Str::uuid();
                DB::table('auth.company_invitations')->insert([
                    'id' => $id,
                    'company_id' => $co->id,
                    'invited_email' => $email,
                    'role' => $role,
                    'invited_by_user_id' => $actor->id,
                    'token' => $token,
                    'status' => 'pending',
                    'expires_at' => $expiresAt,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $inv = DB::table('auth.company_invitations')->where('id', $id)->first();
            }
        });

        return [
            'id' => $inv->id,
            'company_id' => $inv->company_id,
            'email' => $inv->invited_email,
            'role' => $inv->role,
            'status' => $inv->status,
            'expires_at' => $inv->expires_at,
            'token' => $inv->token,
        ];
    }
}
