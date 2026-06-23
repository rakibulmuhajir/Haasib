<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\TransportService;
use App\Modules\Umrah\Models\VehicleType;

class StoreTransportServiceRequest extends UmrahFormRequest
{
    protected function permission(): string
    {
        return Permissions::UMRAH_SETTINGS_UPDATE;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:150',
                $this->uniqueForCompany(TransportService::class, 'name', 'This transport service already exists.'),
            ],
            'vehicle_type_id' => ['nullable', 'uuid', $this->existsForCompany(VehicleType::class, 'Selected vehicle type was not found.')],
            'make' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:50'],
            'number_plate' => ['nullable', 'string', 'max:50'],
            'driver_name' => ['nullable', 'string', 'max:150'],
            'driver_contact' => ['nullable', 'string', 'max:50'],
            'default_sale_amount' => ['nullable', 'numeric', 'min:0'],
            'default_cost_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
