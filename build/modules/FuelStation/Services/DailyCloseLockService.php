<?php

namespace App\Modules\FuelStation\Services;

use App\Models\User;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Models\DailyCloseUnlock;
use Illuminate\Support\Facades\DB;

/**
 * Period-end locking for Daily Closes, independent of any amendment/reversal
 * mechanism. Every close is now a snapshot close (see docs/contracts/fuel-schema.md,
 * "Legacy Daily Close amendment removed"); a locked close simply refuses month-end
 * mutation, it does not need or support being reversed and re-posted.
 */
class DailyCloseLockService
{
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
     *
     * Transaction::unlock() clears the lock columns, so the lock being replaced is captured
     * into fuel.daily_close_unlocks first. That table is append-only and survives relocking,
     * which the columns on the transaction cannot: the audit question is the sequence of
     * reopenings, not the most recent one.
     */
    public function unlockTransaction(Transaction $transaction, User $user, string $reason): void
    {
        if (!$transaction->is_locked) {
            throw new \RuntimeException('This transaction is not locked.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('A reason is required to reopen a locked daily close.');
        }

        DB::transaction(function () use ($transaction, $user, $reason) {
            DailyCloseUnlock::create([
                'company_id' => $transaction->company_id,
                'close_transaction_id' => $transaction->id,
                'unlocked_at' => now(),
                'unlocked_by_user_id' => $user->id,
                'reason' => $reason,
                'previously_locked_at' => $transaction->locked_at,
                'previously_locked_by_user_id' => $transaction->locked_by_user_id,
                'previous_lock_reason' => $transaction->lock_reason,
            ]);

            $transaction->unlock();
        });
    }

    /**
     * Every reopening of this close, oldest first.
     */
    public function unlockHistory(Transaction $transaction)
    {
        return DailyCloseUnlock::with(['unlockedBy:id,name'])
            ->where('company_id', $transaction->company_id)
            ->where('close_transaction_id', $transaction->id)
            ->orderBy('unlocked_at')
            ->get();
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
            ->whereNull('deleted_at')
            ->update([
                'is_locked' => true,
                'locked_at' => now(),
                'locked_by_user_id' => $userId,
                'lock_reason' => 'month_end',
            ]);
    }
}
