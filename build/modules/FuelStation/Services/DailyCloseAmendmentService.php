<?php

namespace App\Modules\FuelStation\Services;

use App\Models\User;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\GlPostingService;
use Illuminate\Support\Facades\DB;

class DailyCloseAmendmentService
{
    public function __construct(
        private readonly GlPostingService $postingService,
        private readonly DailyCloseService $dailyCloseService,
    ) {}

    /**
     * Amend a posted daily close by creating a reversal and correction entry.
     *
     * @param Transaction $original The original transaction to amend
     * @param array $newData The corrected data
     * @param User $user The user making the amendment
     * @param string $reason The reason for the amendment
     * @return array Result with transaction IDs
     */
    public function amendDailyClose(
        Transaction $original,
        array $newData,
        User $user,
        string $reason
    ): array {
        if (!$original->isAmendable()) {
            throw new \RuntimeException('This entry cannot be amended. It may be locked or already reversed.');
        }

        return DB::transaction(function () use ($original, $newData, $user, $reason) {
            $companyId = $original->company_id;
            $originalDate = $original->transaction_date->toDateString();

            // 1. Create reversal entry (mirror of original)
            $reversal = $this->createReversalEntry($original, $user, $reason);

            // 2. Mark original as reversed
            $original->update([
                'reversed_by_id' => $reversal->id,
                'amendment_reason' => $reason,
                'amended_at' => now(),
                'amended_by_user_id' => $user->id,
            ]);

            // 3. Create correction entry with new data
            // Force the date to match original, mark as correction
            $newData['date'] = $originalDate;
            $correction = $this->dailyCloseService->processDailyClose(
                $companyId,
                $newData,
                $user,
                isCorrection: true
            );

            // 4. Link correction to original
            Transaction::where('id', $correction['transaction_id'])
                ->update([
                    'corrects_transaction_id' => $original->id,
                    'amendment_reason' => $reason,
                ]);

            return [
                'original_id' => $original->id,
                'original_number' => $original->transaction_number,
                'reversal_id' => $reversal->id,
                'reversal_number' => $reversal->transaction_number,
                'correction_id' => $correction['transaction_id'],
                'correction_number' => $correction['transaction_number'],
            ];
        });
    }

    /**
     * Create a reversal entry that mirrors the original transaction.
     */
    private function createReversalEntry(Transaction $original, User $user, string $reason): Transaction
    {
        $reversalNumber = $original->transaction_number . '-REV';
        $reversalEntries = $this->buildReversalEntries($original);

        $originalMetadata = $original->metadata ?? [];
        if (!is_array($originalMetadata)) {
            $originalMetadata = [];
        }

        return $this->postingService->postBalancedTransaction([
            'company_id' => $original->company_id,
            'transaction_number' => $reversalNumber,
            'transaction_type' => 'fuel_daily_close_reversal',
            'date' => now()->toDateString(), // Reversal dated today
            'currency' => $original->currency,
            'base_currency' => $original->base_currency,
            'description' => "Reversal of {$original->transaction_number} - {$reason}",
            'reference_type' => 'fuel.daily_close_reversal',
            'reversal_of_id' => $original->id,
            'metadata' => [
                'reverses_date' => $original->transaction_date->toDateString(),
                'reason' => $reason,
                'original_transaction_number' => $original->transaction_number,
                'original_metadata' => $originalMetadata,
            ],
        ], $reversalEntries);
    }

    /**
     * Build reversal entries by flipping debits and credits.
     */
    private function buildReversalEntries(Transaction $original): array
    {
        $entries = [];

        foreach ($original->journalEntries as $entry) {
            $debitAmount = (float) ($entry->debit_amount ?? 0);
            $creditAmount = (float) ($entry->credit_amount ?? 0);

            // Flip: original debits become credits, original credits become debits
            if ($debitAmount > 0) {
                $entries[] = [
                    'account_id' => $entry->account_id,
                    'type' => 'credit',
                    'amount' => $debitAmount,
                    'description' => 'Reversal: ' . ($entry->description ?? ''),
                ];
            }

            if ($creditAmount > 0) {
                $entries[] = [
                    'account_id' => $entry->account_id,
                    'type' => 'debit',
                    'amount' => $creditAmount,
                    'description' => 'Reversal: ' . ($entry->description ?? ''),
                ];
            }
        }

        return $entries;
    }

