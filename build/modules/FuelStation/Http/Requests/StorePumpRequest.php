<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Services\CurrentCompany;
use Illuminate\Validation\Rule;

class StorePumpRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::PUMP_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $company = app(CurrentCompany::class)->get();

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('fuel.pumps', 'name')
                    ->where('company_id', $company?->id)
                    ->whereNull('deleted_at'),
            ],
            'tank_id' => ['required', 'uuid', 'exists:inv.warehouses,id'],
            'current_meter_reading' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'nozzle_count' => ['required', 'integer', 'min:1', 'max:2'],
            'front_electronic' => ['nullable', 'numeric', 'min:0'],
            'front_manual' => ['nullable', 'numeric', 'min:0'],
            'back_electronic' => ['nullable', 'numeric', 'min:0'],
            'back_manual' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A pump point with this name already exists. Use a different point name.',
        ];
    }
}
