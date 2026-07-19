<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\VisaVendor;
use App\Services\CompanyContextService;
use Illuminate\Validation\Rule;

class StoreVisaVendorRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_VENDOR_CREATE;
    }

    public function rules(): array
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();

        return [
            'vendor_number' => [
                'nullable',
                'string',
                'max:50',
                $this->uniqueForCompany(VisaVendor::class, 'vendor_number', 'This vendor number is already used.'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'vendor_type' => ['required', Rule::in(array_keys(VisaVendor::TYPES))],
            'is_company_owned' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'provides_mandatory_transport' => ['sometimes', 'boolean'],
            'mandatory_transport_vendor_id' => [
                Rule::requiredIf($this->input('vendor_type') !== VisaVendor::TYPE_TRANSPORT_PROVIDER && ! $this->boolean('provides_mandatory_transport')),
                'nullable',
                'uuid',
                Rule::exists(VisaVendor::class, 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('vendor_type', VisaVendor::TYPE_TRANSPORT_PROVIDER)->where('is_active', true)->whereNull('deleted_at')),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'adult_retail_amount' => ['nullable', 'numeric', 'min:0'],
            'adult_cost_amount' => ['nullable', 'numeric', 'min:0'],
            'child_retail_amount' => ['nullable', 'numeric', 'min:0'],
            'child_cost_amount' => ['nullable', 'numeric', 'min:0'],
            'included_bus_cost_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
