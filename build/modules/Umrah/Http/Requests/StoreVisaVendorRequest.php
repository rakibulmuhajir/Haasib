<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\VisaVendor;
use Illuminate\Validation\Rule;

class StoreVisaVendorRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_VENDOR_CREATE;
    }

    public function rules(): array
    {
        return [
            'vendor_number' => [
                'nullable',
                'string',
                'max:50',
                $this->uniqueForCompany(VisaVendor::class, 'vendor_number', 'This vendor number is already used.'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'vendor_type' => ['required', Rule::in([VisaVendor::TYPE_GOVERNMENT, VisaVendor::TYPE_VISA_PROVIDER, VisaVendor::TYPE_OTHER])],
            'is_default' => ['sometimes', 'boolean'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'logo_url' => ['nullable', 'url:http,https', 'max:500'],
            'adult_retail_amount' => $this->visaRateRules(true),
            'adult_cost_amount' => $this->visaRateRules(true),
            'child_retail_amount' => $this->visaRateRules(true),
            'child_cost_amount' => $this->visaRateRules(true),
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
