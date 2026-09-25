<?php

namespace App\Modules\FuelStation\Http\Requests\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Park stores "Pay supplier" rows as typed, however incomplete. Post turns each row into a
 * canonical bill payment through bill_payment.create (see DailyClosePaySupplierService), so
 * each row must name a supplier, an amount and the account it is paid from before posting —
 * a row with a supplier and an amount but no account is refused inline, same as one missing
 * the amount or the supplier.
 */
class RequiresCompletePaySupplierRows implements DataAwareRule, ValidationRule
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
        foreach ($rows as $index => $row) {
            $hasAny = !empty($row['vendor_id']) || !empty($row['amount']) || !empty($row['payment_account_id']);
            if (!$hasAny) {
                continue;
            }
            if (empty($row['vendor_id']) || empty($row['payment_account_id']) || empty($row['amount'])) {
                $fail("pay_suppliers.{$index}: a supplier payment row must have a supplier, an amount and an account paid from before posting.");
            }
        }
    }
}
