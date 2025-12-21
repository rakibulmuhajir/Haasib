<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Inventory\Models\ItemCategory;
use Illuminate\Validation\Rule;

class StoreItemCategoryRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::ITEM_CATEGORY_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $company = CompanyContext::getCompany();

        return [
            'parent_id' => [
                'nullable',
                'uuid',
                Rule::exists(ItemCategory::class, 'id')
                    ->where('company_id', $company->id),
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique(ItemCategory::class, 'code')
                    ->where('company_id', $company->id)
                    ->whereNull('deleted_at'),
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'required|boolean',
            'sort_order' => 'required|integer|min:0',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'sort_order' => $this->integer('sort_order', 0),
        ]);
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'A category with this code already exists.',
            'parent_id.exists' => 'The selected parent category does not exist.',
        ];
    }
}
