<?php

namespace Modules\Accounting\Services;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LedgerService
{
    /**
     * Create a draft journal entry with optional transactions.
     */
    public function createJournalEntry(array $entryData, array $transactions = []): JournalEntry
    {
        $validator = Validator::make($entryData, [
            'company_id' => ['required', 'uuid'],
            'reference' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'date' => ['required', 'date'],
            'type' => ['required', 'string', 'max:50'],
            'status' => ['sometimes', 'string'],
            'currency' => ['required', 'string', 'max:3'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return DB::transaction(function () use ($validator, $transactions) {
            $entry = JournalEntry::create(array_merge([
                'status' => 'draft',
            ], $validator->validated()));

            foreach ($transactions as $transaction) {
                $this->addTransaction($entry, $transaction);
            }

            return $entry->fresh(['transactions']);
        });
    }

    /**
     * Attach a transaction to a journal entry.
     */
    public function addTransaction(JournalEntry $entry, array $transactionData): JournalTransaction
    {
        $validator = Validator::make($transactionData, [
            'account_id' => ['required', 'uuid'],
            'debit_credit' => ['required', 'in:debit,credit'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:3'],
            'description' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $entry->transactions()->create($validator->validated());
    }

    /**
     * Post a journal entry if it balances.
     */
    public function postJournalEntry(JournalEntry $entry): JournalEntry
    {
        $debits = $entry->transactions()->where('debit_credit', 'debit')->sum('amount');
        $credits = $entry->transactions()->where('debit_credit', 'credit')->sum('amount');

        if (abs($debits - $credits) > 0.01) {
            throw new \RuntimeException('Journal entry must balance before posting.');
        }

        $entry->forceFill([
            'status' => 'posted',
            'posted_at' => now(),
        ])->save();

        return $entry->fresh();
    }

    /**
     * Calculate the running balance for an account.
     */
    public function calculateAccountBalance(ChartOfAccount $account, ?string $currency = null): float
    {
        $query = $account->journalTransactions();

        if ($currency) {
            $query->where('currency', $currency);
        }

        $debitsQuery = clone $query;
        $creditsQuery = clone $query;

        $debits = (float) $debitsQuery->where('debit_credit', 'debit')->sum('amount');
        $credits = (float) $creditsQuery->where('debit_credit', 'credit')->sum('amount');

        return $account->normal_balance === 'debit'
            ? $account->opening_balance + $debits - $credits
            : $account->opening_balance + $credits - $debits;
    }

    /**
     * Retrieve a trial balance for the supplied company.
     */
    public function getTrialBalance(string $companyId): array
    {
        $accounts = ChartOfAccount::where('company_id', $companyId)
            ->with(['journalTransactions' => fn ($q) => $q->select('account_id', 'debit_credit', 'amount')])
            ->get();

        return $accounts->map(function (ChartOfAccount $account) {
            $debits = $account->journalTransactions->where('debit_credit', 'debit')->sum('amount');
            $credits = $account->journalTransactions->where('debit_credit', 'credit')->sum('amount');

            return [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'normal_balance' => $account->normal_balance,
                'total_debits' => $debits,
                'total_credits' => $credits,
                'balance' => $account->normal_balance === 'debit' ? $debits - $credits : $credits - $debits,
            ];
        })->all();
    }

    /**
     * Close an accounting period (basic lock without generating entries).
     */
    public function closePeriod(AccountingPeriod $period): void
    {
        $period->forceFill([
            'status' => 'closed',
            'closed_at' => now(),
        ])->save();
    }

    /**
     * Validate journal entry data for manual journals.
     */
    public function validateManualJournalEntry(array $data): array
    {
        $validator = Validator::make($data, [
            'company_id' => 'required|uuid|exists:auth.companies,id',
            'description' => 'required|string|max:500',
            'date' => 'required|date|before_or_equal:today',
            'type' => 'required|string|in:sales,purchase,payment,receipt,adjustment,closing,opening,reversal,automation',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|uuid|exists:acct.accounts,id',
            'lines.*.debit_credit' => 'required|string|in:debit,credit',
            'lines.*.amount' => 'required|numeric|min:0.01',
            'lines.*.description' => 'nullable|string|max:255',
            'currency' => 'required|string|max:3',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'attachment_url' => 'nullable|url|max:2048',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        // Validate business rules
        $this->validateJournalBalance($validated['lines']);
        $this->validatePeriodConstraints($validated['company_id'], $validated['date']);
        $this->validateAccountPermissions($validated['company_id'], $validated['lines']);

        return $validated;
    }

    /**
     * Validate that journal entry balances (debits = credits).
     */
    private function validateJournalBalance(array $lines): void
    {
        $totalDebits = collect($lines)
            ->where('debit_credit', 'debit')
            ->sum('amount');

        $totalCredits = collect($lines)
            ->where('debit_credit', 'credit')
            ->sum('amount');

        if (abs($totalDebits - $totalCredits) > 0.01) {
            throw ValidationException::withMessages([
                'lines' => 'Journal entry must balance. Total debits must equal total credits.',
            ]);
        }

        if ($totalDebits <= 0 || $totalCredits <= 0) {
            throw ValidationException::withMessages([
                'lines' => 'Journal entry must have both debit and credit lines.',
            ]);
        }
    }

    /**
     * Validate period constraints for journal entry date.
     */
    private function validatePeriodConstraints(string $companyId, string $date): void
    {
        $journalDate = Carbon::parse($date);

        // Check if date is in a closed period
        $closedPeriod = AccountingPeriod::where('company_id', $companyId)
            ->where('start_date', '<=', $journalDate)
            ->where('end_date', '>=', $journalDate)
            ->where('status', 'closed')
            ->first();

        if ($closedPeriod) {
            throw ValidationException::withMessages([
                'date' => "Cannot create journal entry for closed period: {$closedPeriod->name}",
            ]);
        }

        // Check if date is too far in the past (configurable)
        $maxDaysBack = config('accounting.max_journal_entry_age_days', 365);
        $cutoffDate = now()->subDays($maxDaysBack);

        if ($journalDate->lt($cutoffDate)) {
            throw ValidationException::withMessages([
                'date' => "Journal entry date cannot be more than {$maxDaysBack} days in the past.",
            ]);
        }
    }

    /**
     * Validate that accounts can be used for manual entries.
     */
    private function validateAccountPermissions(string $companyId, array $lines): void
    {
        $accountIds = collect($lines)->pluck('account_id')->unique();

        $accounts = Account::whereIn('id', $accountIds)
            ->where('company_id', $companyId)
            ->get();

        if ($accounts->count() !== $accountIds->count()) {
            throw ValidationException::withMessages([
                'lines' => 'One or more accounts not found or do not belong to the specified company.',
            ]);
        }

        foreach ($accounts as $account) {
            if (! $account->allow_manual_entries) {
                throw ValidationException::withMessages([
                    'lines' => "Account {$account->code} - {$account->name} does not allow manual entries.",
                ]);
            }

            if (! $account->active) {
                throw ValidationException::withMessages([
                    'lines' => "Account {$account->code} - {$account->name} is inactive.",
                ]);
            }
        }
    }

    /**
     * Create a reversal journal entry for an existing entry.
     */
    public function createReversalEntry(JournalEntry $originalEntry, string $reversalDate, ?string $reason = null): JournalEntry
    {
        if ($originalEntry->status !== 'posted') {
            throw new \RuntimeException('Only posted journal entries can be reversed.');
        }

        if ($originalEntry->reversal_of_id) {
            throw new \RuntimeException('This entry is already a reversal and cannot be reversed again.');
        }

        // Check if already reversed
        $existingReversal = JournalEntry::where('reversal_of_id', $originalEntry->id)->first();
        if ($existingReversal) {
            throw new \RuntimeException('This entry has already been reversed.');
        }

        $reversalDate = Carbon::parse($reversalDate);
        $this->validatePeriodConstraints($originalEntry->company_id, $reversalDate);

        return DB::transaction(function () use ($originalEntry, $reversalDate, $reason) {
            // Create reversal entry
            $reversalEntry = JournalEntry::create([
                'company_id' => $originalEntry->company_id,
                'description' => $reason ?? "Reversal of: {$originalEntry->description}",
                'date' => $reversalDate,
                'type' => 'reversal',
                'status' => 'draft',
                'currency' => $originalEntry->currency,
                'reference' => $originalEntry->reference ? 'REV-'.$originalEntry->reference : null,
                'reversal_of_id' => $originalEntry->id,
                'notes' => "Reverses journal entry #{$originalEntry->id} dated {$originalEntry->date}",
            ]);

            // Create reversed transactions
            foreach ($originalEntry->transactions as $transaction) {
                $reversalEntry->transactions()->create([
                    'account_id' => $transaction->account_id,
                    'debit_credit' => $transaction->debit_credit === 'debit' ? 'credit' : 'debit',
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'description' => 'Reversal: '.($transaction->description ?? $originalEntry->description),
                ]);
            }

            return $reversalEntry->fresh(['transactions']);
        });
    }

    /**
     * Link reversal relationships between journal entries.
     */
    public function linkReversalRelationship(JournalEntry $originalEntry, JournalEntry $reversalEntry): void
    {
        if ($originalEntry->id === $reversalEntry->id) {
            throw new \RuntimeException('Cannot link an entry to itself as a reversal.');
        }

        DB::transaction(function () use ($originalEntry, $reversalEntry) {
            $originalEntry->update(['reversed_by_id' => $reversalEntry->id]);
            $reversalEntry->update(['reversal_of_id' => $originalEntry->id]);
        });
    }

    /**
     * Update account balances when posting a journal entry.
     */
    public function updateAccountBalances(JournalEntry $journalEntry): void
    {
        foreach ($journalEntry->transactions as $transaction) {
            $account = $transaction->account;
            $currentBalance = $account->current_balance ?? 0;
            $change = $transaction->amount;

            if ($account->normal_balance === 'debit') {
                $newBalance = $transaction->debit_credit === 'debit'
                    ? $currentBalance + $change
                    : $currentBalance - $change;
            } else {
                $newBalance = $transaction->debit_credit === 'credit'
                    ? $currentBalance + $change
                    : $currentBalance - $change;
            }

            $account->update([
                'current_balance' => $newBalance,
                'last_updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse account balances when voiding or reversing a journal entry.
     */
    public function reverseAccountBalances(JournalEntry $journalEntry): void
    {
        foreach ($journalEntry->transactions as $transaction) {
            $account = $transaction->account;
            $currentBalance = $account->current_balance ?? 0;
            $change = $transaction->amount;

            if ($account->normal_balance === 'debit') {
                $newBalance = $transaction->debit_credit === 'debit'
                    ? $currentBalance - $change
                    : $currentBalance + $change;
            } else {
                $newBalance = $transaction->debit_credit === 'credit'
                    ? $currentBalance - $change
                    : $currentBalance + $change;
            }

            $account->update([
                'current_balance' => $newBalance,
                'last_updated_at' => now(),
            ]);
        }
    }

    /**
     * Validate journal entry status transitions.
     */
    public function validateStatusTransition(JournalEntry $journalEntry, string $newStatus): void
    {
        $validTransitions = [
            'draft' => ['submitted', 'void'],
            'submitted' => ['approved', 'rejected', 'void'],
            'approved' => ['posted', 'void'],
            'posted' => ['void'],
            'void' => [], // Terminal state
            'rejected' => ['draft', 'void'],
        ];

        $currentStatus = $journalEntry->status;

        if (! isset($validTransitions[$currentStatus]) || ! in_array($newStatus, $validTransitions[$currentStatus])) {
            throw ValidationException::withMessages([
                'status' => "Invalid status transition from {$currentStatus} to {$newStatus}",
            ]);
        }
    }

    /**
     * Check if a journal entry can be modified (not posted or void).
     */
    public function canModifyEntry(JournalEntry $journalEntry): bool
    {
        return in_array($journalEntry->status, ['draft', 'submitted', 'rejected']);
    }

    /**
     * Check if a journal entry can be posted.
     */
    public function canPostEntry(JournalEntry $journalEntry): bool
    {
        return $journalEntry->status === 'approved' && $this->entryBalances($journalEntry);
    }

    /**
     * Check if a journal entry balances.
     */
    private function entryBalances(JournalEntry $journalEntry): bool
    {
        $debits = $journalEntry->transactions()->where('debit_credit', 'debit')->sum('amount');
        $credits = $journalEntry->transactions()->where('debit_credit', 'credit')->sum('amount');

        return abs($debits - $credits) < 0.01;
    }
}
