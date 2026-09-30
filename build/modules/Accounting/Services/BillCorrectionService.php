<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillLineItem;
use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Models\BillPaymentAllocation;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Models\VendorCredit;
use App\Modules\Accounting\Models\VendorCreditApplication;
use App\Modules\Accounting\Services\Concerns\RecordsCorrections;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Corrections to posted bills and bill payments -- the payables mirror of CorrectionService.
 * Same rule: the original is never rewritten. What changes is posted as its own entry and
 * recorded in acct.corrections with the before and after, the reason and who made it.
 *
 * Suppliers are a sub-ledger of the one payables account, so moving a bill or a bill payment
 * from one supplier to another leaves the payables total unchanged; it is still posted, as a
 * correcting journal out of the old supplier and into the new, so the move shows in the journal
 * with its correction number. Payments already applied to a moved bill by the old supplier come
 * off it and go back to being that supplier's unapplied advance.
 *
 * Splitting a bill between suppliers keeps the bill (the first supplier keeps it): a vendor
 * credit takes each other share off it, and a new bill with the same date and vendor invoice
 * number puts that share on the supplier it belongs to. Cost/expense is untouched; only who is
 * owed moves.
 */
class BillCorrectionService
{
    use RecordsCorrections;

    public function __construct(private readonly GlPostingService $posting) {}

    /** Move a bill to the supplier it really belongs to. */
    public function billChangeSupplier(Bill $bill, string $vendorId, string $reason): array
    {
        $to = $this->vendor($bill->company_id, $vendorId);
        if ($to->id === $bill->vendor_id) {
            throw ValidationException::withMessages(['vendor_id' => 'That is already this bill\'s supplier.']);
        }

        return $this->run($bill->company_id, function () use ($bill, $to, $reason) {
            $from = Vendor::find($bill->vendor_id);
            $unapplied = $this->unapplyForeignBillPayments($bill, $to->id);
            DB::table('acct.bills')->where('id', $bill->id)->update(['vendor_id' => $to->id, 'updated_at' => now()]);

            $amount = round((float) $bill->total_amount, 2);
            $ap = $this->payablesAccount($bill);
            $journal = $this->journal($bill->company_id, $bill->currency, "Correction: {$bill->bill_number} moved from {$from?->name} to {$to->name}", [
                ['account_id' => $ap, 'type' => 'debit', 'amount' => $amount, 'description' => "{$bill->bill_number} from {$from?->name}"],
                ['account_id' => $ap, 'type' => 'credit', 'amount' => $amount, 'description' => "{$bill->bill_number} to {$to->name}"],
            ]);

            return $this->record($bill->company_id, 'bill', $bill->id, 'change_supplier', $reason, [
                'number' => $bill->bill_number,
                'before' => ['vendor_id' => $from?->id, 'vendor' => $from?->name],
                'after' => ['vendor_id' => $to->id, 'vendor' => $to->name],
                'payments_unapplied' => $unapplied,
            ], $journal);
        });
    }

