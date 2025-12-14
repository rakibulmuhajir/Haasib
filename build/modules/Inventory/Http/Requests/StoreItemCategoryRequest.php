<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class StoreItemCategoryRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::ITEM_CATEGORY_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'parent_id' => 'nullable|uuid|exists:inv.item_categories,id',
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ];
    }
}
