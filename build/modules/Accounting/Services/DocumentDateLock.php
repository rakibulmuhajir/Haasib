<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\AccountingPeriod;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * The one place a document (bill, invoice, ...) checks whether its own date is
 * still open for editing. Two independent things can lock a date:
 *
 *  - this module's own accounting period / fiscal year being closed, and
 *  - a per-module "day lock", e.g. FuelStation's daily close, which this core
 *    service must never import directly.
 *
 * A module registers its own lock check with extend() from its service
 * provider's boot(); this class stays free of any module-specific class.
 */
class DocumentDateLock
{
    /** @var array<int, callable(string, string): ?string> */
    private static array $resolvers = [];

    /**
     * Register a resolver: given (companyId, date) it returns a lock reason,
     * or null when that module has no objection to the date.
     */
    public static function extend(callable $resolver): void
    {
        self::$resolvers[] = $resolver;
    }

    /**
     * Test-only: drop every registered resolver so a suite can start clean.
     */
    public static function flushResolvers(): void
    {
        self::$resolvers = [];
    }

    public function assertOpen(string $companyId, string $date, string $what = 'This bill'): void
    {
        $reason = $this->reason($companyId, $date, $what);

        if ($reason !== null) {
            throw ValidationException::withMessages(['date' => $reason]);
        }
    }

    /**
     * Non-throwing form for the UI: null means the date is editable.
     */
    public function reason(string $companyId, string $date, string $what = 'This bill'): ?string
    {
        $dateObj = Carbon::parse($date);

        $period = AccountingPeriod::join('acct.fiscal_years', 'acct.accounting_periods.fiscal_year_id', '=', 'acct.fiscal_years.id')
            ->where('acct.accounting_periods.company_id', $companyId)
            ->where('acct.accounting_periods.start_date', '<=', $dateObj->toDateString())
            ->where('acct.accounting_periods.end_date', '>=', $dateObj->toDateString())
            ->select('acct.accounting_periods.*', 'acct.fiscal_years.is_closed as fiscal_year_is_closed')
            ->first();

        if ($period && ($period->is_closed || $period->fiscal_year_is_closed)) {
            return "{$what} falls in a closed accounting period. Reopen the period to edit it.";
        }

        foreach (self::$resolvers as $resolver) {
            $reason = $resolver($companyId, $dateObj->toDateString());
            if ($reason !== null) {
                return $reason;
            }
        }

        return null;
    }
}
