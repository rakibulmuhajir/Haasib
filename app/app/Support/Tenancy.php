<?php

namespace App\Support;

use App\Services\CompanyLookupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Tenancy
{
    public function currentCompanyId(): ?string
    {
        return app()->bound('tenant.company_id') ? app('tenant.company_id') : null;
    }

    public function userRoleInCurrentCompany(string $userId): ?string
    {
        $cid = $this->currentCompanyId();
        if (! $cid) {
            return null;
        }

        return app(CompanyLookupService::class)->userRole($cid, $userId);
    }

    public function isMember(string $userId): bool
    {
        $cid = $this->currentCompanyId();
        if (! $cid) {
            return false;
        }

        return $this->verifyMembership($userId, $cid);
    }

    public function resolveCompanyId(Request $request, $user): ?string
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

    public function verifyMembership(string $userId, string $companyId): bool
    {
        return app(CompanyLookupService::class)->isMember($companyId, $userId);
    }

    public function applyDbSessionSettings($user, ?string $companyId = null): void
    {
        try {
            DB::select("select set_config('app.current_user_id', ?, true)", [$user->getKey()]);
            DB::select("select set_config('app.current_user_email', ?, true)", [strtolower($user->email)]);
            DB::select(
                "select set_config('app.current_company_id', ?, true)",
                [$companyId ?: '']
            );
        } catch (\Throwable $e) {
            // noop for non-PgSQL
        }
    }
}
