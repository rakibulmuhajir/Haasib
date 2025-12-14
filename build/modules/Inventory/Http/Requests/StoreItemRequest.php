<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class StoreItemRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::ITEM_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'category_id' => 'nullable|uuid|exists:inv.item_categories,id',
            'sku' => 'required|string|max:100',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'item_type' => 'required|in:product,service,non_inventory,bundle',
            'unit_of_measure' => 'required|string|max:50',
            'track_inventory' => 'boolean',
            'is_purchasable' => 'boolean',
            'is_sellable' => 'boolean',
            'cost_price' => 'numeric|min:0',
            'selling_price' => 'numeric|min:0',
            'currency' => 'required|string|size:3',
            'tax_rate_id' => 'nullable|uuid|exists:tax.tax_rates,id',
            'income_account_id' => 'nullable|uuid|exists:acct.accounts,id',
            'expense_account_id' => 'nullable|uuid|exists:acct.accounts,id',
            'asset_account_id' => 'nullable|uuid|exists:acct.accounts,id',
            'reorder_point' => 'numeric|min:0',
            'reorder_quantity' => 'numeric|min:0',
            'weight' => 'nullable|numeric|min:0',
            'weight_unit' => 'nullable|in:kg,lb,g,oz',
            'dimensions' => 'nullable|array',
            'dimensions.length' => 'nullable|numeric|min:0',
            'dimensions.width' => 'nullable|numeric|min:0',
            'dimensions.height' => 'nullable|numeric|min:0',
            'dimensions.unit' => 'nullable|string|max:10',
            'barcode' => 'nullable|string|max:100',
            'manufacturer' => 'nullable|string|max:255',
            'brand' => 'nullable|string|max:255',
            'image_url' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ];
    }
}
