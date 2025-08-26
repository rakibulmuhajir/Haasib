<?php

namespace App\Support;

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

        return DB::table('auth.company_user')
            ->where('user_id', $userId)
            ->where('company_id', $cid)
            ->exists();
    }
}
