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
            'vendor_type' => ['required', Rule::in(array_keys(VisaVendor::TYPES))],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
