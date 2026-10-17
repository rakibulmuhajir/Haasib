<?php

namespace App\Modules\Accounting\Actions\Invoice;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\CreditNote;
use App\Modules\Accounting\Models\Invoice;
use Illuminate\Validation\ValidationException;

/**
 * "No discount" on one invoice. A blocked invoice is skipped by period discounts and shows why.
 * A label, not money: blocking never reprices the invoice, so an invoice that already carries a
 * discount, or that a period discount already covered, must have that undone first (a correction,
 * or voiding the period discount) -- the books are never quietly rewritten from here.
 */
class SetDiscountBlockAction implements PaletteAction, \App\Contracts\DescribesPalette
{
    public function rules(): array
    {
        return [
            'id' => 'required|uuid',
            'blocked' => 'required|boolean',
            'reason' => 'required_if_accepted:blocked|nullable|string|max:255',
        ];
    }

    public function permission(): ?string
    {
        // Whoever may give a discount may withhold one.
        return Permissions::CREDIT_NOTE_CREATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $invoice = Invoice::where('company_id', $company->id)->findOrFail($params['id']);
        $blocked = filter_var($params['blocked'], FILTER_VALIDATE_BOOLEAN);

        if ($blocked) {
            if (in_array($invoice->status, ['void', 'cancelled'], true)) {
                throw ValidationException::withMessages(['blocked' => 'This invoice is void.']);
            }
            if ((float) $invoice->discount_amount > 0) {
                throw ValidationException::withMessages(['blocked' => 'This invoice already has a discount. Remove it with a correction first.']);
            }
            $covering = CreditNote::where('company_id', $company->id)
                ->whereNotIn('status', ['draft', 'void', 'cancelled'])
                ->whereNotNull('period_discount')
                ->whereJsonContains('period_discount->invoice_ids', $invoice->id)
                ->value('credit_note_number');
            if ($covering) {
                throw ValidationException::withMessages(['blocked' => "Already discounted by {$covering}. Void that discount first."]);
            }
            $invoice->forceFill([
                'discount_blocked' => true,
                'discount_block_reason' => trim((string) $params['reason']),
                'discount_blocked_by_user_id' => auth()->id(),
                'discount_blocked_at' => now(),
            ])->save();

            return ['message' => 'Discount blocked', 'data' => ['id' => $invoice->id, 'discount_blocked' => true]];
        }

        $invoice->forceFill([
            'discount_blocked' => false,
            'discount_block_reason' => null,
            'discount_blocked_by_user_id' => null,
            'discount_blocked_at' => null,
        ])->save();

        return ['message' => 'Discount allowed', 'data' => ['id' => $invoice->id, 'discount_blocked' => false]];
    }

    public function palette(): array
    {
        return ['hidden' => true];
    }
}
