<?php

namespace App\Modules\FuelStation\Http\Requests\Rules;

use App\Modules\Inventory\Models\Item;
use App\Services\CurrentCompany;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Park stores purchase rows as typed, however incomplete. Post turns each row into a
 * canonical bill, so each row must be complete, and a fuel item needs a tank to receive
 * the delivery into.
 */
class RequiresCompletePurchaseRows implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    protected array $data = [];

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $intent = $this->data['intent'] ?? 'post';
        if ($intent !== 'post') {
            return;
        }

        $rows = is_array($value) ? $value : [];
        if (!$rows) {
            return;
        }

        $company = app(CurrentCompany::class)->get();
        $itemIds = collect($rows)->pluck('item_id')->filter()->unique()->all();
        $fuelItemIds = $company
            ? Item::where('company_id', $company->id)->whereIn('id', $itemIds)->whereNotNull('fuel_category')->pluck('id')->all()
            : [];

        foreach ($rows as $index => $row) {
            if (empty($row['supplier_id']) || empty($row['item_id']) || empty($row['quantity']) || !isset($row['unit_cost'])) {
                $fail("purchases.{$index}: a purchase row must have a supplier, item, quantity and unit cost before posting.");

                continue;
            }

            if (in_array($row['item_id'], $fuelItemIds, true) && empty($row['tank_id'])) {
                $fail("purchases.{$index}.tank_id: A tank is required for a fuel purchase.");
            }
        }
    }
}
