<?php

namespace Modules\Accounting\Domain\Payments\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Accounting\Domain\Ledgers\Actions\CreateJournalEntryAction;
use Modules\Accounting\Domain\Ledgers\Events\LedgerEntryCreated;
use Modules\Accounting\Domain\Payments\Events\PaymentCreated;
use Modules\Accounting\Domain\Payments\Events\PaymentAllocated;
use Modules\Accounting\Domain\Payments\Events\PaymentReversed;
use Modules\Accounting\Domain\Payments\Events\AllocationReversed;

class LedgerService
{
    /**
     * Chart of accounts constants.
     */
    const ACCOUNTS_RECEIVABLE = '1100'; // AR
    const CASH_ACCOUNT = '1200'; // Bank/Cash
    const UNDEPOSITED_FUNDS = '1250'; // Undeposited Funds
    const PAYMENT_PROCESSING_FEES = '4600'; // Expense - Processing Fees
    const SALES_DISCOUNTS = '4700'; // Revenue - Discounts
    const SALES_RETURNS = '4750'; // Revenue - Returns
    
    /**
     * Journal entry types.
     */
    const JOURNAL_PAYMENT = 'payment';
    const JOURNAL_REVERSAL = 'reversal';
    const JOURNAL_ALLOCATION = 'allocation';
    const JOURNAL_ALLOCATION_REVERSAL = 'allocation_reversal';

    /**
     * Create journal entries for payment recording.
     */
    public function recordPayment(PaymentCreated $event): array
    {
        $paymentData = $event->getData();
        $paymentId = $paymentData['payment_id'];
        $amount = $paymentData['amount'];
        $paymentMethod = $paymentData['payment_method'];
        $companyId = $paymentData['company_id'];

        return DB::transaction(function () use ($paymentId, $amount, $paymentMethod, $companyId, $paymentData) {
            $journalEntries = [];

            // Determine the cash/bank account based on payment method
            $cashAccount = $this->getCashAccountForPaymentMethod($paymentMethod);

            // Debit: Cash/Bank Account (increase assets)
            $journalEntries[] = $this->createJournalEntry([
                'payment_id' => $paymentId,
                'company_id' => $companyId,
                'entry_type' => self::JOURNAL_PAYMENT,
                'account_code' => $cashAccount,
                'debit_amount' => $amount,
                'credit_amount' => 0,
                'description' => "Payment received - {$paymentData['payment_number']}",
                'reference' => $paymentData['payment_number'],
                'date' => $paymentData['payment_date'],
                'metadata' => [
                    'payment_method' => $paymentMethod,
                    'entity_id' => $paymentData['entity_id'],
                    'entity_type' => $paymentData['entity_type'] ?? 'customer',
                    'auto_allocated' => $paymentData['auto_allocated'] ?? false,
                ],
            ]);

            // Credit: Undeposited Funds or Accounts Receivable (decrease other assets)
            $arAccount = $this->shouldUseUndepositedFunds($paymentMethod) 
                ? self::UNDEPOSITED_FUNDS 
                : self::ACCOUNTS_RECEIVABLE;

            $journalEntries[] = $this->createJournalEntry([
                'payment_id' => $paymentId,
                'company_id' => $companyId,
                'entry_type' => self::JOURNAL_PAYMENT,
                'account_code' => $arAccount,
                'debit_amount' => 0,
                'credit_amount' => $amount,
                'description' => "Payment applied - {$paymentData['payment_number']}",
                'reference' => $paymentData['payment_number'],
                'date' => $paymentData['payment_date'],
                'metadata' => [
                    'payment_method' => $paymentMethod,
                    'entity_id' => $paymentData['entity_id'],
                    'entity_type' => $paymentData['entity_type'] ?? 'customer',
                ],
            ]);

            // Emit ledger events for audit trail
            foreach ($journalEntries as $entry) {
                Event::dispatch(new LedgerEntryCreated([
                    'journal_entry_id' => $entry['id'],
                    'payment_id' => $paymentId,
                    'company_id' => $companyId,
                    'entry_type' => self::JOURNAL_PAYMENT,
                    'account_code' => $entry['account_code'],
                    'debit_amount' => $entry['debit_amount'],
                    'credit_amount' => $entry['credit_amount'],
                    'description' => $entry['description'],
                    'timestamp' => now()->toISOString(),
                    'metadata' => $entry['metadata'],
                ]));
            }

            return $journalEntries;
        });
    }

