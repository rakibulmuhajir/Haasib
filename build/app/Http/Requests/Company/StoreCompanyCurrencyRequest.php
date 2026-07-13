<?php

namespace App\Http\Requests\Company;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Models\CompanyCurrency;
use App\Services\CurrentCompany;
use Closure;
use Illuminate\Support\Facades\DB;

class StoreCompanyCurrencyRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::COMPANY_UPDATE) && $this->validateRlsContext();
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('currency_code')) {
            $this->merge(['currency_code' => strtoupper((string) $this->input('currency_code'))]);
        }
    }

    public function rules(): array
    {
        return [
            'currency_code' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/', $this->activeCurrency(), $this->notEnabled()],
            'exchange_rate' => ['required', 'numeric', 'gt:0', 'decimal:0,8'],
        ];
    }

    private function activeCurrency(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! DB::table('public.currencies')->where('code', $value)->where('is_active', true)->exists()) {
                $fail('Selected currency is not available.');
            }
        };
    }

    private function notEnabled(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $company = app(CurrentCompany::class)->get();
            if ($value === $company->base_currency) {
                $fail('The base currency is already enabled.');
            } elseif (CompanyCurrency::where('company_id', $company->id)->where('currency_code', $value)->exists()) {
                $fail('This currency is already enabled.');
            }
        };
    }
}