    /**
     * Split a bill between the suppliers it really came from. The bill stays with the supplier it
     * was recorded against and is cancelled there by a vendor credit for each share; each share
     * becomes a new bill of its own for its supplier, same date and supplier invoice number. Cost
     * accounts are untouched: only who is owed moves. Payments on the bill come off first
     * ($unapplyPayments), each back to being its supplier's advance.
     */
    public function billSplit(Bill $bill, array $shares, string $reason, bool $unapplyPayments = false): array
    {
        $shares = array_values(array_filter(array_map(fn ($s) => ['vendor_id' => (string) $s['vendor_id'], 'amount' => round((float) $s['amount'], 2)], $shares), fn ($s) => $s['amount'] > 0));
        if (count($shares) < 2) {
            throw ValidationException::withMessages(['shares' => 'Split between at least two suppliers.']);
        }
        $credited = (float) DB::table('acct.vendor_credit_applications')->where('bill_id', $bill->id)->sum('amount_applied');
        $open = round((float) $bill->total_amount - $credited, 2);
        if (abs(array_sum(array_column($shares, 'amount')) - $open) > 0.005) {
            throw ValidationException::withMessages(['shares' => 'The shares must add up to '.number_format($open, 2).'.']);
        }
        foreach ($shares as $share) {
            $this->vendor($bill->company_id, $share['vendor_id']);
        }
        if (! $unapplyPayments && BillPaymentAllocation::where('bill_id', $bill->id)->exists()) {
            throw ValidationException::withMessages(['shares' => 'Payments are applied to this bill. Take them off first.']);
        }

        return $this->run($bill->company_id, function () use ($bill, $shares, $reason, $open) {
            $from = Vendor::find($bill->vendor_id);
            $unapplied = $this->unapplyForeignBillPayments($bill, '');

            $ap = $this->payablesAccount($bill);
            $lines = [];
            $created = [];
            foreach ($shares as $share) {
                $vendor = $this->vendor($bill->company_id, $share['vendor_id']);
                $new = $this->splitBill($bill, $vendor, $share['amount']);
                $credit = $this->splitVendorCredit($bill->fresh(), $from, $share['amount'], "{$new->bill_number} · {$vendor->name}");
                $lines[] = ['account_id' => $ap, 'type' => 'debit', 'amount' => $share['amount'], 'description' => "{$credit->credit_number} off {$bill->bill_number}"];
                $lines[] = ['account_id' => $ap, 'type' => 'credit', 'amount' => $share['amount'], 'description' => "{$new->bill_number} to {$vendor->name} (from {$bill->bill_number})"];
                $created[] = ['vendor' => $vendor->name, 'amount' => $share['amount'], 'bill' => $new->bill_number, 'bill_id' => $new->id, 'vendor_credit' => $credit->credit_number, 'vendor_credit_id' => $credit->id];
            }

            $journal = $this->journal($bill->company_id, $bill->currency, "Correction: {$bill->bill_number} split between suppliers", $lines);
            foreach ($created as $c) {
                DB::table('acct.bills')->where('id', $c['bill_id'])->update(['transaction_id' => $journal]);
                DB::table('acct.vendor_credits')->where('id', $c['vendor_credit_id'])->update(['transaction_id' => $journal]);
            }

            return $this->record($bill->company_id, 'bill', $bill->id, 'split', $reason, [
                'number' => $bill->bill_number,
                'before' => ['vendor_id' => $from?->id, 'vendor' => $from?->name, 'amount' => $open],
                'after' => ['vendor_id' => $from?->id, 'vendor' => $from?->name, 'amount' => 0, 'shares' => $created],
                'payments_unapplied' => $unapplied,
            ], $journal);
        });
    }

    /**
     * Move a bill payment to the supplier who was really paid. What it had paid off comes off
     * those bills; with $applyOldestFirst it then pays the new supplier's oldest unpaid bills
     * (VendorAdvanceService, the same path a manual "Apply advance" uses).
     */
    public function billPaymentChangeSupplier(BillPayment $payment, string $vendorId, string $reason, bool $applyOldestFirst = true): array
    {
        $to = $this->vendor($payment->company_id, $vendorId);
        if ($to->id === $payment->vendor_id) {
            throw ValidationException::withMessages(['vendor_id' => 'That is already this payment\'s supplier.']);
        }

        $result = $this->run($payment->company_id, function () use ($payment, $to, $reason) {
            $from = Vendor::find($payment->vendor_id);
            $unapplied = $this->unapplyBillPayment($payment);
            DB::table('acct.bill_payments')->where('id', $payment->id)->update(['vendor_id' => $to->id, 'updated_at' => now()]);

            $amount = round((float) $payment->amount, 2);
            $ap = $this->billPaymentPayablesAccount($payment);
            $journal = $this->journal($payment->company_id, $payment->currency ?? 'PKR', "Correction: {$payment->payment_number} moved from {$from?->name} to {$to->name}", [
                ['account_id' => $ap, 'type' => 'credit', 'amount' => $amount, 'description' => "{$payment->payment_number} from {$from?->name}"],
                ['account_id' => $ap, 'type' => 'debit', 'amount' => $amount, 'description' => "{$payment->payment_number} to {$to->name}"],
            ]);

            return $this->record($payment->company_id, 'bill_payment', $payment->id, 'change_supplier', $reason, [
                'number' => $payment->payment_number,
                'before' => ['vendor_id' => $from?->id, 'vendor' => $from?->name],
                'after' => ['vendor_id' => $to->id, 'vendor' => $to->name],
                'unapplied_from' => $unapplied,
            ], $journal);
        });

        if ($applyOldestFirst) {
            $this->applyOldestFirstBills($payment->fresh(), $to);
        }

        return $result;
    }

