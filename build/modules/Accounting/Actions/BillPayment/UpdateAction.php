<?php

namespace App\Modules\Accounting\Actions\BillPayment;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Services\DocumentDateLock;
use App\Modules\Accounting\Services\PostingService;
use Illuminate\Support\Facades\Auth;

/**
 * Edits a bill payment already posted by BillPayment\CreateAction. Unlike a
 * bill/invoice edit, only a handful of fields are ever mutable here: the
 * date, amount, paid-from account, method, reference and notes. A change to
 * date, amount or account reverses the old journal (dated its own original
 * date, same as Bill\UpdateAction) and reposts through
 * CreateAction::postPaymentTransaction() -- the same call CreateAction
 * itself makes. A reference/notes/method-only edit never touches the
 * journal.
 */
class UpdateAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|string|uuid',
            'payment_date' => 'nullable|date|before_or_equal:today',
            'amount' => 'nullable|numeric|min:0.01',
            'payment_method' => 'nullable|string|max:50',
            'payment_account_id' => 'nullable|uuid',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'ap_account_id' => 'nullable|uuid',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::BILL_PAY;
    }

    public function handle(array $params): array
    {
        return \App\Services\AccountingWriteTransaction::run(fn () => $this->execute($params));
    }

    private function execute(array $params): array
    {
        $company = CompanyContext::requireCompany();

        $payment = BillPayment::withTrashed()
            ->where('company_id', $company->id)
            ->with(['allocations.bill', 'vendor'])
            ->findOrFail($params['id']);
        // Voiding soft-deletes the payment; say so rather than "not found".
        if ($payment->trashed()) {
            throw new \InvalidArgumentException('This payment has been voided and can\'t be edited.');
        }

        $dateLock = app(DocumentDateLock::class);
        $oldDate = $payment->payment_date->toDateString();
        $dateLock->assertOpen($company->id, $oldDate, 'This payment');

        $newDate = $params['payment_date'] ?? $oldDate;
        if ($newDate !== $oldDate) {
            $dateLock->assertOpen($company->id, $newDate, 'This payment');
        }

        $oldAmount = round((float) $payment->amount, 6);
        $newAmount = array_key_exists('amount', $params) && $params['amount'] !== null
            ? round((float) $params['amount'], 6)
            : $oldAmount;
        $newAccountId = $params['payment_account_id'] ?? $payment->payment_account_id;
        $newMethod = $params['payment_method'] ?? $payment->payment_method;

        $amountChanged = abs($newAmount - $oldAmount) > 0.000001;
        $dateChanged = $newDate !== $oldDate;
        $accountChanged = $newAccountId !== $payment->payment_account_id;

        $allocations = $payment->allocations;

        if ($amountChanged) {
            if ($allocations->count() > 1) {
                throw new \InvalidArgumentException('This payment is split across several bills. Change the date, account, reference or notes instead of the amount.');
            }
            if ($allocations->count() === 1) {
                $allocation = $allocations->first();
                $bill = $allocation->bill;
                $availableBalance = round((float) $bill->balance + (float) $allocation->amount_allocated, 6);
                if ($newAmount > $availableBalance + 0.000001) {
                    throw new \InvalidArgumentException("That's more than is owed on {$bill->bill_number}.");
                }
            }
        }

        return \App\Services\AccountingWriteTransaction::run(function () use (
            $company,
            $payment,
            $allocations,
            $params,
            $newDate,
            $newAmount,
            $newAccountId,
            $newMethod,
            $amountChanged,
            $dateChanged,
            $accountChanged
        ) {
            $repost = $amountChanged || $dateChanged || $accountChanged;

            $postedApAccountId = null;
            if ($repost) {
                $transaction = $this->resolveTransaction($company->id, $payment);
                if ($transaction) {
                    // The payable account the payment actually posted to: the edit re-posts
                    // against the same one. Supplier and company rarely carry one of their own;
                    // the create form sends it, the edit form does not.
                    $postedApAccountId = \App\Modules\Accounting\Models\JournalEntry::query()
                        ->join('acct.accounts as a', 'a.id', '=', 'acct.journal_entries.account_id')
                        ->where('acct.journal_entries.transaction_id', $transaction->id)
                        ->where('acct.journal_entries.debit_amount', '>', 0)
                        ->where('a.subtype', 'accounts_payable')
                        ->value('acct.journal_entries.account_id');
                    app(PostingService::class)->reverseTransaction($transaction, 'Payment amended', $transaction->transaction_date);
                }
            }

            if ($amountChanged && $allocations->count() === 1) {
                $allocation = $allocations->first();
                $bill = $allocation->bill;

                $newBillPaidAmount = (float) $bill->paid_amount - (float) $allocation->amount_allocated + $newAmount;
                CreateAction::recomputeBillStatus($bill, $newBillPaidAmount);
                $bill->updated_by_user_id = Auth::id();
                $bill->save();

                $allocation->amount_allocated = $newAmount;
                $allocation->base_amount_allocated = round($newAmount * ($payment->exchange_rate ?? 1), 2);
                $allocation->save();
            }

            $payment->payment_date = $newDate;
            $payment->amount = $newAmount;
            $payment->base_amount = round($newAmount * ($payment->exchange_rate ?? 1), 2);
            $payment->payment_method = $newMethod;
            $payment->payment_account_id = $newAccountId;
            if (array_key_exists('reference_number', $params)) {
                $payment->reference_number = $params['reference_number'];
            }
            if (array_key_exists('notes', $params)) {
                $payment->notes = $params['notes'];
            }
            $payment->updated_by_user_id = Auth::id();
            $payment->save();

            if ($repost) {
                $vendor = $payment->vendor ?? Vendor::where('company_id', $company->id)->find($payment->vendor_id);
                $apAccountId = $params['ap_account_id'] ?? $postedApAccountId ?? $vendor?->ap_account_id ?? $company->ap_account_id;
                if (! $apAccountId) {
                    throw new \RuntimeException('AP account is required to post the bill payment.');
                }

                app(CreateAction::class)->postPaymentTransaction($payment, $newAccountId, $apAccountId, Transaction::generateJournalNumber($company->id));
            }

            return [
                'message' => "Payment {$payment->payment_number} updated",
                'data' => ['id' => $payment->id],
            ];
        });
    }

    /**
     * Same lookup VoidAction uses: prefer the payment's own transaction_id,
     * falling back to the newest non-reversal transaction referencing it, in
     * case an older row never got transaction_id backfilled.
     */
    private function resolveTransaction(string $companyId, BillPayment $payment): ?Transaction
    {
        if ($payment->transaction_id) {
            $transaction = Transaction::where('company_id', $companyId)
                ->where('id', $payment->transaction_id)
                ->whereNull('deleted_at')
                ->first();
            if ($transaction) {
                return $transaction;
            }
        }

        return Transaction::where('company_id', $companyId)
            ->where('reference_type', 'acct.bill_payments')
            ->where('reference_id', $payment->id)
            ->whereNull('reversal_of_id')
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->first();
    }
}
