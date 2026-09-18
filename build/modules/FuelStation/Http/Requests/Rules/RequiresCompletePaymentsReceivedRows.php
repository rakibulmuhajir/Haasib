<?php

namespace App\Modules\FuelStation\Http\Requests\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Park stores payments-received rows as typed, however incomplete. Post turns each row
 * into a canonical payment through Payment\CreateAction, so each row must name a buyer,
 * an amount and the account it was received into before posting.
 *
 * An invoice is deliberately NOT required: a row may name one invoice (invoice_id),
 * several (invoice_ids), or none at all -- a buyer handing over a lump sum with nothing
 * selected is a valid on-account payment that auto-allocates oldest-first and leaves any
 * remainder as credit. This mirrors DailyClosePaymentsReceivedService::prepare(), which
 * is the authority on what a complete row is; requiring invoice_id here rejected
 * perfectly good on-account and multi-invoice rows.
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
            if (empty($row['customer_id']) || empty($row['payment_account_id']) || empty($row['amount'])) {
                $fail("payments_received.{$index}: a payment row must have a buyer, an amount and an account received into before posting.");
            }
        }
    }
}
