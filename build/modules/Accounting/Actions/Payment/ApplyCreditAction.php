<?php

namespace App\Modules\Accounting\Actions\Payment;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\PaymentAllocation;
use App\Modules\Accounting\Services\PaymentAllocationService;
use App\Support\PaletteFormatter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Applies an existing on-account credit (money a buyer already paid that was never
 * matched to an invoice - see PaymentAllocation and Payment\CreateAction) to an invoice
 * raised later. This is a subsidiary-ledger reclass, not new money: the cash already moved
 * and was already posted Dr Cash/Cr AR (in total, not per-invoice) at the time the payment
 * was recorded, so no new Transaction/journal entry is created here - only the
 * payment_allocations rows change, moving amount from the null-invoice ("unapplied") row(s)
 * to a new row naming this invoice, oldest credit first. The buyer's overall balance is
 * unaffected (it already reflected this money as paid); only which invoice it is matched
 * against changes, exactly like CustomerStatementService already expects.
 */
class ApplyCreditAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'customer_id' => 'nullable|uuid',
            'invoice_id' => 'required|string|max:255',
            'amount' => 'nullable|numeric|min:0.01',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::PAYMENT_APPLY_CREDIT;
    }

    public function handle(array $params): array
    {
        return \App\Services\AccountingWriteTransaction::run(fn () => $this->execute($params));
    }

    private function execute(array $params): array
    {
        $company = CompanyContext::requireCompany();

        $invoice = Invoice::where('company_id', $company->id)->find($params['invoice_id']);
        if (!$invoice) {
            throw ValidationException::withMessages(['invoice_id' => 'Choose an invoice belonging to this company.']);
        }
        if (!empty($params['customer_id']) && $params['customer_id'] !== $invoice->customer_id) {
            throw ValidationException::withMessages(['customer_id' => "customer_id does not match invoice {$invoice->invoice_number}'s buyer."]);
        }
        if (in_array($invoice->status, ['cancelled', 'void'], true)) {
            throw ValidationException::withMessages(['invoice_id' => "Cannot apply credit to cancelled invoice {$invoice->invoice_number}."]);
        }
        if ($invoice->status === 'paid' || (float) $invoice->balance <= 0) {
            throw ValidationException::withMessages(['invoice_id' => "Invoice {$invoice->invoice_number} is already fully paid."]);
        }

        // Oldest unapplied credit first, mirroring how a payment auto-allocates to the
        // oldest open invoice first.
        $onAccount = PaymentAllocation::where('company_id', $company->id)
            ->whereNull('invoice_id')
            ->whereHas('payment', fn ($q) => $q->where('customer_id', $invoice->customer_id))
            ->where('amount_allocated', '>', 0)
            ->orderBy('applied_at')
            ->orderBy('created_at')
            ->lockForUpdate()
            ->get();

        $available = round((float) $onAccount->sum('amount_allocated'), 6);
        if ($available <= 0) {
            throw ValidationException::withMessages(['invoice_id' => 'This buyer has no on-account credit to apply.']);
        }

        $requested = isset($params['amount']) ? round((float) $params['amount'], 6) : min($available, (float) $invoice->balance);
        if ($requested > $available + 0.000001) {
            throw ValidationException::withMessages(['amount' => "Only {$available} is available on account."]);
        }
        $toApply = round(min($requested, (float) $invoice->balance), 6);
        if ($toApply <= 0) {
            throw ValidationException::withMessages(['amount' => 'Nothing to apply.']);
        }

        $remaining = $toApply;
        foreach ($onAccount as $credit) {
            if ($remaining <= 0.000001) {
                break;
            }
            $take = round(min($remaining, (float) $credit->amount_allocated), 6);
            if ($take <= 0) {
                continue;
            }
            $ratio = (float) $credit->amount_allocated > 0
                ? $take / (float) $credit->amount_allocated
                : 0;
            $baseTake = round((float) $credit->base_amount_allocated * $ratio, 2);

            $newBalance = round((float) $credit->amount_allocated - $take, 6);
            if ($newBalance <= 0.000001) {
                $credit->delete();
            } else {
                $credit->update([
                    'amount_allocated' => $newBalance,
                    'base_amount_allocated' => round((float) $credit->base_amount_allocated - $baseTake, 2),
                ]);
            }

            PaymentAllocation::create([
                'company_id' => $company->id,
                'payment_id' => $credit->payment_id,
                'invoice_id' => $invoice->id,
                'amount_allocated' => $take,
                'base_amount_allocated' => $baseTake,
                'applied_at' => now(),
            ]);

            $remaining = round($remaining - $take, 6);
        }

        $result = app(PaymentAllocationService::class)->settleInvoice($invoice, $toApply);

        return [
            'message' => "Applied " . PaletteFormatter::money($toApply, $invoice->currency) .
                " of on-account credit to {$invoice->invoice_number}" .
                ($result['status'] === 'paid' ? ' — {success}Paid in full{/}' : ''),
            'data' => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'applied' => $toApply,
                'status' => $result['status'],
                'balance' => $result['balance'],
            ],
        ];
    }
}
