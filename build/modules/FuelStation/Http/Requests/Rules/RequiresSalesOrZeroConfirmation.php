<?php

namespace App\Modules\FuelStation\Http\Requests\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Guards against an accidental empty submission permanently claiming a
 * business date, without forbidding a legitimate zero-sales day.
 *
 * For intent=post: nozzle_readings must be non-empty, OR the submitter must
 * explicitly confirm zero_sales_confirmed=true with a zero_sales_reason.
 * intent=park stays lenient (no check).
 */
class RequiresSalesOrZeroConfirmation implements DataAwareRule, ValidationRule
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

        $fuelSales = collect(is_array($value) ? $value : [])->contains(fn ($row) => (float) ($row['liters_sold'] ?? 0) > 0);
        $otherSales = collect($this->data['other_sales'] ?? [])->contains(fn ($row) => (float) ($row['amount'] ?? 0) > 0);
        if ($fuelSales || $otherSales) {
            return;
        }

        $confirmed = filter_var($this->data['zero_sales_confirmed'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $reason = trim((string) ($this->data['zero_sales_reason'] ?? ''));

        if ($confirmed && $reason !== '') {
            return;
        }

        $fail('No sales entered. Enter the closing meter readings.');
    }
}
