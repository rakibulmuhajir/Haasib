<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\FuelStation\Models\Pump;
use App\Modules\FuelStation\Models\PumpReading;
use Illuminate\Validation\Rule;

class StorePumpReadingRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::PUMP_READING_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'pump_id' => ['required', 'uuid', Rule::exists(Pump::class, 'id')],
            'reading_date' => ['required', 'date'],
            'shift' => ['required', 'in:' . implode(',', PumpReading::getShifts())],
            'opening_meter' => ['required', 'numeric', 'min:0'],
            'closing_meter' => ['required', 'numeric', 'gte:opening_meter'],
        ];
    }

    public function messages(): array
    {
        return [
            'closing_meter.gte' => 'Closing meter must be greater than or equal to opening meter.',
        ];
    }
}
