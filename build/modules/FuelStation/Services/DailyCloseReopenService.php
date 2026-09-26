<?php

namespace App\Modules\FuelStation\Services;

use App\Models\Partner;
use App\Models\PartnerTransaction;
use App\Models\User;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillLineItem;
use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Models\BillPaymentAllocation;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\PaymentAllocation;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Models\AmanatTransaction;
use App\Modules\FuelStation\Models\CustomerProfile;
use App\Modules\FuelStation\Models\Nozzle;
use App\Modules\FuelStation\Models\NozzleReading;
use App\Modules\FuelStation\Models\TankReading;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Models\SalaryAdvance;
use App\Services\AccountingWriteTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Edit day": turns a posted (and unlocked) Daily Close back into a parked draft for its
 * business date, removing everything the close created as if it had never been posted, so
 * the owner can reopen the Create page and edit it with the full form instead of the
 * scattered post-close inputs (late expense / reading correction) on the Show page.
 *
 * Every removal here undoes exactly what DailyCloseService::processDailyClose created for
 * this close (see its own comments for each side effect); this class does not reinterpret
 * that logic, it walks the same metadata processDailyClose already wrote and reverses each
 * entry. Three DB triggers protect a posted close's own transaction row, journal/readings/
 * stock, and its credit invoices from ordinary edits (fuel.protect_close_snapshot,
 * fuel.capture_post_close_activity, fuel.protect_close_credit_invoice) and one Eloquent model
 * event does the same at the application layer (Invoice::deleting/updating ->
 * DailyCloseCreditSaleService::assertMutable); this service is the one place allowed through
 * all of them, via a transaction-local `app.reopening_close_id` Postgres session variable
 * (both the DB triggers and assertMutable check it), scoped to this one close for this one
 * transaction only (see 2026_09_26_030000_daily_close_reopen_support.php and
 * 2026_09_26_040000_daily_close_reopen_snapshot_bypass.php).
 */