    /**
     * Create journal entries for payment allocations.
     */
    public function recordAllocation(PaymentAllocated $event): array
    {
        $allocationData = $event->getData();
        $paymentId = $allocationData['payment_id'];
        $allocatedAmount = $allocationData['allocated_amount'];
        $invoiceId = $allocationData['invoice_id'];
        $companyId = $allocationData['company_id'];

        return DB::transaction(function () use ($paymentId, $allocatedAmount, $invoiceId, $companyId, $allocationData) {
            $journalEntries = [];

            // Debit: Undeposited Funds (decrease - funds are now allocated)
            $journalEntries[] = $this->createJournalEntry([
                'payment_id' => $paymentId,
                'invoice_id' => $invoiceId,
                'company_id' => $companyId,
                'entry_type' => self::JOURNAL_ALLOCATION,
                'account_code' => self::UNDEPOSITED_FUNDS,
                'debit_amount' => $allocatedAmount,
                'credit_amount' => 0,
                'description' => "Payment allocated to invoice #{$allocationData['invoice_number']}",
                'reference' => $allocationData['payment_number'],
                'date' => $allocationData['allocation_date'],
                'metadata' => [
                    'invoice_id' => $invoiceId,
                    'invoice_number' => $allocationData['invoice_number'],
                    'allocation_method' => $allocationData['allocation_method'],
                    'entity_id' => $allocationData['entity_id'],
                ],
            ]);

            // Credit: Accounts Receivable (decrease - invoice is paid)
            $journalEntries[] = $this->createJournalEntry([
                'payment_id' => $paymentId,
                'invoice_id' => $invoiceId,
                'company_id' => $companyId,
                'entry_type' => self::JOURNAL_ALLOCATION,
                'account_code' => self::ACCOUNTS_RECEIVABLE,
                'debit_amount' => 0,
                'credit_amount' => $allocatedAmount,
                'description' => "Invoice #{$allocationData['invoice_number']} payment applied",
                'reference' => $allocationData['invoice_number'],
                'date' => $allocationData['allocation_date'],
                'metadata' => [
                    'invoice_id' => $invoiceId,
                    'invoice_number' => $allocationData['invoice_number'],
                    'allocation_method' => $allocationData['allocation_method'],
                    'entity_id' => $allocationData['entity_id'],
                ],
            ]);

            // Emit ledger events
            foreach ($journalEntries as $entry) {
                Event::dispatch(new LedgerEntryCreated([
                    'journal_entry_id' => $entry['id'],
                    'payment_id' => $paymentId,
                    'invoice_id' => $invoiceId,
                    'company_id' => $companyId,
                    'entry_type' => self::JOURNAL_ALLOCATION,
                    'account_code' => $entry['account_code'],
                    'debit_amount' => $entry['debit_amount'],
                    'credit_amount' => $entry['credit_amount'],
                    'description' => $entry['description'],
                    'timestamp' => now()->toISOString(),
                    'metadata' => $entry['metadata'],
                ]));
            }

            return $journalEntries;
        });
    }

