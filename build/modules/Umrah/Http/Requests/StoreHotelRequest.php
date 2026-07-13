<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\HotelRoomRate;
use App\Modules\Umrah\Models\HotelVendor;
use Illuminate\Validation\Rule;

class StoreHotelRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_SETTINGS_UPDATE;
    }

    public function rules(): array
    {
        return [
            'hotel_vendor_id' => ['required', 'uuid', $this->existsForCompany(HotelVendor::class, 'Selected hotel vendor was not found.')],
            'name' => ['required', 'string', 'max:255'],
            'city' => ['required', Rule::in(['Makkah', 'Madinah'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'room_rates' => ['required', 'array', 'min:1'],
            'room_rates.*.room_type' => ['required', 'distinct', Rule::in(array_keys(HotelRoomRate::TYPES))],
            'room_rates.*.retail_amount' => ['required', 'numeric', 'min:0'],
            'room_rates.*.cost_amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
