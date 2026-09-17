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
                if (!in_array($state, ['40001', '40P01'], true) || $attempt >= 9) {
                    throw $exception;
                }
                usleep(min(500000, 50000 * ($attempt + 1)));
            }
        }
    }
}