    /**
     * Create journal entries for payment reversals.
     */
    public function recordPaymentReversal(PaymentReversed $event): array
    {
        $reversalData = $event->getData();
        $paymentId = $reversalData['payment_id'];
        $reversedAmount = $reversalData['reversed_amount'];
        $reversalMethod = $reversalData['reversal_method'];
        $reason = $reversalData['reason'];
        $companyId = $reversalData['company_id'];

        return DB::transaction(function () use ($paymentId, $reversedAmount, $reversalMethod, $reason, $companyId, $reversalData) {
            $journalEntries = [];

            // Get the original payment method to determine accounts
            $originalPaymentMethod = $reversalData['payment_method'];
            $cashAccount = $this->getCashAccountForPaymentMethod($originalPaymentMethod);
            $arAccount = $this->shouldUseUndepositedFunds($originalPaymentMethod) 
                ? self::UNDEPOSITED_FUNDS 
                : self::ACCOUNTS_RECEIVABLE;

            // Credit: Cash/Bank Account (decrease assets - money returned/refunded)
            $journalEntries[] = $this->createJournalEntry([
                'payment_id' => $paymentId,
                'company_id' => $companyId,
                'entry_type' => self::JOURNAL_REVERSAL,
                'account_code' => $cashAccount,
                'debit_amount' => 0,
                'credit_amount' => $reversedAmount,
                'description' => "Payment reversal - {$reversalData['payment_number']} ({$reversalMethod})",
                'reference' => $reversalData['payment_number'] . '-R',
                'date' => now()->toDateString(),
                'metadata' => [
                    'reversal_method' => $reversalMethod,
                    'reversal_reason' => $reason,
                    'original_payment_method' => $originalPaymentMethod,
                    'entity_id' => $reversalData['entity_id'],
                    'reversal_id' => $reversalData['reversal_id'],
                ],
            ]);

            // Debit: Accounts Receivable or Undeposited Funds (increase - payment is cancelled)
            $journalEntries[] = $this->createJournalEntry([
                'payment_id' => $paymentId,
                'company_id' => $companyId,
                'entry_type' => self::JOURNAL_REVERSAL,
                'account_code' => $arAccount,
                'debit_amount' => $reversedAmount,
                'credit_amount' => 0,
                'description' => "Payment reversal applied - {$reversalData['payment_number']}",
                'reference' => $reversalData['payment_number'] . '-R',
                'date' => now()->toDateString(),
                'metadata' => [
                    'reversal_method' => $reversalMethod,
                    'reversal_reason' => $reason,
                    'original_payment_method' => $originalPaymentMethod,
                    'entity_id' => $reversalData['entity_id'],
                    'reversal_id' => $reversalData['reversal_id'],
                ],
            ]);

            // Additional entry for chargebacks (creates liability)
            if ($reversalMethod === 'chargeback') {
                $journalEntries[] = $this->createJournalEntry([
                    'payment_id' => $paymentId,
                    'company_id' => $companyId,
                    'entry_type' => self::JOURNAL_REVERSAL,
                    'account_code' => '2100', // Credit Card Payable
                    'debit_amount' => 0,
                    'credit_amount' => $reversedAmount,
                    'description' => "Chargeback liability - {$reversalData['payment_number']}",
                    'reference' => $reversalData['payment_number'] . '-CB',
                    'date' => now()->toDateString(),
                    'metadata' => [
                        'reversal_method' => $reversalMethod,
                        'reversal_reason' => $reason,
                        'original_payment_method' => $originalPaymentMethod,
                        'entity_id' => $reversalData['entity_id'],
                        'reversal_id' => $reversalData['reversal_id'],
                        'is_liability' => true,
                    ],
                ]);
            }

            // Emit ledger events
            foreach ($journalEntries as $entry) {
                Event::dispatch(new LedgerEntryCreated([
                    'journal_entry_id' => $entry['id'],
                    'payment_id' => $paymentId,
                    'company_id' => $companyId,
                    'entry_type' => self::JOURNAL_REVERSAL,
                    'account_code' => $entry['account_code'],
                    'debit_amount' => $entry['debit_amount'],
                    'credit_amount' => $entry['credit_amount'],
                    'description' => $entry['description'],
                    'timestamp' => now()->toISOString(),
                    'metadata' => $entry['metadata'],
                ]));
            }

            return $journalEntries;
        });
    }

    /**
     * Create journal entries for allocation reversals.
     */
    public function recordAllocationReversal(AllocationReversed $event): array
    {
        $reversalData = $event->getData();
        $allocationId = $reversalData['allocation_id'];
        $refundAmount = $reversalData['refund_amount'];
        $originalAmount = $reversalData['original_amount'];
        $reason = $reversalData['reason'];
        $companyId = $reversalData['company_id'];

        return DB::transaction(function () use ($allocationId, $refundAmount, $originalAmount, $reason, $companyId, $reversalData) {
            $journalEntries = [];

            // Credit: Undeposited Funds (increase - funds are now unallocated)
            $journalEntries[] = $this->createJournalEntry([
                'allocation_id' => $allocationId,
                'company_id' => $companyId,
                'entry_type' => self::JOURNAL_ALLOCATION_REVERSAL,
                'account_code' => self::UNDEPOSITED_FUNDS,
                'debit_amount' => 0,
                'credit_amount' => $refundAmount,
                'description' => "Allocation reversal - Invoice #{$reversalData['invoice_number']}",
                'reference' => $reversalData['payment_number'] . '-AR',
                'date' => now()->toDateString(),
                'metadata' => [
                    'original_amount' => $originalAmount,
                    'refund_amount' => $refundAmount,
                    'reversal_reason' => $reason,
                    'invoice_id' => $reversalData['invoice_id'],
                    'invoice_number' => $reversalData['invoice_number'],
                    'payment_id' => $reversalData['payment_id'],
                    'entity_id' => $reversalData['entity_id'],
                ],
            ]);

            // Debit: Accounts Receivable (increase - invoice balance restored)
            $journalEntries[] = $this->createJournalEntry([
                'allocation_id' => $allocationId,
                'company_id' => $companyId,
                'entry_type' => self::JOURNAL_ALLOCATION_REVERSAL,
                'account_code' => self::ACCOUNTS_RECEIVABLE,
                'debit_amount' => $refundAmount,
                'credit_amount' => 0,
                'description' => "Invoice balance restored - #{$reversalData['invoice_number']}",
                'reference' => $reversalData['invoice_number'] . '-R',
                'date' => now()->toDateString(),
                'metadata' => [
                    'original_amount' => $originalAmount,
                    'refund_amount' => $refundAmount,
                    'reversal_reason' => $reason,
                    'invoice_id' => $reversalData['invoice_id'],
                    'invoice_number' => $reversalData['invoice_number'],
                    'payment_id' => $reversalData['payment_id'],
                    'entity_id' => $reversalData['entity_id'],
                ],
            ]);

            // Emit ledger events
            foreach ($journalEntries as $entry) {
                Event::dispatch(new LedgerEntryCreated([
                    'journal_entry_id' => $entry['id'],
                    'allocation_id' => $allocationId,
                    'company_id' => $companyId,
                    'entry_type' => self::JOURNAL_ALLOCATION_REVERSAL,
                    'account_code' => $entry['account_code'],
                    'debit_amount' => $entry['debit_amount'],
                    'credit_amount' => $entry['credit_amount'],
                    'description' => $entry['description'],
                    'timestamp' => now()->toISOString(),
                    'metadata' => $entry['metadata'],
                ]));
            }

            return $journalEntries;
        });
    }

