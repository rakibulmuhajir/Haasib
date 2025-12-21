<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\FuelStation\Models\SaleMetadata;

class StoreFuelSaleRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::FUEL_SALE_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'sale_type' => ['required', 'in:' . implode(',', SaleMetadata::getSaleTypes())],
            'item_id' => ['required', 'uuid', 'exists:inv.items,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'sale_date' => ['nullable', 'date'],
            'pump_id' => ['nullable', 'uuid', 'exists:fuel.pumps,id'],
            'customer_id' => ['nullable', 'uuid', 'exists:acct.customers,id'],
            'investor_id' => ['nullable', 'uuid', 'exists:fuel.investors,id'],
            'description' => ['nullable', 'string', 'max:255'],
            'discount_per_liter' => ['nullable', 'numeric', 'min:0'],
            'payment_terms_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $saleType = $this->sale_type;

            // Amanat and credit sales require customer
            if (in_array($saleType, [SaleMetadata::TYPE_AMANAT, SaleMetadata::TYPE_CREDIT]) && !$this->customer_id) {
                $validator->errors()->add('customer_id', "Customer is required for {$saleType} sales.");
            }

            // Investor sales require investor
            if ($saleType === SaleMetadata::TYPE_INVESTOR && !$this->investor_id) {
                $validator->errors()->add('investor_id', 'Investor is required for investor sales.');
            }

            // Bulk sales should have discount
            if ($saleType === SaleMetadata::TYPE_BULK && !$this->discount_per_liter) {
                $validator->errors()->add('discount_per_liter', 'Discount per liter is required for bulk sales.');
            }
        });
    }
}
