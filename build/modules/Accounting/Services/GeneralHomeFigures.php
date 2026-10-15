<?php

namespace App\Modules\Accounting\Services;

use App\Models\Company;
use App\Modules\Accounting\Models\Bill;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The money figures on a general company's home page.
 *
 * Every figure agrees with the report it links to: profit comes from the P&L service, cash
 * from the same posted-ledger balances the balance sheet reads, and payables use the
 * payables aging report's own bill filter.
 */
class GeneralHomeFigures
{
    /** Same statuses the balance sheet counts as booked. */
    private const POSTED = ['posted', 'locked'];

    public function __construct(private readonly ProfitLossReportService $profitLoss) {}

    /**
     * @return array{cash_and_bank: float, ap_outstanding: float, ap_overdue: float, revenue_mtd: float, expenses_mtd: float, profit_mtd: float}
     */
    public function for(Company $company, Carbon $today): array
    {
        $totals = $this->profitLoss->run(
            $company->id,
            $today->copy()->startOfMonth()->toDateString(),
            $today->toDateString(),
        )['totals'];

        $openBills = Bill::where('company_id', $company->id)
            ->whereNotIn('status', ['draft', 'void', 'cancelled'])
            ->where('balance', '>', 0);

        return [
            'cash_and_bank' => $this->cashAndBank($company->id, $today),
            'ap_outstanding' => round((float) (clone $openBills)->sum('balance'), 2),
            'ap_overdue' => round((float) (clone $openBills)->whereDate('due_date', '<', $today->toDateString())->sum('balance'), 2),
            'revenue_mtd' => (float) $totals['income'],
            'expenses_mtd' => (float) $totals['expenses'],
            'profit_mtd' => (float) $totals['profit'],
        ];
    }

    /** Debit minus credit on active bank and cash accounts, as the balance sheet sums assets. */
    private function cashAndBank(string $companyId, Carbon $asOf): float
    {
        $row = DB::table('acct.journal_entries as je')
            ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
            ->join('acct.accounts as a', 'a.id', '=', 'je.account_id')
            ->where('t.company_id', $companyId)
            ->whereIn('t.status', self::POSTED)
            ->whereDate('t.transaction_date', '<=', $asOf->toDateString())
            ->where('a.type', 'asset')
            ->whereIn('a.subtype', ['bank', 'cash'])
            ->where('a.is_active', true)
            ->whereNull('a.deleted_at')
            ->selectRaw('COALESCE(SUM(je.debit_amount), 0) AS debit, COALESCE(SUM(je.credit_amount), 0) AS credit')
            ->first();

        return round((float) $row->debit - (float) $row->credit, 2);
    }
}