    /**
     * Create a single journal entry.
     */
    private function createJournalEntry(array $data): array
    {
        $action = new CreateJournalEntryAction();
        return $action->execute($data);
    }

    /**
     * Get the appropriate cash account for payment method.
     */
    private function getCashAccountForPaymentMethod(string $paymentMethod): string
    {
        $cashAccounts = [
            'cash' => '1201', // Cash on Hand
            'bank_transfer' => '1210', // Bank Account - Checking
            'card' => '1220', // Bank Account - Card Processing
            'cheque' => '1230', // Bank Account - Cheques
            'other' => '1240', // Other Payment Methods
        ];

        return $cashAccounts[$paymentMethod] ?? self::CASH_ACCOUNT;
    }

    /**
     * Determine if payment should use undeposited funds account.
     */
    private function shouldUseUndepositedFunds(string $paymentMethod): bool
    {
        // Cash and cheques typically go to undeposited funds first
        return in_array($paymentMethod, ['cash', 'cheque']);
    }

    /**
     * Get payment method ledger account mapping.
     */
    public function getPaymentMethodAccountMapping(): array
    {
        return [
            'cash' => [
                'debit_account' => '1201', // Cash on Hand
                'credit_account' => self::UNDEPOSITED_FUNDS,
            ],
            'bank_transfer' => [
                'debit_account' => '1210', // Bank Account - Checking
                'credit_account' => self::ACCOUNTS_RECEIVABLE,
            ],
            'card' => [
                'debit_account' => '1220', // Bank Account - Card Processing
                'credit_account' => self::ACCOUNTS_RECEIVABLE,
            ],
            'cheque' => [
                'debit_account' => '1230', // Bank Account - Cheques
                'credit_account' => self::UNDEPOSITED_FUNDS,
            ],
            'other' => [
                'debit_account' => '1240', // Other Payment Methods
                'credit_account' => self::ACCOUNTS_RECEIVABLE,
            ],
        ];
    }

    /**
     * Calculate ledger balance for payment reconciliation.
     */
    public function calculatePaymentLedgerBalance(string $paymentId): array
    {
        $sql = "
            SELECT 
                account_code,
                SUM(debit_amount) as total_debits,
                SUM(credit_amount) as total_credits,
                SUM(debit_amount - credit_amount) as net_balance
            FROM accounting.journal_entries 
            WHERE payment_id = ? 
            GROUP BY account_code
            ORDER BY account_code
        ";

        $results = DB::select($sql, [$paymentId]);

        return [
            'payment_id' => $paymentId,
            'account_balances' => collect($results)->keyBy('account_code')->toArray(),
            'total_debits' => collect($results)->sum('total_debits'),
            'total_credits' => collect($results)->sum('total_credits'),
            'is_balanced' => collect($results)->sum('total_debits') === collect($results)->sum('total_credits'),
        ];
    }

    /**
     * Get payment reconciliation summary from ledger entries.
     */
    public function getPaymentReconciliationSummary(string $paymentId): array
    {
        $ledgerBalance = $this->calculatePaymentLedgerBalance($paymentId);
        
        // Get associated journal entries with details
        $entries = DB::table('accounting.journal_entries')
            ->where('payment_id', $paymentId)
            ->orderBy('created_at')
            ->get()
            ->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'entry_type' => $entry->entry_type,
                    'account_code' => $entry->account_code,
                    'debit_amount' => $entry->debit_amount,
                    'credit_amount' => $entry->credit_amount,
                    'description' => $entry->description,
                    'date' => $entry->date,
                    'created_at' => $entry->created_at,
                    'metadata' => json_decode($entry->metadata, true),
                ];
            })
            ->toArray();

        return [
            'payment_id' => $paymentId,
            'ledger_balance' => $ledgerBalance,
            'journal_entries' => $entries,
            'reconciliation_status' => $ledgerBalance['is_balanced'] ? 'balanced' : 'unbalanced',
            'reconciliation_date' => now()->toDateString(),
        ];
    }
}