<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\Hotel;
use App\Modules\Umrah\Models\HotelRoomRate;
use App\Modules\Umrah\Models\HotelVendor;
use App\Services\CompanyContextService;
use Closure;
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
            'hotel_vendor_id' => ['required', 'uuid', $this->activeForCompany(HotelVendor::class, 'Select an active hotel vendor.')],
            'name' => ['required', 'string', 'max:255', $this->uniqueHotelNameAndCity()],
            'city' => ['required', Rule::in(['Makkah', 'Madinah'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'room_rates' => ['required', 'array', 'min:1'],
            'room_rates.*.room_type' => ['required', 'distinct', Rule::in(array_keys(HotelRoomRate::TYPES))],
            'room_rates.*.retail_amount' => ['required', 'numeric', 'min:0'],
            'room_rates.*.cost_amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    private function uniqueHotelNameAndCity(): Closure
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();
        $hotelId = $this->route('hotel');

        return function (string $attribute, mixed $value, Closure $fail) use ($companyId, $hotelId): void {
            $city = $this->input('city');
            if (! is_string($value) || ! is_string($city)) {
                return;
            }

            $query = Hotel::query()
                ->where('company_id', $companyId)
                ->whereRaw('lower(name) = lower(?)', [trim($value)])
                ->where('city', $city)
                ->whereNull('deleted_at');

            if (is_string($hotelId) && $hotelId !== '') {
                $query->whereKeyNot($hotelId);
            }

            if ($query->exists()) {
                $fail('A hotel with this name already exists in the selected city.');
            }
        };
    }
}
