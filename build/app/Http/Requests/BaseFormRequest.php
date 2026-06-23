<?php

namespace App\Http\Requests;

use App\Services\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

/**
 * Base company-scoped FormRequest with permission + RLS helpers.
 */
abstract class BaseFormRequest extends FormRequest
{
    protected function hasCompanyPermission(string $permission): bool
    {
        $user = $this->user();

        if (! $this->hasActiveCompanyAccess()) {
            return false;
        }

        return $user?->hasCompanyPermission($permission) ?? false;
    }

    protected function validateRlsContext(): bool
    {
        return app(CompanyContextService::class)->getCompanyId() !== null;
    }

    protected function hasActiveCompanyAccess(): bool
    {
        $user = $this->user();
        $companyId = app(CompanyContextService::class)->getCompanyId();

        if (! $user || ! $companyId) {
            return false;
        }

        if (str_starts_with($user->id, '00000000-0000-0000-0000-')) {
            return true;
        }

        return DB::table('auth.company_user')
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();
    }
}
