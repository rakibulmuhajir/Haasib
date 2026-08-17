<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\VisaVendor;

class UpdateTransportProviderRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_VENDOR_UPDATE;
    }

    public function rules(): array
    {
        return [
            'vendor_number' => ['nullable', 'string', 'max:50', $this->uniqueForCompany(VisaVendor::class, 'vendor_number', 'This transport vendor number is already used.', (string) $this->route('transportProvider'))],
            'name' => ['required', 'string', 'max:255'],
            'is_company_owned' => ['sometimes', 'boolean'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'logo_url' => ['nullable', 'url:http,https', 'max:500'],
            'standard_bus_retail_amount' => ['required', 'numeric', 'min:0'],
            'standard_bus_cost_amount' => ['required', 'numeric', 'min:0'],
            'charge_child_fare' => ['required', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
