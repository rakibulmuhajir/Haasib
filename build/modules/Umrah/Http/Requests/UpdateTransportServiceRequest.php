<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\Driver;
use App\Modules\Umrah\Models\TransportService;

class UpdateTransportServiceRequest extends UmrahFormRequest
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
                $this->uniqueForCompany(TransportService::class, 'name', 'This transport service already exists.', (string) $this->route('transportService')),
            ],
            'driver_id' => ['nullable', 'uuid', $this->existsForCompany(Driver::class, 'Selected driver was not found.')],
            'vehicle_type' => ['nullable', 'string', 'max:100'],
            'pax_capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
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
