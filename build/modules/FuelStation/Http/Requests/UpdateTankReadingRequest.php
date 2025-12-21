<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\FuelStation\Models\TankReading;

class UpdateTankReadingRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::TANK_READING_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'dip_measurement_liters' => ['sometimes', 'required', 'numeric', 'min:0'],
            'variance_reason' => ['nullable', 'in:' . implode(',', TankReading::getVarianceReasons())],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
