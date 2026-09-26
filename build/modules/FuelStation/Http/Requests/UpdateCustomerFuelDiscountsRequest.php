<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\FuelStation\Models\CustomerFuelDiscount;
use App\Modules\Inventory\Models\Item;
use Illuminate\Validation\Rule;

class UpdateCustomerFuelDiscountsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::CUSTOMER_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'discounts' => ['present', 'array'],
            'discounts.*.item_id' => ['required', 'uuid', Rule::exists(Item::class, 'id')],
            // A row with no type is "None" - the customer's discount for that item is
            // cleared rather than validated as a real discount.
            'discounts.*.discount_type' => ['nullable', 'in:' . implode(',', CustomerFuelDiscount::getDiscountTypes())],
            'discounts.*.value' => ['nullable', 'numeric', 'min:0.0001', 'required_with:discounts.*.discount_type'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ((array) $this->input('discounts', []) as $index => $row) {
                if (($row['discount_type'] ?? null) === CustomerFuelDiscount::TYPE_PERCENT && (float) ($row['value'] ?? 0) > 100) {
                    $validator->errors()->add("discounts.{$index}.value", 'A percent discount cannot exceed 100.');
                }
            }
        });
    }
}
