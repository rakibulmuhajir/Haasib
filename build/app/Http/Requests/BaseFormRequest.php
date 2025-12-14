<?php

namespace App\Http\Requests;

use App\Services\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base company-scoped FormRequest with permission + RLS helpers.
 */
abstract class BaseFormRequest extends FormRequest
{
    protected function hasCompanyPermission(string $permission): bool
    {
        $user = $this->user();

        return $user?->hasCompanyPermission($permission) ?? false;
    }

    protected function validateRlsContext(): bool
    {
        return app(CompanyContextService::class)->getCompanyId() !== null;
    }
}
