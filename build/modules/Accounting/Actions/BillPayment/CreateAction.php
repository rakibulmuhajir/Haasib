<?php

namespace App\Modules\Accounting\Actions\BillPayment;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Models\BillPaymentAllocation;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Services\GlPostingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'vendor_id' => 'required|uuid',
            'payment_number' => 'nullable|string|max:50',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'transaction_charge' => 'nullable|numeric|min:0',
            'currency' => 'required|string|size:3|uppercase',
            'base_currency' => 'required|string|size:3|uppercase',
            'exchange_rate' => 'nullable|numeric|min:0.00000001|decimal:8',
            'payment_method' => 'required|string|max:50',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'payment_account_id' => 'nullable|uuid',
            'payment_splits' => 'nullable|array',
            'payment_splits.*.payment_account_id' => 'required_with:payment_splits|uuid',
            'payment_splits.*.amount' => 'required_with:payment_splits|numeric|min:0.01',
            'payment_splits.*.payment_method' => 'required_with:payment_splits|string|max:50',
            'payment_splits.*.reference_number' => 'nullable|string|max:100',
            'ap_account_id' => 'nullable|uuid',
            'allocations' => 'nullable|array',
            'allocations.*.bill_id' => 'required_with:allocations|uuid',
            'allocations.*.amount_allocated' => 'required_with:allocations|numeric|min:0',
            // Internal-only: lets a trusted server-side caller (never the HTTP FormRequest,
            // which never passes this key) pay from a payment channel's configured clearing
            // account instead of a cash/bank account. Still validated below against the
            // company's actual station settings — the flag alone does not bypass anything.
            'allow_clearing_account' => 'nullable|boolean',
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

        $exchangeRate = $params['currency'] === $params['base_currency'] ? null : ($params['exchange_rate'] ?? null);
        $splits = $this->normalizeSplits($params);
        $this->validatePaymentAccounts($company->id, $splits, (bool) ($params['allow_clearing_account'] ?? false));
        $splitTotal = round(collect($splits)->sum('amount'), 6);
        $paymentAmount = round((float) $params['amount'], 6);
        $transactionCharge = round((float) ($params['transaction_charge'] ?? 0), 6);
        if ($transactionCharge > $paymentAmount) {
            throw new \InvalidArgumentException('Transaction charge cannot exceed the payment amount.');
        }
        if (abs($splitTotal - $paymentAmount) > 0.000001) {
            throw new \InvalidArgumentException('Payment splits must equal the total payment amount.');
        }

        $paymentNumbers = $this->paymentNumbers($company->id, $params['payment_number'] ?? null, count($splits));
        foreach ($paymentNumbers as $paymentNumber) {
            $exists = BillPayment::where('company_id', $company->id)
                ->where('payment_number', $paymentNumber)
                ->whereNull('deleted_at')
                ->exists();
            if ($exists) {
                throw new \InvalidArgumentException("Payment number {$paymentNumber} already exists");
            }
        }

        return \App\Services\AccountingWriteTransaction::run(function () use ($company, $params, $paymentNumbers, $exchangeRate, $splits, $transactionCharge) {
            $allocationPool = $this->validateAndBuildAllocationPool($company->id, $params);
            $createdPayments = [];
            $paymentGroupId = (string) Str::uuid();
            $paymentGroupNumber = $params['payment_number'] ?? $paymentNumbers[0];

            $vendor = Vendor::where('company_id', $company->id)->find($params['vendor_id']);
            $apAccountId = $params['ap_account_id'] ?? $vendor?->ap_account_id ?? $company->ap_account_id;
            if (! $apAccountId) {
                throw new \RuntimeException('AP account is required to post the bill payment.');
            }

            foreach ($splits as $index => $split) {
                $amount = round((float) $split['amount'], 6);
                $baseAmount = round($amount * ($exchangeRate ?? 1), 2);
                $splitCharge = $index === count($splits) - 1
                    ? round($transactionCharge - collect($splits)->take($index)->sum(fn ($prior) => round((float) $prior['amount'] / $paymentAmount * $transactionCharge, 6)), 6)
                    : round($amount / $paymentAmount * $transactionCharge, 6);
                $baseSplitCharge = round($splitCharge * ($exchangeRate ?? 1), 2);
                $paymentAllocations = $this->takeAllocationsForAmount($allocationPool, $amount);

                $payment = BillPayment::create([
                'company_id' => $company->id,
                'vendor_id' => $params['vendor_id'],
                    'payment_group_id' => $paymentGroupId,
                    'payment_group_number' => $paymentGroupNumber,
                    'payment_number' => $paymentNumbers[$index],
                'payment_date' => $params['payment_date'],
                    'amount' => $amount,
                'currency' => $params['currency'],
                'exchange_rate' => $exchangeRate,
                'base_currency' => $params['base_currency'],
                'base_amount' => $baseAmount,
                'transaction_charge' => $splitCharge,
                'base_transaction_charge' => $baseSplitCharge,
                    'payment_method' => $split['payment_method'],
                    'payment_account_id' => $split['payment_account_id'],
                    'reference_number' => $split['reference_number'] ?? $params['reference_number'] ?? null,
                'notes' => $params['notes'] ?? null,
                'created_by_user_id' => Auth::id(),
            ]);

                foreach ($paymentAllocations as $allocation) {
                    BillPaymentAllocation::create([
                        'company_id' => $company->id,
                        'bill_payment_id' => $payment->id,
                        'bill_id' => $allocation['bill']->id,
                        'amount_allocated' => $allocation['amount_allocated'],
                        'base_amount_allocated' => round($allocation['amount_allocated'] * ($payment->exchange_rate ?? 1), 2),
                        'applied_at' => now(),
                    ]);

                    $this->applyAllocationToBill($allocation['bill'], (float) $allocation['amount_allocated']);
                }

                // Each split is its own payment against its own account, so
                // each one posts its own DR AP / CR bank. Inside the same
                // transaction as the rows above: a payment that cannot be
                // posted must not leave the bill looking settled.
                $this->postPaymentTransaction($payment, $split['payment_account_id'], $apAccountId);

                $createdPayments[] = $payment;
            }

            $totalUnapplied = round(collect($createdPayments)->sum(fn ($p) => $p->fresh('allocations')->unappliedAmount()), 6);
            $advanceNote = $totalUnapplied > 0.000001
                ? ' (' . \App\Support\PaletteFormatter::money($totalUnapplied, $createdPayments[0]->currency) . ' held on account)'
                : '';

            return [
                'message' => (count($createdPayments) === 1
                    ? "Payment {$createdPayments[0]->payment_number} recorded for Daily Close"
                    : count($createdPayments) . ' split payments recorded for Daily Close') . $advanceNote,
                'data' => [
                    'id' => $createdPayments[0]->id,
                    'ids' => collect($createdPayments)->pluck('id')->all(),
                    'unapplied_amount' => $totalUnapplied,
                ],
            ];
        }); // retry on deadlock (40P01): nextNumber()/paymentNumbers() above take a
        // lockForUpdate() row lock ahead of this insert into an audited table; see the
        // lock-order comment in the audit_post_close_activity migration.
    }

    /**
     * Shared with BillPayment\UpdateAction: derive a bill's paid_amount/
     * balance/status/paid_at purely from the amount now paid against it.
     * Handles both directions -- paying more (here) and paying less after an
     * edit shrinks an allocation (Update) -- and mirrors VoidAction's
     * floor-at-zero / received-or-draft fallback so a bill never reports a
     * negative balance or an inconsistent status across the three actions.
     */
    public static function recomputeBillStatus(Bill $bill, float $newPaidAmount): void
    {
        $newPaidAmount = max(0, round($newPaidAmount, 6));
        $newBalance = max(0, round((float) $bill->total_amount - $newPaidAmount, 6));

        if ($newBalance <= 0.000001) {
            $newStatus = 'paid';
        } elseif ($newPaidAmount > 0.000001) {
            $newStatus = 'partial';
        } else {
            $newStatus = $bill->received_at ? 'received' : 'draft';
        }

        $bill->paid_amount = $newPaidAmount;
        $bill->balance = $newBalance;
        $bill->status = $newStatus;
        $bill->paid_at = $newStatus === 'paid' ? ($bill->paid_at ?? now()) : null;
    }

    private function applyAllocationToBill(Bill $bill, float $amountAllocated): void
    {
        self::recomputeBillStatus($bill, (float) $bill->paid_amount + $amountAllocated);
        $bill->save();
    }

    /**
     * Post the AP-debit / cash-credit journal for one payment row and stamp
     * its transaction_id. Shared with BillPayment\UpdateAction so an edit
     * that reverses and reposts a payment uses exactly the posting call a
     * fresh payment would.
     */
    /**
     * A re-post (an edited payment) passes a fresh journal number: the first posting took
     * the payment number, and that journal stays on record, reversed, so the number is taken.
     */
    public function postPaymentTransaction(BillPayment $payment, string $paymentAccountId, string $apAccountId, ?string $transactionNumber = null): Transaction
    {
        $transaction = app(GlPostingService::class)->postBillPayment(
            $payment->fresh(['allocations', 'company']),
            $paymentAccountId,
            $apAccountId,
            $transactionNumber
        );
        $payment->transaction_id = $transaction->id;
        $payment->save();

        return $transaction;
    }

    /**
     * Ordinary payments are unchanged. An internal caller may pass allow_clearing_account to pay
     * from a clearing account, and only one that a module has registered as settling to a supplier
     * (ClearingPaymentAccounts) - the flag alone grants nothing, and the HTTP form never sends it.
     */
    private function validatePaymentAccounts(string $companyId, array $splits, bool $allowClearingAccount): void
    {
        if (! $allowClearingAccount) {
            return;
        }

        $policy = app(\App\Modules\Accounting\Services\ClearingPaymentAccounts::class);
        foreach ($splits as $split) {
            $account = Account::where('company_id', $companyId)->find($split['payment_account_id']);
            if (! $account || ! $policy->allows($companyId, $account->id)) {
                throw new \InvalidArgumentException('This account is not a clearing account that settles to a supplier.');
            }
        }
    }

    /**
     * Allocations may now fall short of the payment amount -- an advance paid before any
     * bill exists (or before it covers everything owed) leaves the remainder unapplied
     * (see BillPayment::unappliedAmount()), not an error. They may never exceed it.
     */
    private function validateAndBuildAllocationPool(string $companyId, array $params): array
    {
        $allocations = collect($params['allocations'] ?? [])
            ->filter(fn ($allocation) => (float) ($allocation['amount_allocated'] ?? 0) > 0)
            ->values();

        $sumAlloc = round((float) $allocations->sum('amount_allocated'), 6);
        if ($sumAlloc > round((float) $params['amount'], 6) + 0.000001) {
            throw new \InvalidArgumentException('Allocations cannot exceed the payment amount.');
        }

        return $allocations->map(function ($allocation) use ($companyId, $params) {
            $bill = Bill::where('company_id', $companyId)->findOrFail($allocation['bill_id']);
            if (!in_array($params['currency'], [$bill->currency, $bill->base_currency], true)) {
                throw new \InvalidArgumentException('Payment currency must match bill currency or company base');
            }

            $amount = round((float) $allocation['amount_allocated'], 6);
            if ($amount > ((float) $bill->balance + 0.000001)) {
                throw new \InvalidArgumentException("Allocation exceeds balance for bill {$bill->bill_number}.");
            }

            return [
                'bill' => $bill,
                'remaining' => $amount,
            ];
        })->all();
    }

    private function normalizeSplits(array $params): array
    {
        $splits = collect($params['payment_splits'] ?? [])
            ->filter(fn ($split) => (float) ($split['amount'] ?? 0) > 0)
            ->map(fn ($split) => [
                'payment_account_id' => $split['payment_account_id'],
                'amount' => round((float) $split['amount'], 6),
                'payment_method' => $split['payment_method'] ?? $params['payment_method'],
                'reference_number' => $split['reference_number'] ?? null,
            ])
            ->values()
            ->all();

        if (! empty($splits)) {
            return $splits;
        }

        if (empty($params['payment_account_id'])) {
            throw new \InvalidArgumentException('Payment account is required.');
        }

        return [[
            'payment_account_id' => $params['payment_account_id'],
            'amount' => round((float) $params['amount'], 6),
            'payment_method' => $params['payment_method'],
            'reference_number' => $params['reference_number'] ?? null,
        ]];
    }

    private function takeAllocationsForAmount(array &$allocationPool, float $amount): array
    {
        $remaining = round($amount, 6);
        $taken = [];

        foreach ($allocationPool as &$poolItem) {
            if ($remaining <= 0.000001) {
                break;
            }
            if ($poolItem['remaining'] <= 0.000001) {
                continue;
            }

            $take = min($poolItem['remaining'], $remaining);
            $take = round($take, 6);
            $taken[] = [
                'bill' => $poolItem['bill'],
                'amount_allocated' => $take,
            ];
            $poolItem['remaining'] = round($poolItem['remaining'] - $take, 6);
            $remaining = round($remaining - $take, 6);
        }

        // Whatever this split's amount could not be matched to a bill from the pool is not
        // an error -- it stays unapplied on this split's own BillPayment row (an advance),
        // exactly like the shortfall on the payment as a whole.
        return $taken;
    }

    private function paymentNumbers(string $companyId, ?string $requested, int $count): array
    {
        if ($count === 1) {
            return [$requested ?: $this->nextNumber($companyId)];
        }

        if ($requested) {
            return collect(range(1, $count))
                ->map(fn ($index) => substr("{$requested}-{$index}", 0, 50))
                ->all();
        }

        $first = $this->nextNumber($companyId);
        if (! preg_match('/^(.*?)(\d+)$/', $first, $matches)) {
            return collect(range(1, $count))
                ->map(fn ($index) => substr("{$first}-{$index}", 0, 50))
                ->all();
        }

        $prefix = $matches[1];
        $number = (int) $matches[2];
        $width = strlen($matches[2]);

        return collect(range(0, $count - 1))
            ->map(fn ($offset) => $prefix . str_pad((string) ($number + $offset), $width, '0', STR_PAD_LEFT))
            ->all();
    }

    private function nextNumber(string $companyId): string
    {
        return \App\Services\AccountingWriteTransaction::run(function () use ($companyId) {
            // withTrashed: a soft-deleted payment (e.g. removed by Edit day on a daily close)
            // keeps its number on the (company_id, payment_number) unique index, so it must
            // never be re-issued. Mirrors Bill\CreateAction / Invoice::generateInvoiceNumber.
            $last = BillPayment::withTrashed()
                ->where('company_id', $companyId)
                ->where('payment_number', '~', '^PMT-[0-9]+$')
                ->lockForUpdate()
                ->orderByRaw("CAST(substring(payment_number from '[0-9]+$') AS bigint) DESC")
                ->value('payment_number');

            if ($last && preg_match('/(\d+)$/', $last, $m)) {
                $seq = ((int) $m[1]) + 1;
            } else {
                $seq = 1;
            }

            return 'PMT-' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
        });
    }
}
