<?php

namespace App\Modules\FuelStation\Services;

use App\Models\User;
use App\Modules\Accounting\Models\Transaction;

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
            ->whereNull('deleted_at')
            ->update([
                'is_locked' => true,
                'locked_at' => now(),
                'locked_by_user_id' => $userId,
                'lock_reason' => 'month_end',
            ]);
    }
}
