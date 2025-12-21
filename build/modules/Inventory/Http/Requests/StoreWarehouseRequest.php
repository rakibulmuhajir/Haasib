<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Validation\Rule;

class StoreWarehouseRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::WAREHOUSE_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $company = CompanyContext::getCompany();

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique(Warehouse::class, 'code')
                    ->where('company_id', $company->id)
                    ->whereNull('deleted_at'),
            ],
            'name' => 'required|string|max:255',
            'warehouse_type' => 'required|in:standard,tank',
            'capacity' => [
                'required_if:warehouse_type,tank',
                'nullable',
                'numeric',
                'min:0',
                'max:999999999.99'
            ],
            'low_level_alert' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999.99'
            ],
            'linked_item_id' => [
                'required_if:warehouse_type,tank',
                'nullable',
                'uuid',
                'exists:inv.items,id'
            ],
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
        // Ensure boolean fields are always present with default values if missing
        $this->merge([
            'is_primary' => $this->boolean('is_primary', false),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'A warehouse with this code already exists.',
        ];
    }
}
