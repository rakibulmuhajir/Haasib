<?php

namespace Modules\Accounting\Services;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalTransaction;
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
}
