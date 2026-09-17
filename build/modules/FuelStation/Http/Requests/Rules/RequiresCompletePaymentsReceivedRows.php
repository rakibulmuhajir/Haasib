<?php

namespace App\Modules\FuelStation\Http\Requests\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Park stores payments-received rows as typed, however incomplete. Post turns each row
 * into a canonical payment through Payment\CreateAction, so each row must name an
 * invoice, an amount and the account it was received into before posting.
 */
class RequiresCompletePaymentsReceivedRows implements DataAwareRule, ValidationRule
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
            if (empty($row['invoice_id']) || empty($row['payment_account_id']) || empty($row['amount'])) {
                $fail("payments_received.{$index}: a payment row must have an invoice, an amount and an account received into before posting.");
            }
        }
    }
}
