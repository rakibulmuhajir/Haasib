<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Services\CompanyContextService;
use Illuminate\Validation\Rule;

class StoreVendorRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::VENDOR_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();
        $companyBaseCurrency = app(CompanyContextService::class)->getCompany()?->base_currency;

        $vendorNumberRule = Rule::unique('acct.vendors', 'vendor_number')
            ->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at'));

        $emailRule = Rule::unique('acct.vendors', 'email')
            ->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at'));

        $baseCurrencyRule = $companyBaseCurrency
            ? Rule::in([$companyBaseCurrency])
            : 'string';

        return [
            'vendor_number' => ['required', 'string', 'max:50', $vendorNumberRule],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', $emailRule],
            'phone' => ['nullable', 'string', 'max:50'],
            'address.street' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'string', 'max:100'],
            'address.state' => ['nullable', 'string', 'max:100'],
            'address.zip' => ['nullable', 'string', 'max:20'],
            'address.country' => ['nullable', 'string', 'size:2'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'base_currency' => ['required', 'string', 'size:3', 'uppercase', $baseCurrencyRule],
            'payment_terms' => ['required', 'integer', 'min:0', 'max:365'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'website' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ];
    }
}
