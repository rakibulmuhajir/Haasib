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
        $requiresVisaRates = $this->input('vendor_type') !== VisaVendor::TYPE_TRANSPORT_PROVIDER;
        $requiresTransportProvider = $requiresVisaRates
            && ! $this->boolean('provides_mandatory_transport')
            && (float) $this->input('included_bus_cost_amount', 0) > 0;

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
                Rule::requiredIf($requiresTransportProvider),
                'nullable',
                'uuid',
                Rule::exists(VisaVendor::class, 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('vendor_type', VisaVendor::TYPE_TRANSPORT_PROVIDER)->where('is_active', true)->whereNull('deleted_at')),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'logo_url' => ['nullable', 'url:http,https', 'max:500'],
            'adult_retail_amount' => $this->visaRateRules($requiresVisaRates),
            'adult_cost_amount' => $this->visaRateRules($requiresVisaRates),
            'child_retail_amount' => $this->visaRateRules($requiresVisaRates),
            'child_cost_amount' => $this->visaRateRules($requiresVisaRates),
            'included_bus_cost_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }

    private function visaRateRules(bool $required): array
    {
        return $required
            ? ['required', 'numeric', 'gt:0']
            : ['nullable', 'numeric', 'min:0'];
    }

    public function attributes(): array
    {
        return [
            'adult_retail_amount' => 'adult retail rate',
            'adult_cost_amount' => 'adult cost rate',
            'child_retail_amount' => 'child retail rate',
            'child_cost_amount' => 'child cost rate',
        ];
    }
}