    // ------------------------------------------------------------------------------------------

    /**
     * Split a supplier payment between the suppliers it really paid. The money went out once and
     * stays as posted; the payment keeps the first share (amount corrected down to it, before and
     * after kept in the correction), each other share becomes a payment of its own for that
     * supplier, posted by the correcting journal. Whatever it had paid comes off first; every
     * share is left as that supplier's advance.
     */
    public function billPaymentSplit(BillPayment $payment, array $shares, string $reason): array
    {
        $total = round((float) $payment->amount, 2);
        $shares = array_values(array_filter(array_map(fn ($s) => ['vendor_id' => (string) $s['vendor_id'], 'amount' => round((float) $s['amount'], 2)], $shares), fn ($s) => $s['amount'] > 0));
        if (count($shares) < 2) {
            throw ValidationException::withMessages(['shares' => 'Split between at least two suppliers.']);
        }
        if (abs(array_sum(array_column($shares, 'amount')) - $total) > 0.005) {
            throw ValidationException::withMessages(['shares' => 'The shares must add up to '.number_format($total, 2).'.']);
        }
        foreach ($shares as $share) {
            $this->vendor($payment->company_id, $share['vendor_id']);
        }

        return $this->run($payment->company_id, function () use ($payment, $shares, $reason, $total) {
            $from = Vendor::find($payment->vendor_id);
            $keeper = $this->vendor($payment->company_id, $shares[0]['vendor_id']);
            $rate = (float) ($payment->exchange_rate ?: 1);
            $unapplied = $this->unapplyBillPayment($payment);
            DB::table('acct.bill_payments')->where('id', $payment->id)->update([
                'vendor_id' => $keeper->id,
                'amount' => $shares[0]['amount'],
                'base_amount' => round($shares[0]['amount'] * $rate, 2),
                'updated_at' => now(),
            ]);

            $ap = $this->billPaymentPayablesAccount($payment);
            $lines = [];
            $created = [];
            foreach (array_slice($shares, 1) as $share) {
                $vendor = $this->vendor($payment->company_id, $share['vendor_id']);
                $new = BillPayment::create([
                    'company_id' => $payment->company_id,
                    'vendor_id' => $vendor->id,
                    'payment_number' => $this->nextBillPaymentNumber($payment->company_id),
                    'payment_date' => $payment->payment_date,
                    'amount' => $share['amount'],
                    'currency' => $payment->currency,
                    'exchange_rate' => $payment->exchange_rate,
                    'base_currency' => $payment->base_currency,
                    'base_amount' => round($share['amount'] * $rate, 2),
                    'transaction_charge' => 0,
                    'base_transaction_charge' => 0,
                    'payment_method' => $payment->payment_method,
                    'payment_account_id' => $payment->payment_account_id,
                    'reference_number' => $payment->reference_number,
                    'notes' => "Split from {$payment->payment_number}",
                    'created_by_user_id' => Auth::id(),
                ]);
                $lines[] = ['account_id' => $ap, 'type' => 'credit', 'amount' => $share['amount'], 'description' => "{$payment->payment_number} share off {$from?->name}"];
                $lines[] = ['account_id' => $ap, 'type' => 'debit', 'amount' => $share['amount'], 'description' => "{$new->payment_number} to {$vendor->name}"];
                $created[] = ['vendor' => $vendor->name, 'amount' => $share['amount'], 'payment' => $new->payment_number, 'payment_id' => $new->id];
            }
            if ($keeper->id !== ($from?->id)) {
                $lines[] = ['account_id' => $ap, 'type' => 'credit', 'amount' => $shares[0]['amount'], 'description' => "{$payment->payment_number} from {$from?->name}"];
                $lines[] = ['account_id' => $ap, 'type' => 'debit', 'amount' => $shares[0]['amount'], 'description' => "{$payment->payment_number} to {$keeper->name}"];
            }

            $journal = $this->journal($payment->company_id, $payment->currency ?? 'PKR', "Correction: {$payment->payment_number} split between suppliers", $lines);
            DB::table('acct.bill_payments')->whereIn('id', array_column($created, 'payment_id'))->update(['transaction_id' => $journal]);

            return $this->record($payment->company_id, 'bill_payment', $payment->id, 'split', $reason, [
                'number' => $payment->payment_number,
                'before' => ['vendor_id' => $from?->id, 'vendor' => $from?->name, 'amount' => $total],
                'after' => ['vendor_id' => $keeper->id, 'vendor' => $keeper->name, 'amount' => $shares[0]['amount'], 'shares' => $created],
                'unapplied_from' => $unapplied,
            ], $journal);
        });
    }

