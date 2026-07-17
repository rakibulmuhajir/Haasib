<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\VisaVendor;
use Illuminate\Validation\Rule;

class UpdateVisaVendorRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_VENDOR_UPDATE;
    }

    public function rules(): array
    {
        return [
            'vendor_number' => [
                'nullable',
                'string',
                'max:50',
                $this->uniqueForCompany(VisaVendor::class, 'vendor_number', 'This vendor number is already used.', (string) $this->route('vendor')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'vendor_type' => ['required', Rule::in(array_keys(VisaVendor::TYPES))],
            'is_company_owned' => ['sometimes', 'boolean'],
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