    /**
     * Get the amendment chain for a transaction.
     * Returns all related transactions: original, reversals, and corrections.
     */
    public function getAmendmentChain(Transaction $transaction): array
    {
        $chain = [];

        // Find the root transaction (the original that started the chain)
        $root = $this->findRootTransaction($transaction);

        // Build the chain from root
        $this->buildChainFromRoot($root, $chain);

        return $chain;
    }

    /**
     * Find the root (original) transaction in an amendment chain.
     */
    private function findRootTransaction(Transaction $transaction): Transaction
    {
        // If this corrects another transaction, go up
        if ($transaction->corrects_transaction_id) {
            $corrected = Transaction::find($transaction->corrects_transaction_id);
            if ($corrected) {
                return $this->findRootTransaction($corrected);
            }
        }

        // If this is a reversal, go to the reversed transaction
        if ($transaction->reversal_of_id) {
            $reversed = Transaction::find($transaction->reversal_of_id);
            if ($reversed) {
                return $this->findRootTransaction($reversed);
            }
        }

        return $transaction;
    }

    /**
     * Build the amendment chain from a root transaction.
     */
    private function buildChainFromRoot(Transaction $root, array &$chain): void
    {
        $chain[] = [
            'id' => $root->id,
            'transaction_number' => $root->transaction_number,
            'transaction_date' => $root->transaction_date->toDateString(),
            'created_at' => $root->created_at->toDateTimeString(),
            'type' => 'original',
            'status' => $root->display_status,
            'metadata' => $root->metadata,
            'amendment_reason' => $root->amendment_reason,
        ];

        // Check if this was reversed
        if ($root->reversed_by_id) {
            $reversal = Transaction::find($root->reversed_by_id);
            if ($reversal) {
                $chain[] = [
                    'id' => $reversal->id,
                    'transaction_number' => $reversal->transaction_number,
                    'transaction_date' => $reversal->transaction_date->toDateString(),
                    'created_at' => $reversal->created_at->toDateTimeString(),
                    'type' => 'reversal',
                    'status' => 'reversal',
                    'metadata' => $reversal->metadata,
                    'amendment_reason' => null,
                ];
            }

            // Find the correction that replaces this
            $correction = Transaction::where('corrects_transaction_id', $root->id)->first();
            if ($correction) {
                // Recursively build from correction (in case it was also amended)
                $this->buildChainFromRoot($correction, $chain);
            }
        }
    }

    /**
     * Get the current (effective) transaction for a date.
     * This is the latest correction, or the original if never amended.
     */
    public function getEffectiveTransaction(string $companyId, string $date): ?Transaction
    {
        // Get all daily closes for this date
        $transactions = Transaction::where('company_id', $companyId)
            ->where('transaction_type', 'fuel_daily_close')
            ->whereDate('transaction_date', $date)
            ->whereNull('deleted_at')
            ->whereNull('reversed_by_id') // Not reversed
            ->orderByDesc('created_at')
            ->get();

        // Return the most recent non-reversed one
        return $transactions->first();
    }

    /**
     * Lock a daily close transaction.
     */
    public function lockTransaction(Transaction $transaction, User $user, string $reason = 'manual'): void
    {
        if (!$transaction->isLockable()) {
            throw new \RuntimeException('This transaction cannot be locked.');
        }

        $transaction->lock($user->id, $reason);
    }

    /**
     * Unlock a daily close transaction (owner only).
     */
    public function unlockTransaction(Transaction $transaction): void
    {
        if (!$transaction->is_locked) {
            throw new \RuntimeException('This transaction is not locked.');
        }

        $transaction->unlock();
    }

    /**
     * Lock all daily closes for a given month.
     */
    public function lockMonth(string $companyId, int $year, int $month, string $userId): int
    {
        $startDate = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        return Transaction::where('company_id', $companyId)
            ->where('transaction_type', 'fuel_daily_close')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->where('is_locked', false)
            ->whereNull('reversed_by_id')
            ->whereNull('deleted_at')
            ->update([
                'is_locked' => true,
                'locked_at' => now(),
                'locked_by_user_id' => $userId,
                'lock_reason' => 'month_end',
            ]);
    }
}