    /** Mirrors BillPayment\CreateAction::nextNumber(); this runs inside the correction's own lock. */
    private function nextBillPaymentNumber(string $companyId): string
    {
        $last = BillPayment::withTrashed()->where('company_id', $companyId)
            ->where('payment_number', '~', '^PMT-[0-9]+$')
            ->orderByRaw("CAST(substring(payment_number from '[0-9]+$') AS bigint) DESC")
            ->value('payment_number');
        $seq = ($last && preg_match('/(\d+)$/', $last, $m)) ? ((int) $m[1]) + 1 : 1;

        return 'PMT-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    private function vendor(string $companyId, string $id): Vendor
    {
        $vendor = Vendor::where('company_id', $companyId)->find($id);
        if (! $vendor) {
            throw ValidationException::withMessages(['vendor_id' => 'Not a supplier of this company.']);
        }

        return $vendor;
    }

    /**
     * Takes other suppliers' bill payments off a bill, back to being their unapplied advance
     * (BillPayment::unappliedAmount()). An empty $keeperId takes every one off.
     */
    private function unapplyForeignBillPayments(Bill $bill, string $keeperId): array
    {
        $done = [];
        $allocations = BillPaymentAllocation::where('company_id', $bill->company_id)->where('bill_id', $bill->id)->get();
        foreach ($allocations as $allocation) {
            $payment = BillPayment::find($allocation->bill_payment_id);
            if (! $payment || $payment->vendor_id === $keeperId) {
                continue;
            }
            // Reload the bill each time: more than one foreign allocation coming off the same
            // bill must see the previous one's paid_amount, not a stale copy from before this loop.
            $this->moveBillAllocationOnAccount($allocation, Bill::find($bill->id));
            $done[] = ['payment' => $payment->payment_number, 'amount' => round((float) $allocation->amount_allocated, 2)];
        }

        return $done;
    }

    /** Takes a payment off every bill it paid; the money stays with the payment, as an advance. */
    private function unapplyBillPayment(BillPayment $payment): array
    {
        $done = [];
        foreach (BillPaymentAllocation::where('bill_payment_id', $payment->id)->get() as $allocation) {
            $bill = Bill::find($allocation->bill_id);
            $this->moveBillAllocationOnAccount($allocation, $bill);
            $done[] = ['bill' => $bill?->bill_number, 'amount' => round((float) $allocation->amount_allocated, 2)];
        }

        return $done;
    }

    /**
     * There is no on-account sentinel row on the AP side (unlike acct.payment_allocations'
     * null-invoice_id row) -- an advance is simply the gap between a payment's amount and what
     * its own allocation rows total. So taking an allocation off a bill means deleting the row.
     */
    private function moveBillAllocationOnAccount(BillPaymentAllocation $allocation, ?Bill $bill): void
    {
        $amount = (float) $allocation->amount_allocated;
        if ($bill) {
            $paid = max(0.0, round((float) $bill->paid_amount - $amount, 6));
            $balance = round((float) $bill->total_amount - $paid, 6);
            DB::table('acct.bills')->where('id', $bill->id)->update([
                'paid_amount' => $paid,
                'balance' => $balance,
                // A posted bill never goes back to draft: unpaid again, it is simply received.
                'status' => $paid <= 0.000001 ? (in_array($bill->status, ['partial', 'paid'], true) ? 'received' : $bill->status) : ($balance <= 0.000001 ? 'paid' : 'partial'),
                'paid_at' => $balance <= 0.000001 ? $bill->paid_at : null,
                'updated_at' => now(),
            ]);
        }
        $allocation->delete();
    }

    private function applyOldestFirstBills(BillPayment $payment, Vendor $vendor): void
    {
        $left = round($payment->fresh('allocations')->unappliedAmount(), 2);
        $open = Bill::where('company_id', $payment->company_id)->where('vendor_id', $vendor->id)
            ->whereNotIn('status', ['draft', 'void', 'cancelled', 'paid'])->where('balance', '>', 0)
            ->orderBy('bill_date')->orderBy('created_at')->get();
        foreach ($open as $bill) {
            if ($left <= 0.005) {
                break;
            }
            $amount = round(min($left, (float) $bill->balance), 2);
            $result = app(VendorAdvanceService::class)->applyToBill($bill, $amount);
            $left = round($left - ($result['applied'] ?: $amount), 2);
        }
    }

    private function splitBill(Bill $original, Vendor $vendor, float $amount): Bill
    {
        $bill = Bill::create([
            'company_id' => $original->company_id,
            'vendor_id' => $vendor->id,
            'bill_number' => $this->nextBillNumber($original->company_id),
            'vendor_invoice_number' => $original->vendor_invoice_number,
            'bill_date' => $original->bill_date,
            'due_date' => $original->due_date ?? $original->bill_date,
            'subtotal' => $amount,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => $amount,
            'paid_amount' => 0,
            'balance' => $amount,
            'currency' => $original->currency,
            'base_currency' => $original->base_currency,
            'exchange_rate' => $original->exchange_rate,
            'base_amount' => $amount,
            'payment_terms' => $original->payment_terms,
            'status' => 'received',
            'received_at' => now(),
            'notes' => $original->notes,
            'internal_notes' => "Split from {$original->bill_number}",
            'created_by_user_id' => Auth::id(),
        ]);
        $line = BillLineItem::where('bill_id', $original->id)->orderBy('line_number')->first();
        BillLineItem::create([
            'company_id' => $original->company_id,
            'bill_id' => $bill->id,
            'line_number' => 1,
            'description' => ($line?->description ?? 'Purchase')." (share of {$original->bill_number})",
            'quantity' => 1,
            'unit_price' => $amount,
            'tax_rate' => 0,
            'discount_rate' => 0,
            'line_total' => $amount,
            'tax_amount' => 0,
            'total' => $amount,
            'expense_account_id' => $line?->expense_account_id,
            'item_id' => $line?->item_id,
            'created_by_user_id' => Auth::id(),
        ]);

        return $bill;
    }

    private function splitVendorCredit(Bill $original, Vendor $keeper, float $amount, string $newNumber): VendorCredit
    {
        $credit = VendorCredit::create([
            'company_id' => $original->company_id,
            'vendor_id' => $keeper->id,
            'bill_id' => $original->id,
            'credit_number' => $this->nextVendorCreditNumber($original->company_id),
            'credit_date' => $original->bill_date,
            'amount' => $amount,
            'currency' => $original->currency,
            'base_currency' => $original->base_currency,
            'base_amount' => $amount,
            'reason' => "Moved to {$newNumber}",
            'status' => 'applied',
            'received_at' => now(),
            'ap_account_id' => $keeper->ap_account_id,
            'created_by_user_id' => Auth::id(),
        ]);
        $fresh = Bill::find($original->id);
        $after = round((float) $fresh->balance - $amount, 6);
        VendorCreditApplication::create([
            'company_id' => $original->company_id,
            'vendor_credit_id' => $credit->id,
            'bill_id' => $original->id,
            'amount_applied' => $amount,
            'applied_at' => now(),
            'user_id' => Auth::id(),
            'bill_balance_before' => $fresh->balance,
            'bill_balance_after' => $after,
            'notes' => 'Correction: share moved to '.$newNumber,
        ]);
        DB::table('acct.bills')->where('id', $original->id)->update([
            'paid_amount' => round((float) $fresh->total_amount - $after, 6),
            'balance' => $after,
            'status' => $after <= 0.000001 ? 'paid' : 'partial',
            'updated_at' => now(),
        ]);

        return $credit;
    }

    /** The payables account the bill was posted to. */
    private function payablesAccount(Bill $bill): string
    {
        if ($bill->transaction_id) {
            $line = DB::table('acct.journal_entries')->where('transaction_id', $bill->transaction_id)
                ->where('credit_amount', '>', 0)
                ->where(fn ($q) => $q->where('description', 'ilike', '%'.$bill->bill_number.'%')->orWhere('description', 'ilike', 'Accounts Payable%'))
                ->value('account_id');
            if ($line) {
                return $line;
            }
        }

        return $this->fallbackPayables($bill->company_id, $bill->vendor_id);
    }

    private function billPaymentPayablesAccount(BillPayment $payment): string
    {
        $tx = $payment->transaction_id ?? null;
        if ($tx) {
            $paymentAccount = $payment->payment_account_id ?? null;
            $line = DB::table('acct.journal_entries')->where('transaction_id', $tx)->where('debit_amount', '>', 0)
                ->when($paymentAccount, fn ($q) => $q->where('account_id', '!=', $paymentAccount))
                ->value('account_id');
            if ($line) {
                return $line;
            }
        }

        return $this->fallbackPayables($payment->company_id, $payment->vendor_id);
    }

    private function fallbackPayables(string $companyId, ?string $vendorId): string
    {
        $id = $vendorId ? Vendor::where('id', $vendorId)->value('ap_account_id') : null;
        $id ??= DB::table('auth.companies')->where('id', $companyId)->value('ap_account_id');
        if (! $id) {
            throw ValidationException::withMessages(['vendor_id' => 'No payables account to post the correction to.']);
        }

        return $id;
    }

    /** Mirrors Bill\CreateAction::nextNumber(); this runs inside the correction's own lock. */
    private function nextBillNumber(string $companyId): string
    {
        // Highest by number, not by text: an odd number (a hand-typed one) must not reset the count.
        $last = Bill::withTrashed()->where('company_id', $companyId)
            ->where('bill_number', '~', '^BILL-[0-9]+$')
            ->orderByRaw("CAST(substring(bill_number from '[0-9]+$') AS bigint) DESC")
            ->value('bill_number');
        $seq = ($last && preg_match('/(\d+)$/', $last, $m)) ? ((int) $m[1]) + 1 : 1;

        return 'BILL-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    /** Mirrors VendorCredit\CreateAction::nextNumber(). */
    private function nextVendorCreditNumber(string $companyId): string
    {
        $last = VendorCredit::where('company_id', $companyId)->where('credit_number', '~', '^VCRED-[0-9]+$')
            ->orderByRaw("CAST(substring(credit_number from '[0-9]+$') AS bigint) DESC")
            ->value('credit_number');
        $seq = ($last && preg_match('/(\d+)$/', $last, $m)) ? ((int) $m[1]) + 1 : 1;

        return 'VCRED-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
