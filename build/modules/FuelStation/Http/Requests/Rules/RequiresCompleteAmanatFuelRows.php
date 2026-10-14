<?php

namespace App\Modules\FuelStation\Http\Requests\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/** Incomplete fuel rows may be parked; posting requires reconciled inputs. */
class RequiresCompleteAmanatFuelRows implements DataAwareRule, ValidationRule
{
    private array $data = [];

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (($this->data['intent'] ?? 'post') === 'park') {
            return;
        }
        foreach ((array) $value as $row) {
            if (($row['kind'] ?? 'withdrawal') !== 'fuel_purchase') {
                continue;
            }
            $quantity = (float) ($row['fuel_quantity'] ?? 0);
            $rate = (float) ($row['fuel_rate'] ?? 0);
            if (empty($row['fuel_item_id']) || $quantity <= 0 || $rate <= 0) {
                $fail('Select a fuel type, quantity and rate for every Amanat fuel withdrawal.');
            } elseif (abs(round($quantity * $rate, 2) - (float) ($row['amount'] ?? 0)) > 0.005) {
                $fail('The Amanat fuel amount must equal quantity × rate.');
            }
        }
    }
}
