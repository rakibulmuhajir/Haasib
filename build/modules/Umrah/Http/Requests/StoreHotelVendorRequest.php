<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\HotelVendor;

class StoreHotelVendorRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_SETTINGS_UPDATE;
    }

    public function rules(): array
    {
        return [
            'vendor_number' => ['nullable', 'string', 'max:50', $this->uniqueForCompany(HotelVendor::class, 'vendor_number', 'This hotel vendor number is already used.')],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'logo_url' => ['nullable', 'url:http,https', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
