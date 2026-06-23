<?php

namespace App\Modules\Umrah\Http\Requests;

use App\Constants\Permissions;
use App\Modules\Umrah\Models\VehicleType;

class StoreVehicleTypeRequest extends UmrahFormRequest
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
                'max:100',
                $this->uniqueForCompany(VehicleType::class, 'name', 'This vehicle type already exists.'),
            ],
            'seats' => ['nullable', 'integer', 'min:1', 'max:100'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
