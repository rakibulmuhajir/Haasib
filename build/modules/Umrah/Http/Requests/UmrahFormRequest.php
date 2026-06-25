<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use App\Services\CompanyContextService;
use Closure;

abstract class UmrahFormRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->validateRlsContext()
            && $this->hasCompanyPermission($this->permission());
    }

    abstract protected function permission(): string;

    protected function uniqueForCompany(string $modelClass, string $column, string $message, ?string $ignoreId = null): Closure
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();

        return function (string $attribute, mixed $value, Closure $fail) use ($companyId, $modelClass, $column, $message, $ignoreId): void {
            if ($value === null || $value === '') {
                return;
            }

            $query = $modelClass::query()
                ->where('company_id', $companyId)
                ->where($column, $value)
                ->whereNull('deleted_at');

            if ($ignoreId !== null) {
                $query->whereKeyNot($ignoreId);
            }

            if ($query->exists()) {
                $fail($message);
            }
        };
    }

    protected function existsForCompany(string $modelClass, string $message): Closure
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();

        return function (string $attribute, mixed $value, Closure $fail) use ($companyId, $modelClass, $message): void {
            if ($value === null || $value === '') {
                return;
            }

            if (! $modelClass::query()
                ->where('company_id', $companyId)
                ->whereKey($value)
                ->whereNull('deleted_at')
                ->exists()) {
                $fail($message);
            }
        };
    }
}
