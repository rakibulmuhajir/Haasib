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
        $itemIds = collect($rows)->flatMap(fn ($row) => collect(\App\Modules\FuelStation\Services\DailyCloseEntryService::purchaseLines((array) $row))->pluck('item_id'))->filter()->unique()->all();
        $fuelItemIds = $company
            ? Item::where('company_id', $company->id)->whereIn('id', $itemIds)->whereNotNull('fuel_category')->pluck('id')->all()
            : [];

        foreach ($rows as $index => $row) {
            $lines = \App\Modules\FuelStation\Services\DailyCloseEntryService::purchaseLines((array) $row);
            if (empty($row['supplier_id']) || ! $lines) {
                $fail("purchases.{$index}: a purchase must have a supplier and at least one product with a quantity before posting.");

                continue;
            }

            foreach ($lines as $line) {
                if (! isset($line['unit_cost']) && ! isset($line['line_total'])) {
                    $fail("purchases.{$index}: enter a rate or total for every product on the purchase.");
                }
                $intoTank = (float) $line['quantity'] - (float) ($line['direct_quantity'] ?? 0);
                if (in_array($line['item_id'], $fuelItemIds, true) && empty($line['tank_id']) && $intoTank > 0.0005) {
                    $fail("purchases.{$index}.tank_id: A tank is required for a fuel purchase.");
                }
            }
        }
    }
}
