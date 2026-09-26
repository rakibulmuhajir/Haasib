<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Models\Customer;
use App\Modules\FuelStation\Models\Investor;
use App\Modules\FuelStation\Models\Pump;
use App\Modules\FuelStation\Models\SaleMetadata;
use App\Modules\Inventory\Models\Item;
use Illuminate\Validation\Rule;

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
            'item_id' => ['required', 'uuid', Rule::exists(Item::class, 'id')],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'sale_date' => ['nullable', 'date'],
            'pump_id' => ['nullable', 'uuid', Rule::exists(Pump::class, 'id')],
            'customer_id' => ['nullable', 'uuid', Rule::exists(Customer::class, 'id')],
            'investor_id' => ['nullable', 'uuid', Rule::exists(Investor::class, 'id')],
            'description' => ['nullable', 'string', 'max:255'],
            'discount_per_liter' => ['nullable', 'numeric', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
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

            // A discount is either a per-litre amount or a percent of the sale, never both --
            // FuelSaleService prices it exactly one way.
            if ($this->discount_per_liter && $this->discount_percent) {
                $validator->errors()->add('discount_percent', 'Enter either a per-litre discount or a percent discount, not both.');
            }

            // Bulk sales should have a discount of one kind or the other
            if ($saleType === SaleMetadata::TYPE_BULK && !$this->discount_per_liter && !$this->discount_percent) {
                $validator->errors()->add('discount_per_liter', 'A discount is required for bulk sales.');
            }
        });
    }
}
