<?php

namespace App\Modules\Accounting\Services;

use App\Models\Company;
use App\Modules\Accounting\Models\Bill;
use Illuminate\Support\Facades\DB;

/**
 * Next bill number, from the prefix and start number saved in onboarding.
 * Callers hold their own lock; existing bills are never renumbered.
 */
class BillNumberService
{
    public function next(string $companyId): string
    {
        // Serialise concurrent creators for this company until their transaction ends.
        if (DB::connection('pgsql')->transactionLevel() > 0) {
            DB::connection('pgsql')->select('SELECT pg_advisory_xact_lock(hashtext(?))', ['bill-number:'.$companyId]);
        }

        $company = Company::find($companyId);
        $prefix = $company?->bill_prefix ?: 'BILL-';

        // Highest by number, not by text: a hand-typed number must not reset the count.
        // Soft-deleted bills count too, as their numbers stay reserved by the unique index.
        $last = Bill::withTrashed()
            ->where('company_id', $companyId)
            ->whereRaw('left(bill_number, ?) = ?', [mb_strlen($prefix), $prefix])
            ->whereRaw("substring(bill_number from ?::integer) ~ '^[0-9]+$'", [mb_strlen($prefix) + 1])
            ->selectRaw('MAX(substring(bill_number from ?::integer)::numeric) AS max_number', [mb_strlen($prefix) + 1])
            ->value('max_number');

        // The saved start number only opens the sequence; once bills exist the count carries on.
        $next = $last !== null
            ? (int) $last + 1
            : max(1, (int) ($company?->bill_start_number ?? 1));

        return $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
