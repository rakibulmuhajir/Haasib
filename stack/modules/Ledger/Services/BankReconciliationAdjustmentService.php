<?php

namespace Modules\Ledger\Services;

use App\Models\BankReconciliation;
use App\Models\BankReconciliationAdjustment;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BankReconciliationAdjustmentService
{
    private array $defaultAccounts = [
        'bank_fee' => [
            'debit_account' => null, // Will be determined by company configuration
            'credit_account' => null, // Will be the bank account being reconciled
        ],
        'interest' => [
            'debit_account' => null, // Will be the bank account being reconciled
            'credit_account' => null, // Will be determined by company configuration
        ],
        'write_off' => [
            'debit_account' => null, // Will be determined by company configuration
            'credit_account' => null, // Will be the bank account being reconciled
        ],
        'timing' => [
            'debit_account' => null, // Will be determined by transaction type
            'credit_account' => null, // Will be the bank account being reconciled
        ],
    ];

    public function createAdjustment(
        BankReconciliation $reconciliation,
        string $adjustmentType,
        float $amount,
        string $description,
        User $user,
        ?string $statementLineId = null,
        bool $postJournalEntry = true
    ): BankReconciliationAdjustment {
        return DB::transaction(function () use ($reconciliation, $adjustmentType, $amount, $description, $user, $statementLineId, $postJournalEntry) {
            $adjustment = $this->createAdjustmentRecord(
                $reconciliation,
                $adjustmentType,
                $amount,
                $description,
                $user,
                $statementLineId,
                null // journal_entry_id will be set below if needed
            );

            if ($postJournalEntry) {
                $journalEntry = $this->createJournalEntry(
                    $reconciliation,
                    $adjustmentType,
                    $amount,
                    $description,
                    $user
                );

                $adjustment->update(['journal_entry_id' => $journalEntry->id]);
            }

            // Update reconciliation variance
            $reconciliation->recalculateVariance();

            return $adjustment;
        });
    }

    public function createWithExistingJournalEntry(
        BankReconciliation $reconciliation,
        string $adjustmentType,
        float $amount,
        string $description,
        User $user,
        ?string $statementLineId,
        string $journalEntryId
    ): BankReconciliationAdjustment {
        return DB::transaction(function () use ($reconciliation, $adjustmentType, $amount, $description, $user, $statementLineId, $journalEntryId) {
            // Validate that the journal entry exists and belongs to the company
            $journalEntry = JournalEntry::where('id', $journalEntryId)
                ->where('company_id', $reconciliation->company_id)
                ->firstOrFail();

            $adjustment = $this->createAdjustmentRecord(
                $reconciliation,
                $adjustmentType,
                $amount,
                $description,
                $user,
                $statementLineId,
                $journalEntryId
            );

            // Update reconciliation variance
            $reconciliation->recalculateVariance();

            return $adjustment;
        });
    }

    public function updateAdjustment(
        BankReconciliationAdjustment $adjustment,
        float $newAmount,
        string $newDescription,
        User $user
    ): BankReconciliationAdjustment {
        return DB::transaction(function () use ($adjustment, $newAmount, $newDescription) {
            $oldAmount = $adjustment->amount;

            $adjustment->update([
                'amount' => $newAmount,
                'description' => $newDescription,
            ]);

            // Update journal entry if it exists
            if ($adjustment->journal_entry_id) {
                $this->updateJournalEntry(
                    $adjustment->journalEntry,
                    $adjustment->adjustment_type,
                    $oldAmount,
                    $newAmount,
                    $newDescription
                );
            }

            // Update reconciliation variance
            $adjustment->reconciliation->recalculateVariance();

            return $adjustment;
        });
    }

    public function deleteAdjustment(BankReconciliationAdjustment $adjustment, User $user): bool
    {
        return DB::transaction(function () use ($adjustment) {
            // Delete journal entry if it exists
            if ($adjustment->journal_entry_id) {
                $journalEntry = $adjustment->journalEntry;

                // Delete journal transactions first
                $journalEntry->transactions()->delete();

                // Delete journal entry
                $journalEntry->delete();
            }

            // Delete adjustment
            $result = $adjustment->delete();

            // Update reconciliation variance
            $adjustment->reconciliation->recalculateVariance();

            return $result;
        });
    }

    private function createAdjustmentRecord(
        BankReconciliation $reconciliation,
        string $adjustmentType,
        float $amount,
        string $description,
        User $user,
        ?string $statementLineId,
        ?string $journalEntryId
    ): BankReconciliationAdjustment {
        return BankReconciliationAdjustment::create([
            'reconciliation_id' => $reconciliation->id,
            'company_id' => $reconciliation->company_id,
            'statement_line_id' => $statementLineId,
            'adjustment_type' => $adjustmentType,
            'journal_entry_id' => $journalEntryId,
            'amount' => $this->applyAmountSign($adjustmentType, $amount),
            'description' => $description,
            'created_by' => $user->id,
        ]);
    }

    private function createJournalEntry(
        BankReconciliation $reconciliation,
        string $adjustmentType,
        float $amount,
        string $description,
        User $user
    ): JournalEntry {
        $journalEntry = JournalEntry::create([
            'company_id' => $reconciliation->company_id,
            'journal_date' => now(),
            'description' => $description,
            'reference' => "Bank Reconciliation Adjustment #{$reconciliation->id}",
            'created_by' => $user->id,
        ]);

        $accounts = $this->getJournalAccounts($reconciliation, $adjustmentType);
        $signedAmount = $this->applyAmountSign($adjustmentType, $amount);

        // Create debit transaction
        JournalTransaction::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $accounts['debit_account'],
            'debit_amount' => abs($signedAmount),
            'credit_amount' => 0,
            'description' => $description,
        ]);

        // Create credit transaction
        JournalTransaction::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $accounts['credit_account'],
            'debit_amount' => 0,
            'credit_amount' => abs($signedAmount),
            'description' => $description,
        ]);

        return $journalEntry;
    }

    private function updateJournalEntry(
        JournalEntry $journalEntry,
        string $adjustmentType,
        float $oldAmount,
        float $newAmount,
        string $newDescription
    ): void {
        $signedOldAmount = $this->applyAmountSign($adjustmentType, $oldAmount);
        $signedNewAmount = $this->applyAmountSign($adjustmentType, $newAmount);

        // Update journal entry description
        $journalEntry->update(['description' => $newDescription]);

        // Update transactions
        $transactions = $journalEntry->transactions()->get();

        foreach ($transactions as $transaction) {
            if ($transaction->debit_amount > 0) {
                // Debit transaction
                $transaction->update(['debit_amount' => abs($signedNewAmount)]);
            } else {
                // Credit transaction
                $transaction->update(['credit_amount' => abs($signedNewAmount)]);
            }
        }
    }

    private function getJournalAccounts(BankReconciliation $reconciliation, string $adjustmentType): array
    {
        $bankAccountId = $reconciliation->ledger_account_id;

        switch ($adjustmentType) {
            case 'bank_fee':
                return [
                    'debit_account' => $this->getBankFeeExpenseAccount($reconciliation->company_id),
                    'credit_account' => $bankAccountId,
                ];
            case 'interest':
                return [
                    'debit_account' => $bankAccountId,
                    'credit_account' => $this->getInterestIncomeAccount($reconciliation->company_id),
                ];
            case 'write_off':
                return [
                    'debit_account' => $this->getBadDebtExpenseAccount($reconciliation->company_id),
                    'credit_account' => $bankAccountId,
                ];
            case 'timing':
                return [
                    'debit_account' => $bankAccountId, // Typically a timing adjustment moves money back to bank
                    'credit_account' => $this->getTimingAdjustmentAccount($reconciliation->company_id),
                ];
            default:
                throw new \InvalidArgumentException("Unknown adjustment type: {$adjustmentType}");
        }
    }

    private function applyAmountSign(string $adjustmentType, float $amount): float
    {
        switch ($adjustmentType) {
            case 'bank_fee':
            case 'write_off':
                return -abs($amount); // Always negative
            case 'interest':
                return abs($amount); // Always positive
            case 'timing':
                return $amount; // Use the provided sign
            default:
                return $amount;
        }
    }

    private function getBankFeeExpenseAccount(string $companyId): string
    {
        // Try to find a bank fee expense account
        $account = ChartOfAccount::where('company_id', $companyId)
            ->where('account_type', 'expense')
            ->where(function ($query) {
                $query->where('account_subtype', 'bank_fee')
                    ->orWhere('name', 'ILIKE', '%bank fee%')
                    ->orWhere('name', 'ILIKE', '%bank charges%');
            })
            ->first();

        if ($account) {
            return $account->id;
        }

        // Create a default bank fee expense account
        return $this->createDefaultAccount($companyId, 'Bank Fees', 'expense', 'bank_fee');
    }

    private function getInterestIncomeAccount(string $companyId): string
    {
        // Try to find an interest income account
        $account = ChartOfAccount::where('company_id', $companyId)
            ->where('account_type', 'revenue')
            ->where(function ($query) {
                $query->where('account_subtype', 'interest_income')
                    ->orWhere('name', 'ILIKE', '%interest income%')
                    ->orWhere('name', 'ILIKE', '%interest earned%');
            })
            ->first();

        if ($account) {
            return $account->id;
        }

        // Create a default interest income account
        return $this->createDefaultAccount($companyId, 'Interest Income', 'revenue', 'interest_income');
    }

    private function getBadDebtExpenseAccount(string $companyId): string
    {
        // Try to find a bad debt expense account
        $account = ChartOfAccount::where('company_id', $companyId)
            ->where('account_type', 'expense')
            ->where(function ($query) {
                $query->where('account_subtype', 'bad_debt')
                    ->orWhere('name', 'ILIKE', '%bad debt%')
                    ->orWhere('name', 'ILIKE', '%write-off%');
            })
            ->first();

        if ($account) {
            return $account->id;
        }

        // Create a default bad debt expense account
        return $this->createDefaultAccount($companyId, 'Bad Debt Expense', 'expense', 'bad_debt');
    }

    private function getTimingAdjustmentAccount(string $companyId): string
    {
        // Try to find a timing adjustment account
        $account = ChartOfAccount::where('company_id', $companyId)
            ->where('account_type', 'asset') // Usually an asset clearing account
            ->where(function ($query) {
                $query->where('name', 'ILIKE', '%timing adjustment%')
                    ->orWhere('name', 'ILIKE', '%bank clearing%')
                    ->orWhere('name', 'ILIKE', '%suspense%');
            })
            ->first();

        if ($account) {
            return $account->id;
        }

        // Create a default timing adjustment account
        return $this->createDefaultAccount($companyId, 'Bank Timing Adjustments', 'asset', 'timing_adjustment');
    }

    private function createDefaultAccount(string $companyId, string $name, string $type, string $subtype): string
    {
        // Get the next account number
        $lastAccount = ChartOfAccount::where('company_id', $companyId)
            ->orderBy('account_number', 'desc')
            ->first();

        $accountNumber = $lastAccount ? $lastAccount->account_number + 1 : 5000;

        $account = ChartOfAccount::create([
            'company_id' => $companyId,
            'account_number' => $accountNumber,
            'name' => $name,
            'account_type' => $type,
            'account_subtype' => $subtype,
            'is_active' => true,
            'created_by' => 1, // System created
        ]);

        Log::info('Created default account for bank reconciliation adjustments', [
            'company_id' => $companyId,
            'account_id' => $account->id,
            'account_name' => $name,
            'account_type' => $type,
        ]);

        return $account->id;
    }
}
