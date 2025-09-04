<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Tenancy
{
    public static function currentCompanyId(): ?string
    {
        return app()->bound('tenant.company_id') ? app('tenant.company_id') : null;
    }

    public static function userRoleInCurrentCompany(string $userId): ?string
    {
        $cid = self::currentCompanyId();
        if (! $cid) return null;

        return DB::table('auth.company_user')
            ->where('user_id', $userId)
            ->where('company_id', $cid)
            ->value('role');
    }

    public static function isMember(string $userId): bool
    {
        $cid = self::currentCompanyId();
        if (! $cid) return false;

        return self::verifyMembership($userId, $cid);
    }

    public static function resolveCompanyId(Request $request, $user): ?string
    {
        $companyId = $request->header('X-Company-Id');
        if (! $companyId && $request->hasSession()) {
            $companyId = $request->session()->get('current_company_id');
        }
        if (! $companyId) {
            $companyId = $user->companies()
                ->limit(1)
                ->pluck($user->companies()->getRelated()->getQualifiedKeyName())
                ->first();
        }

        return $companyId ?: null;
    }

    public static function verifyMembership(string $userId, string $companyId): bool
    {
        return DB::table('auth.company_user')
            ->where('user_id', $userId)
            ->where('company_id', $companyId)
            ->exists();
    }

    public static function applyDbSessionSettings($user, ?string $companyId = null): void
    {
        try {
            DB::select("select set_config('app.current_user_id', ?, true)", [$user->getKey()]);
            DB::select("select set_config('app.current_user_email', ?, true)", [strtolower($user->email)]);
            if ($companyId) {
                DB::select("select set_config('app.current_company_id', ?, true)", [$companyId]);
            }
        } catch (\Throwable $e) {
            // noop for non-PgSQL
        }
    }
}
