<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Validation\Rule;

class UpdateWarehouseRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::WAREHOUSE_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $company = CompanyContext::getCompany();
        $warehouseId = $this->route('warehouse');

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique(Warehouse::class, 'code')
                    ->where('company_id', $company->id)
                    ->ignore($warehouseId)
                    ->whereNull('deleted_at'),
            ],
            'name' => 'sometimes|required|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country_code' => 'nullable|string|size:2',
            'is_primary' => 'required|boolean',
            'is_active' => 'required|boolean',
            'notes' => 'nullable|string',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Ensure boolean fields are always present with their current values if missing
        $this->merge([
            'is_primary' => $this->boolean('is_primary', false),
            'is_active' => $this->boolean('is_active', false),
        ]);
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'A warehouse with this code already exists.',
        ];
    }
}
