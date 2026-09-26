<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\FuelStation\Models\CustomerFuelDiscount;

/**
 * The one place every customer fuel discount is looked up and priced. A per-litre or
 * percent discount, set on the customer page (fuel.customer_fuel_discounts), is honoured
 * identically whether the sale is a standalone Fuel -> Sales credit sale or a manual Daily
 * Close credit row -- both call here rather than repricing it themselves.
 */
class CustomerFuelDiscountService
{
    /**
     * The active discount for this customer + fuel item, or null if none is set.
     *
     * @return array{discount_type: string, value: float}|null
     */
    public function for(string $companyId, string $customerId, string $itemId): ?array
    {
        $discount = CustomerFuelDiscount::where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->where('item_id', $itemId)
            ->first();

        if (!$discount) {
            return null;
        }

        return [
            'discount_type' => $discount->discount_type,
            'value' => (float) $discount->value,
        ];
    }

    /**
     * The discount amount for a sale of $litres at $grossAmount, given a discount definition
     * (as returned by for(), or an equivalent array). Never more than the gross amount.
     *
     * @param  array{discount_type: string, value: float}  $discount
     */
    public function amount(array $discount, float $litres, float $grossAmount): float
    {
        $value = (float) ($discount['value'] ?? 0);
        if ($value <= 0 || $grossAmount <= 0) {
            return 0.0;
        }

        $amount = $discount['discount_type'] === CustomerFuelDiscount::TYPE_PER_LITRE
            ? round($litres * $value, 2)
            : round($grossAmount * $value / 100, 2);

        return min($amount, round($grossAmount, 2));
    }
}
