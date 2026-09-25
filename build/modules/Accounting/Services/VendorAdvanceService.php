<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Actions\BillPayment\CreateAction as BillPaymentCreateAction;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Models\BillPaymentAllocation;
use Illuminate\Support\Facades\Auth;

/**
 * Applies a vendor's unapplied bill-payment balance (an advance -- see
 * BillPayment::unappliedAmount()) to a bill, oldest advance first. This is a subsidiary-
 * ledger reclass only: the cash already moved and was already posted Dr AP / Cr cash in
 * full when the payment itself was recorded (PostingService::postBillPayment), so applying
 * it here creates a new acct.bill_payment_allocations row and updates the bill's
 * paid_amount/balance/status the same way BillPayment\CreateAction does, and posts no new
 * Transaction/journal entry.
 *
 * Called two ways:
 *  - autoApply(): automatically, the instant a bill for that vendor becomes payable (see
 *    Bill\CreateAction and Bill\ReceiveAction), so nobody has to remember an advance exists.
 *  - applyToBill(): by hand from a bill's page, for a bill that already existed before the
 *    advance was recorded (Bill\ApplyAdvanceAction).
 */
class VendorAdvanceService
{
    public function autoApply(Bill $bill): array
    {
        return $this->apply($bill, null);
    }

    public function applyToBill(Bill $bill, ?float $amount = null): array
    {
        return $this->apply($bill, $amount);
    }

    private function apply(Bill $bill, ?float $amount): array
    {
        $billBalance = round((float) $bill->balance, 6);
        if ($billBalance <= 0.000001) {
            return ['applied' => 0.0, 'payments' => []];
        }

        $cap = $amount !== null ? round(min($amount, $billBalance), 6) : $billBalance;
        if ($cap <= 0.000001) {
            return ['applied' => 0.0, 'payments' => []];
        }

        // Oldest payment first, mirroring Payment\ApplyCreditAction on the AR side. A
        // payment is only a candidate once loaded with its own allocations, since
        // unappliedAmount() needs them.
        $candidates = BillPayment::where('company_id', $bill->company_id)
            ->where('vendor_id', $bill->vendor_id)
            ->whereIn('currency', array_filter([$bill->currency, $bill->base_currency]))
            ->whereNull('deleted_at')
            ->with('allocations')
            ->orderBy('payment_date')
            ->orderBy('created_at')
            ->lockForUpdate()
            ->get()
            ->filter(fn (BillPayment $payment) => $payment->unappliedAmount() > 0.000001)
            ->values();

        $remaining = $cap;
        $applied = 0.0;
        $touched = [];

        foreach ($candidates as $payment) {
            if ($remaining <= 0.000001) {
                break;
            }

            $take = round(min($remaining, $payment->unappliedAmount()), 6);
            if ($take <= 0) {
                continue;
            }

            BillPaymentAllocation::create([
                'company_id' => $bill->company_id,
                'bill_payment_id' => $payment->id,
                'bill_id' => $bill->id,
                'amount_allocated' => $take,
                'base_amount_allocated' => round($take * ($payment->exchange_rate ?? 1), 2),
                'applied_at' => now(),
            ]);

            BillPaymentCreateAction::recomputeBillStatus($bill, (float) $bill->paid_amount + $take);

            $applied = round($applied + $take, 6);
            $remaining = round($remaining - $take, 6);
            $touched[] = [
                'payment_id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'amount' => $take,
            ];
        }

        if ($applied > 0.000001) {
            $bill->updated_by_user_id = Auth::id();
            $bill->save();
        }

        return ['applied' => $applied, 'payments' => $touched];
    }

    /**
     * Sum of every unapplied bill-payment balance this vendor is currently holding, for the
     * vendor page's "Advance on account" figure and to decide whether a bill's page should
     * offer a manual "Apply advance" button.
     */
    public function totalUnapplied(string $companyId, string $vendorId): float
    {
        return round(
            BillPayment::where('company_id', $companyId)
                ->where('vendor_id', $vendorId)
                ->whereNull('deleted_at')
                ->with('allocations')
                ->get()
                ->sum(fn (BillPayment $payment) => $payment->unappliedAmount()),
            6
        );
    }
}
