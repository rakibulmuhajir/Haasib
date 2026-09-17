<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/** Retry the complete command, never just the failed SQL statement or a partial posting. */
class AccountingWriteTransaction
{
    public static function run(callable $work): mixed
    {
        if (DB::transactionLevel() > 0) {
            return DB::transaction($work); // Preserve savepoints; the outer owner retries concurrency failures.
        }
        for ($attempt = 0; ; $attempt++) {
            try {
                return DB::transaction($work);
            } catch (\Throwable $exception) {
                $cause = $exception;
                while ($cause->getPrevious() && !($cause instanceof QueryException)) { $cause = $cause->getPrevious(); }
                $state = $cause instanceof QueryException ? ($cause->errorInfo[0] ?? (string) $cause->getCode()) : '';
                // 23505 (unique_violation) is included alongside the two serialization codes
                // because every *Number() sequence in this codebase (Bill, BillPayment, ...)
                // generates its next value from `SELECT ... FOR UPDATE ORDER BY ... DESC` on
                // the existing rows: when there is no row yet to lock (the very first document
                // for a company), two concurrent commands can compute the same next number and
                // only one insert wins. Retrying the whole command re-reads the now-committed
                // sibling row and computes a fresh number, which is the same recovery a
                // genuine 40001 gets — never a partial posting. A real duplicate the caller
                // supplied by hand (not sequence-generated) fails identically either way, just
                // after this retry budget is spent.
                if (!in_array($state, ['40001', '40P01', '23505'], true) || $attempt >= 9) {
                    throw $exception;
                }
                // Observability only (no behavior change): lets concurrency tests count
                // retries per worker process without instrumenting every call site.
                \Illuminate\Support\Facades\Log::info('accounting_write_retry', ['pid' => getmypid(), 'attempt' => $attempt + 1, 'sqlstate' => $state]);
                usleep(min(500000, 50000 * ($attempt + 1)));
            }
        }
    }
}
