<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Services\CompanyContextService;
use Illuminate\Validation\Rule;

class UpdateVendorRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::VENDOR_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();
        $vendorId = $this->route('vendor');

        $vendorNumberRule = Rule::unique('acct.vendors', 'vendor_number')
            ->ignore($vendorId)
            ->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at'));

        $emailRule = Rule::unique('acct.vendors', 'email')
            ->ignore($vendorId)
            ->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at'));

        return [
            'vendor_number' => ['sometimes', 'string', 'max:50', $vendorNumberRule],
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', $emailRule],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'address' => ['sometimes', 'nullable', 'array'],
            'address.street' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'string', 'max:100'],
            'address.state' => ['nullable', 'string', 'max:100'],
            'address.zip' => ['nullable', 'string', 'max:20'],
            'address.country' => ['nullable', 'string', 'max:2'],
            'tax_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'base_currency' => ['sometimes', 'string', 'size:3', 'uppercase'],
            'payment_terms' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'account_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'website' => ['sometimes', 'nullable', 'string', 'max:500'],
            'logo_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'ap_account_id' => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::exists('acct.accounts', 'id')->where(fn ($q) => $q
                    ->where('subtype', 'accounts_payable')
                    ->where('is_active', true)),
            ],
        ];
    }
}