class DailyCloseReopenService
{
    /** @return array{parked_date: string, warnings: array<int, string>} */
    public function reopen(Transaction $close, User $user, string $reason): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('A reason is required to reopen a posted Daily Close.');
        }

        return AccountingWriteTransaction::run(function () use ($close, $user, $reason) {
            $companyId = $close->company_id;
            // set_config(..., true) is transaction-local, exactly like SET LOCAL -- but unlike
            // SET LOCAL it accepts a bound parameter, so the close id is never interpolated
            // into SQL text.
            DB::select("SELECT set_config('app.reopening_close_id', ?, true)", [$close->id]);
            DB::select("SELECT set_config('app.current_company_id', ?, false)", [$companyId]);
            DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);

            /** @var Transaction $close */
            $close = Transaction::where('company_id', $companyId)
                ->where('transaction_type', 'fuel_daily_close')
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->findOrFail($close->id);

            $businessDate = $close->transaction_date->toDateString();
            $metadata = $close->metadata ?? [];
            if (! is_array($metadata) || empty($metadata['posting_snapshot'])) {
                throw new \RuntimeException('This Daily Close has no posting snapshot and cannot be reopened.');
            }

            $this->guardLocked($close);
            $this->guardLaterCloseExists($companyId, $businessDate);
            $this->guardReadingCorrections($close);
            $warnings = [];

            // metadata['purchase_details'] / ['expense_transaction_ids'] only exist on closes
            // posted after this feature shipped. A close posted before it still made these
            // transactions -- it just never recorded which ones. Refusing to guess is the
            // only acceptable failure mode here: an unmatched or ambiguous row must refuse the
            // whole reopen rather than silently leave (or double-post) a transaction.
            $expenseTransactionIds = $this->resolveExpenseTransactionIds($companyId, $close, $metadata);
            $purchaseDetails = $this->resolvePurchaseDetails($companyId, $close, $metadata);

            $this->guardCreditInvoicesNotPaidElsewhere($companyId, $metadata);
            $this->guardBillsNotPaidElsewhere($companyId, $purchaseDetails);
            $this->guardAdvancesUntouched($companyId, $close->id);
            $this->guardStockNotIssuedBelowWhatWouldRemain($companyId, $metadata);

            // Keep history before anything is touched.
            DB::table('fuel.daily_close_revisions')->insert([
                'id' => (string) Str::uuid(),
                'company_id' => $companyId,
                'business_date' => $businessDate,
                'close_transaction_number' => $close->transaction_number,
                'reopened_by_user_id' => $user->id,
                'reason' => $reason,
                'snapshot' => json_encode($metadata),
                'created_at' => now(),
            ]);

            $this->revertPostCloseDiscounts($companyId, $close->id, $warnings);

            $this->detachOrDeleteCreditSales($companyId, $metadata);
            $this->reversePaymentsReceived($companyId, $metadata);
            $this->reversePaySuppliers($companyId, $metadata);
            $this->reverseChannelSupplierSettlements($companyId, $metadata);
            $this->reverseBillPaymentsRecordedElsewhere($companyId, $close->id, $metadata);
            $this->reverseDeclaredExpenses($companyId, $expenseTransactionIds);
            $this->reversePurchases($companyId, $purchaseDetails, $warnings);
            $this->unreceiveDeliveries($companyId, $metadata);
            $this->reverseStockReconciliations($companyId, $close->id);
            $this->reverseAmanat($companyId, $close->id);
            $this->reversePartnerTransactions($companyId, $close->id);
            $this->reverseSalaryAdvances($companyId, $close->id, $warnings);
            $this->reversePayrollPayouts($companyId, $close->id);
            $this->deleteReadings($companyId, $businessDate);

            // The close's own journal.
            JournalEntry::where('company_id', $companyId)->where('transaction_id', $close->id)->delete();
            $close->delete(); // soft delete: every lookup used to find a posted close filters whereNull('deleted_at')

            // Park the original form input as the draft for this date, exactly like the
            // Create page's own "Save as draft" path.
            $formInput = $metadata['form_input'] ?? [];
            $formInput['date'] = $businessDate;
            app(DailyCloseReconciliationService::class)->park($companyId, $formInput, $user->id);

            return ['parked_date' => $businessDate, 'warnings' => $warnings];
        });
    }

    private function guardLocked(Transaction $close): void
    {
        if ($close->is_locked) {
            throw new \RuntimeException('Unlock the day first.');
        }
    }

    private function guardLaterCloseExists(string $companyId, string $businessDate): void
    {
        $later = Transaction::where('company_id', $companyId)
            ->where('transaction_type', 'fuel_daily_close')
            ->whereNull('deleted_at')
            ->whereNull('reversed_by_id')
            ->where('transaction_date', '>', $businessDate)
            ->orderBy('transaction_date')
            ->value('transaction_date');

        if ($later) {
            $laterDate = $later instanceof \Carbon\Carbon ? $later->toDateString() : (string) $later;
            throw new \RuntimeException("Reopen {$laterDate} first — each day's openings come from the day before.");
        }
    }

    private function guardReadingCorrections(Transaction $close): void
    {
        $count = DB::table('fuel.daily_close_reading_corrections')
            ->where('company_id', $close->company_id)
            ->where('close_transaction_id', $close->id)
            ->count();

        if ($count > 0) {
            throw new \RuntimeException("This close has {$count} reading correction(s) recorded after posting. Review them before reopening; reopening cannot yet fold a reading correction back into a draft.");
        }
    }

    /**
     * A close posted before metadata['expense_transaction_ids'] existed still created one
     * ordinary 'expense' transaction per declared row (DailyCloseEntryService::expense):
     * transaction_type 'expense', reference_type 'fuel.daily_close_expense', no reference_id,
     * dated the business date, description = the row's own description, one journal line
     * debiting the row's account_id for exactly the row's amount. That transaction is created
     * inside the same DB transaction as the close itself, strictly before it, so
     * created_at <= the close's own created_at is true for it and false for anything entered
     * afterwards through the post-close "late expense" form (a real, if indirect, link to the
     * close, not a guess). Refuses rather than guesses when a row doesn't resolve to exactly
     * one such transaction.
     *
     * @return array<int, string>
     */
    private function resolveExpenseTransactionIds(string $companyId, Transaction $close, array $metadata): array
    {
        if (array_key_exists('expense_transaction_ids', $metadata)) {
            return $metadata['expense_transaction_ids'] ?? [];
        }

        $rows = $metadata['form_input']['expenses'] ?? [];
        if (! $rows) {
            return [];
        }

        $businessDate = $close->transaction_date->toDateString();
        $candidates = Transaction::where('company_id', $companyId)
            ->where('transaction_type', 'expense')
            ->where('reference_type', 'fuel.daily_close_expense')
            ->whereDate('transaction_date', $businessDate)
            ->whereNull('deleted_at')
            ->where('created_at', '<=', $close->created_at)
            ->with('journalEntries')
            ->get();

        $usedIds = [];
        $ids = [];
        foreach ($rows as $row) {
            $amount = round((float) ($row['amount'] ?? 0), 2);
            if ($amount <= 0) {
                continue;
            }
            $accountId = $row['account_id'] ?? null;
            $description = $row['description'] ?? null;

            $matches = $candidates->filter(function (Transaction $t) use ($usedIds, $amount, $accountId, $description) {
                if (in_array($t->id, $usedIds, true) || $t->description !== $description) {
                    return false;
                }
                return $t->journalEntries->contains(
                    fn ($je) => $je->account_id === $accountId && round((float) $je->debit_amount, 2) === $amount
                );
            });

            if ($matches->count() !== 1) {
                throw new \RuntimeException("Can't safely reopen {$businessDate}: couldn't find the expense '{$description}' this close created.");
            }
            $usedIds[] = $ids[] = $matches->first()->id;
        }

        return $ids;
    }

    /**
     * Same reasoning as resolveExpenseTransactionIds(), for a close posted before
     * metadata['purchase_details'] existed. DailyCloseEntryService::purchase() creates the
     * bill via bill.create -- vendor_id, bill_date = the business date, vendor_invoice_number,
     * one line per row (item_id, warehouse_id, quantity, unit_price) -- and, when
     * status='received', bill.create posts the bill's own GL transaction and sets
     * Bill::transaction_id to it. created_at <= the close's own created_at is the same
     * relative-timing link used for expenses above. Refuses rather than guesses when a row
     * doesn't resolve to exactly one such bill.
     *
     * @return array<int, array{bill_id:?string, bill_transaction_id:?string, payment_transaction_id:?string}>
     */
    private function resolvePurchaseDetails(string $companyId, Transaction $close, array $metadata): array
    {
        if (array_key_exists('purchase_details', $metadata)) {
            return $metadata['purchase_details'] ?? [];
        }

        $rows = $metadata['form_input']['purchases'] ?? [];
        if (! $rows) {
            return [];
        }

        $businessDate = $close->transaction_date->toDateString();
        $candidates = Bill::where('company_id', $companyId)
            ->whereDate('bill_date', $businessDate)
            ->whereNull('deleted_at')
            ->where('created_at', '<=', $close->created_at)
            ->with('lineItems')
            ->get();

        $usedIds = [];
        $details = [];
        foreach ($rows as $row) {
            if (empty($row['supplier_id']) || empty($row['item_id']) || (float) ($row['quantity'] ?? 0) <= 0) {
                continue;
            }
            $quantity = round((float) $row['quantity'], 6);
            $unitCost = round((float) ($row['unit_cost'] ?? 0), 6);

            $matches = $candidates->filter(function (Bill $bill) use ($usedIds, $row, $quantity, $unitCost) {
                if (in_array($bill->id, $usedIds, true) || $bill->vendor_id !== $row['supplier_id']) {
                    return false;
                }
                return $bill->lineItems->contains(fn ($line) => $line->item_id === $row['item_id']
                    && round((float) $line->quantity, 6) === $quantity
                    && round((float) $line->unit_price, 6) === $unitCost);
            });

            if ($matches->count() !== 1) {
                $label = $row['description'] ?? ($row['item_id'] ?? 'purchase');
                throw new \RuntimeException("Can't safely reopen {$businessDate}: couldn't find the purchase '{$label}' this close created.");
            }
            $bill = $matches->first();
            $usedIds[] = $bill->id;

            $paymentTransactionId = null;
            if (filter_var($row['paid_now'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $payment = BillPayment::where('company_id', $companyId)
                    ->where('vendor_id', $bill->vendor_id)
                    ->whereDate('payment_date', $businessDate)
                    ->whereNull('deleted_at')
                    ->where('created_at', '<=', $close->created_at)
                    ->whereHas('allocations', fn ($q) => $q->where('bill_id', $bill->id)
                        ->where('amount_allocated', round((float) $bill->total_amount, 2)))
                    ->get();
                if ($payment->count() !== 1) {
                    throw new \RuntimeException("Can't safely reopen {$businessDate}: couldn't find the payment for bill {$bill->bill_number} this close made.");
                }
                $paymentTransactionId = $payment->first()->transaction_id;
            }

            $details[] = [
                'bill_id' => $bill->id,
                'bill_transaction_id' => $bill->transaction_id,
                'payment_transaction_id' => $paymentTransactionId,
            ];
        }

        return $details;
    }

    private function guardCreditInvoicesNotPaidElsewhere(string $companyId, array $metadata): void
    {
        $ownPaymentIds = collect($metadata['payments_received_details'] ?? [])->pluck('payment_id')->filter()->values()->all();

        foreach ($metadata['credit_sale_details'] ?? [] as $credit) {
            if (! in_array($credit['source'] ?? null, ['manual', 'fuel_sale_invoice'], true) || empty($credit['invoice_id'])) {
                continue;
            }
            $externalAllocated = (float) PaymentAllocation::where('company_id', $companyId)
                ->where('invoice_id', $credit['invoice_id'])
                ->when($ownPaymentIds, fn ($q) => $q->whereNotIn('payment_id', $ownPaymentIds))
                ->sum('amount_allocated');
            if ($externalAllocated > 0.004) {
                throw new \RuntimeException("Invoice {$credit['invoice_number']} has a payment that was not made by this close. Reverse that payment first.");
            }
        }
    }

    /** @param array<int, array{bill_id:?string, bill_transaction_id:?string, payment_transaction_id:?string}> $purchaseDetails */
    private function guardBillsNotPaidElsewhere(string $companyId, array $purchaseDetails): void
    {
        foreach ($purchaseDetails as $purchase) {
            if (empty($purchase['bill_id'])) {
                continue;
            }
            $ownPaymentTransactionId = $purchase['payment_transaction_id'] ?? null;
            $bill = Bill::where('company_id', $companyId)->find($purchase['bill_id']);
            if (! $bill) {
                continue;
            }
            $externalAllocated = (float) BillPaymentAllocation::where('company_id', $companyId)
                ->where('bill_id', $bill->id)
                ->whereHas('billPayment', function ($q) use ($ownPaymentTransactionId) {
                    $q->when($ownPaymentTransactionId, fn ($qq) => $qq->where(function ($w) use ($ownPaymentTransactionId) {
                        $w->whereNull('transaction_id')->orWhere('transaction_id', '!=', $ownPaymentTransactionId);
                    }), fn ($qq) => $qq);
                })
                ->sum('amount_allocated');
            if ($externalAllocated > 0.004) {
                throw new \RuntimeException("Bill {$bill->bill_number} has a payment that was not made by this close. Reverse that payment first.");
            }
            $receivedElsewhere = (float) $bill->lineItems()->sum('quantity_received') > 0
                && StockMovement::where('company_id', $companyId)
                    ->where('reference_type', 'acct.bills')
                    ->where('reference_id', $bill->id)
                    ->where('movement_date', '>', $bill->bill_date)
                    ->exists();
            if ($receivedElsewhere) {
                throw new \RuntimeException("Bill {$bill->bill_number} was received on a later date than this close recorded. Review it before reopening.");
            }
        }
    }

    private function guardAdvancesUntouched(string $companyId, string $closeId): void
    {
        $entryIds = JournalEntry::where('company_id', $companyId)->where('transaction_id', $closeId)->pluck('id');
        $advances = SalaryAdvance::where('company_id', $companyId)->whereIn('journal_entry_id', $entryIds)->get();
        foreach ($advances as $advance) {
            if ((float) $advance->amount_recovered > 0) {
                throw new \RuntimeException("A salary advance recorded by this close (for {$advance->reason}) has already had repayments recorded against it. Reverse those first.");
            }
        }
    }

    private function guardStockNotIssuedBelowWhatWouldRemain(string $companyId, array $metadata): void
    {
        foreach ($metadata['posting_snapshot']['tanks'] ?? [] as $tank) {
            $level = StockLevel::where('company_id', $companyId)
                ->where('warehouse_id', $tank['tank_id'])
                ->where('item_id', $tank['item_id'])
                ->value('quantity');
            // The close's own reconciliation movement moved stock to the physical dip; if the
            // ledger has since gone below that dip, something was issued out of the tank
            // after this close's litres were counted, and removing this close's movements
            // would drive the tank negative.
            if ($level !== null && (float) $level < ((float) $tank['physical_liters'] - 0.5)) {
                throw new \RuntimeException("Stock for tank {$tank['tank_name']} has moved since this close (now {$level}L, was declared {$tank['physical_liters']}L). Review before reopening.");
            }
        }
    }

    private function revertPostCloseDiscounts(string $companyId, string $closeId, array &$warnings): void
    {
        $discountTransactions = Transaction::where('company_id', $companyId)
            ->where('transaction_type', 'fuel_daily_close_discount')
            ->whereNull('deleted_at')
            ->where('metadata->close_id', $closeId)
            ->get();

        foreach ($discountTransactions as $txn) {
            $invoiceId = $txn->reference_id;
            $invoice = Invoice::where('company_id', $companyId)->find($invoiceId);
            $discountAmount = 0.0;
            foreach ($txn->journalEntries as $line) {
                $discountAmount = max($discountAmount, (float) $line->debit_amount);
            }
            if ($invoice) {
                $invoice->update([
                    'discount_amount' => max(0, round((float) $invoice->discount_amount - $discountAmount, 2)),
                    'total_amount' => round((float) $invoice->total_amount + $discountAmount, 2),
                    'balance' => round((float) $invoice->balance + $discountAmount, 2),
                    'status' => $invoice->status === 'paid' ? 'sent' : $invoice->status,
                ]);
                $warnings[] = "Post-close discount on {$invoice->invoice_number} was removed; reapply it after re-posting if still needed.";
            }
            JournalEntry::where('company_id', $companyId)->where('transaction_id', $txn->id)->delete();
            $txn->delete();
        }
    }

    private function detachOrDeleteCreditSales(string $companyId, array $metadata): void
    {
        foreach ($metadata['credit_sale_details'] ?? [] as $credit) {
            if (empty($credit['invoice_id'])) {
                continue;
            }
            $invoice = Invoice::where('company_id', $companyId)->find($credit['invoice_id']);
            if (! $invoice) {
                continue;
            }
            $source = $credit['source'] ?? 'manual';
            if ($source === 'accounting_invoice') {
                $invoice->update(['included_in_close_id' => null]);
                continue;
            }
            if ($source === 'fuel_sale_invoice') {
                // Pre-existing fuel-sale invoice the close only attached; hand it back to
                // pending exactly as it stood before the close touched it.
                $invoice->update(['transaction_id' => null, 'status' => 'draft', 'sent_at' => null]);
                continue;
            }
            // 'manual': the close itself created this invoice via invoice.create -- delete it
            // with its lines, nothing else in the system depends on it.
            $invoice->lineItems()->delete();
            $invoice->delete();
        }
    }

    private function reversePaymentsReceived(string $companyId, array $metadata): void
    {
        foreach ($metadata['payments_received_details'] ?? [] as $detail) {
            if (empty($detail['payment_id'])) {
                continue;
            }
            $payment = Payment::where('company_id', $companyId)->find($detail['payment_id']);
            if (! $payment) {
                continue;
            }
            foreach ($payment->paymentAllocations()->with('invoice')->get() as $allocation) {
                $invoice = $allocation->invoice;
                if ($invoice) {
                    $newBalance = round((float) $invoice->balance + (float) $allocation->amount_allocated, 2);
                    $newPaid = round((float) $invoice->paid_amount - (float) $allocation->amount_allocated, 2);
                    $invoice->update([
                        'balance' => $newBalance,
                        'paid_amount' => max(0, $newPaid),
                        'status' => $newBalance >= (float) $invoice->total_amount ? 'sent' : ($newPaid > 0.004 ? 'partial' : 'sent'),
                        'paid_at' => $newBalance > 0.004 ? null : $invoice->paid_at,
                    ]);
                }
            }
            PaymentAllocation::where('company_id', $companyId)->where('payment_id', $payment->id)->delete();
            if ($payment->transaction_id) {
                JournalEntry::where('company_id', $companyId)->where('transaction_id', $payment->transaction_id)->delete();
                Transaction::where('company_id', $companyId)->whereKey($payment->transaction_id)->delete();
            }
            $payment->delete();
        }
    }

    /** Shared by pay-supplier rows and channel supplier settlements: both are bill_payment.create. */
    private function reverseBillPayment(string $companyId, ?string $paymentId): void
    {
        if (! $paymentId) {
            return;
        }
        $payment = BillPayment::where('company_id', $companyId)->find($paymentId);
        if (! $payment) {
            return;
        }
        foreach (BillPaymentAllocation::where('company_id', $companyId)->where('bill_payment_id', $payment->id)->get() as $allocation) {
            $bill = Bill::where('company_id', $companyId)->find($allocation->bill_id);
            if ($bill) {
                $newPaid = max(0, round((float) $bill->paid_amount - (float) $allocation->amount_allocated, 2));
                $newBalance = max(0, round((float) $bill->total_amount - $newPaid, 2));
                $bill->update([
                    'paid_amount' => $newPaid,
                    'balance' => $newBalance,
                    'status' => $newBalance <= 0.004 ? 'paid' : ($newPaid > 0.004 ? 'partial' : ($bill->goods_received_at ? 'received' : $bill->status)),
                    'paid_at' => $newBalance > 0.004 ? null : $bill->paid_at,
                ]);
            }
            $allocation->delete();
        }
        if ($payment->transaction_id) {
            JournalEntry::where('company_id', $companyId)->where('transaction_id', $payment->transaction_id)->delete();
            Transaction::where('company_id', $companyId)->whereKey($payment->transaction_id)->delete();
        }
        $payment->delete();
    }

    private function reversePaySuppliers(string $companyId, array $metadata): void
    {
        foreach ($metadata['pay_supplier_details'] ?? [] as $detail) {
            $this->reverseBillPayment($companyId, $detail['payment_id'] ?? null);
        }
    }

    private function reverseChannelSupplierSettlements(string $companyId, array $metadata): void
    {
        foreach ($metadata['channel_supplier_settlements'] ?? [] as $detail) {
            $this->reverseBillPayment($companyId, $detail['bill_payment_id'] ?? null);
        }
    }

    /**
     * "Supplier Bill Payments recorded elsewhere": pre-existing payments (transaction_id was
     * null) the close only linked into its own journal. Hand them back to pending, never
     * delete them -- the close did not create them.
     */
    private function reverseBillPaymentsRecordedElsewhere(string $companyId, string $closeId, array $metadata): void
    {
        $ids = collect($metadata['bill_payment_details'] ?? [])->pluck('payment_id')->filter()->all();
        if (! $ids) {
            return;
        }
        BillPayment::where('company_id', $companyId)
            ->whereIn('id', $ids)
            ->where('transaction_id', $closeId)
            ->update(['transaction_id' => null]);
    }

    /** Each inline "record a forgotten cash expense" row is its own ordinary GL transaction. */
    private function reverseDeclaredExpenses(string $companyId, array $expenseTransactionIds): void
    {
        foreach ($expenseTransactionIds as $transactionId) {
            JournalEntry::where('company_id', $companyId)->where('transaction_id', $transactionId)->delete();
            Transaction::where('company_id', $companyId)->whereKey($transactionId)->delete();
        }
    }

    /** @param array<int, array{bill_id:?string, bill_transaction_id:?string, payment_transaction_id:?string}> $purchaseDetails */
    private function reversePurchases(string $companyId, array $purchaseDetails, array &$warnings): void
    {
        foreach ($purchaseDetails as $purchase) {
            if (empty($purchase['bill_id'])) {
                continue;
            }
            $bill = Bill::where('company_id', $companyId)->with('lineItems')->find($purchase['bill_id']);
            if (! $bill) {
                continue;
            }

            if (! empty($purchase['payment_transaction_id'])) {
                $payment = BillPayment::where('company_id', $companyId)->where('transaction_id', $purchase['payment_transaction_id'])->first();
                if ($payment) {
                    $this->reverseBillPayment($companyId, $payment->id);
                }
            }

            foreach (StockMovement::where('company_id', $companyId)->where('reference_type', 'acct.bills')->where('reference_id', $bill->id)->get() as $movement) {
                $this->removeStockMovement($companyId, $movement);
            }

            if ($purchase['bill_transaction_id'] ?? null) {
                JournalEntry::where('company_id', $companyId)->where('transaction_id', $purchase['bill_transaction_id'])->delete();
                Transaction::where('company_id', $companyId)->whereKey($purchase['bill_transaction_id'])->delete();
            }
            BillLineItem::where('company_id', $companyId)->where('bill_id', $bill->id)->delete();
            $bill->delete();
        }
    }

    private function unreceiveDeliveries(string $companyId, array $metadata): void
    {
        foreach ($metadata['deliveries_received'] ?? [] as $delivery) {
            $line = BillLineItem::where('company_id', $companyId)->find($delivery['line_id']);
            if ($line) {
                $line->update(['quantity_received' => max(0, round((float) $line->quantity_received - (float) $delivery['litres'], 6))]);
                $bill = Bill::where('company_id', $companyId)->find($line->bill_id);
                if ($bill && $bill->goods_received_at) {
                    $stillAllReceived = $bill->lineItems()->get()->every(fn ($l) => (float) $l->quantity_received >= (float) $l->quantity - (float) $l->direct_quantity);
                    if (! $stillAllReceived) {
                        $bill->update(['goods_received_at' => null]);
                    }
                }
            }
            $movement = StockMovement::where('company_id', $companyId)
                ->where('reference_type', 'acct.bills')
                ->where('reference_id', $delivery['bill_id'])
                ->where('warehouse_id', $delivery['tank_id'])
                ->whereDate('movement_date', $delivery['bill_date'])
                ->where('quantity', $delivery['litres'])
                ->orderByDesc('created_at')
                ->first();
            if ($movement) {
                $this->removeStockMovement($companyId, $movement);
            }
        }
    }

    private function reverseStockReconciliations(string $companyId, string $closeId): void
    {
        foreach (StockMovement::where('company_id', $companyId)->where('gl_transaction_id', $closeId)->get() as $movement) {
            $this->removeStockMovement($companyId, $movement);
        }
    }

    private function removeStockMovement(string $companyId, StockMovement $movement): void
    {
        // The insert trigger (inv.update_stock_level_on_movement) only fires on INSERT, so
        // removal must undo its effect by hand: subtract the same quantity it added.
        StockLevel::where('company_id', $companyId)
            ->where('warehouse_id', $movement->warehouse_id)
            ->where('item_id', $movement->item_id)
            ->decrement('quantity', (float) $movement->quantity);
        $movement->delete();
    }

    private function reverseAmanat(string $companyId, string $closeId): void
    {
        $entryIds = JournalEntry::where('company_id', $companyId)->where('transaction_id', $closeId)->pluck('id');
        foreach (AmanatTransaction::where('company_id', $companyId)->whereIn('journal_entry_id', $entryIds)->get() as $txn) {
            $profile = CustomerProfile::where('company_id', $companyId)->where('customer_id', $txn->customer_id)->where('is_amanat_holder', true)->first();
            if ($profile) {
                $sign = $txn->transaction_type === AmanatTransaction::TYPE_DEPOSIT ? -1 : 1;
                $profile->adjustAmanatBalance($sign * (float) $txn->amount);
            }
            $txn->delete();
        }
    }

    private function reversePartnerTransactions(string $companyId, string $closeId): void
    {
        $entryIds = JournalEntry::where('company_id', $companyId)->where('transaction_id', $closeId)->pluck('id');
        foreach (PartnerTransaction::where('company_id', $companyId)->whereIn('journal_entry_id', $entryIds)->get() as $txn) {
            if ($txn->transaction_type === 'withdrawal') {
                $partner = Partner::find($txn->partner_id);
                $partner?->decrement('current_period_withdrawn', (float) $txn->amount);
            }
            $txn->delete();
        }
    }

    private function reverseSalaryAdvances(string $companyId, string $closeId, array &$warnings): void
    {
        $entryIds = JournalEntry::where('company_id', $companyId)->where('transaction_id', $closeId)->pluck('id');
        foreach (SalaryAdvance::where('company_id', $companyId)->whereIn('journal_entry_id', $entryIds)->get() as $advance) {
            if ((float) $advance->amount_recovered > 0) {
                // Already refused in guardAdvancesUntouched(); defensive only.
                $warnings[] = "Salary advance for employee {$advance->employee_id} had repayments recorded and was left in place.";
                continue;
            }
            $advance->delete();
        }
    }

    private function reversePayrollPayouts(string $companyId, string $closeId): void
    {
        Payslip::where('company_id', $companyId)
            ->where('payment_gl_transaction_id', $closeId)
            ->update([
                'status' => 'approved',
                'paid_at' => null,
                'payment_method' => null,
                'payment_reference' => null,
                'payment_gl_transaction_id' => null,
            ]);
    }

    private function deleteReadings(string $companyId, string $businessDate): void
    {
        foreach (NozzleReading::where('company_id', $companyId)->whereDate('reading_date', $businessDate)->get() as $reading) {
            // Restore the nozzle's own opening-for-next-day pointer to what it was before this
            // close moved it forward.
            $previous = NozzleReading::where('company_id', $companyId)
                ->where('nozzle_id', $reading->nozzle_id)
                ->whereDate('reading_date', '<', $businessDate)
                ->orderByDesc('reading_date')
                ->first();
            Nozzle::where('company_id', $companyId)->where('id', $reading->nozzle_id)->update([
                'last_closing_reading' => $previous?->closing_electronic ?? 0,
                'last_manual_reading' => $previous?->closing_manual ?? 0,
            ]);
            $reading->delete();
        }
        TankReading::where('company_id', $companyId)->whereDate('reading_date', $businessDate)->delete();
    }
}
