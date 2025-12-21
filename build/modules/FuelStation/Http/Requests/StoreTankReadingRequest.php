<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\FuelStation\Models\TankReading;

class StoreTankReadingRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::TANK_READING_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'tank_id' => ['required', 'uuid', 'exists:inv.warehouses,id'],
            'reading_date' => ['required', 'date'],
            'reading_type' => ['required', 'in:' . implode(',', TankReading::getReadingTypes())],
            'dip_measurement_liters' => ['required', 'numeric', 'min:0'],
            'variance_reason' => ['nullable', 'in:' . implode(',', TankReading::getVarianceReasons())],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
