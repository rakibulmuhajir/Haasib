<?php

namespace App\Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardService
{
    public function getCashPosition(string $companyId): array
    {
        $accounts = DB::table('acct.company_bank_accounts')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->select('account_name', 'current_balance', 'currency')
            ->get();

        $total = $accounts->sum('current_balance'); // Simplified: assumes same currency for total (V1)

        return [
            'total' => (float) $total,
            'accounts' => $accounts->map(fn($a) => [
                'name' => $a->account_name,
                'balance' => (float) $a->current_balance,
                'currency' => $a->currency
            ])->toArray()
        ];
    }

    public function getMoneyInOut(string $companyId): array
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth()->toDateString();
        $endOfMonth = $now->copy()->endOfMonth()->toDateString();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth()->toDateString();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth()->toDateString();

        // Use GL-based P&L so "Money In/Out" matches the Profit widget (avoids mixing cash vs accrual).
        $current = $this->calculatePLFromGL($companyId, $startOfMonth, $endOfMonth);
        $last = $this->calculatePLFromGL($companyId, $startOfLastMonth, $endOfLastMonth);

        $moneyInCurrent = (float) $current['income'];
        $moneyInLast = (float) $last['income'];
        $moneyOutCurrent = (float) $current['expenses'];
        $moneyOutLast = (float) $last['expenses'];

        return [
            'money_in' => [
                'current' => (float) $moneyInCurrent,
                'last' => (float) $moneyInLast,
                'growth' => $moneyInLast != 0.0
                    ? (($moneyInCurrent - $moneyInLast) / abs($moneyInLast)) * 100
                    : ($moneyInCurrent > 0 ? 100 : 0),
            ],
            'money_out' => [
                'current' => (float) $moneyOutCurrent,
                'last' => (float) $moneyOutLast,
                'growth' => $moneyOutLast != 0.0
                    ? (($moneyOutCurrent - $moneyOutLast) / abs($moneyOutLast)) * 100
                    : ($moneyOutCurrent > 0 ? 100 : 0),
            ]
        ];
    }

    public function getNeedsAttention(string $companyId): array
    {
        // 1. Overdue Invoices
        $overdueInvoices = DB::table('acct.invoices')
            ->where('company_id', $companyId)
            ->whereNotIn('status', ['draft', 'paid', 'void', 'cancelled'])
            ->where('due_date', '<', now()->toDateString())
            ->where('balance', '>', 0)
            ->count();

        // 2. Bills Due Soon (next 7 days)
        $billsDueSoonQuery = DB::table('acct.bills')
            ->where('company_id', $companyId)
            ->whereNotIn('status', ['draft', 'paid', 'void', 'cancelled'])
            ->whereBetween('due_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
            ->where('balance', '>', 0);

        $billsDueSoonCount = (int) $billsDueSoonQuery->count();
        $billsDueSoonAmount = (float) $billsDueSoonQuery->sum('balance');

        // 3. Unreconciled Bank Transactions
        $unreconciled = DB::table('acct.bank_transactions')
            ->where('company_id', $companyId)
            ->where('is_reconciled', false)
            ->count();

        return [
            'overdue_invoices' => $overdueInvoices,
            'bills_due_soon' => $billsDueSoonCount,
            'bills_due_soon_amount' => $billsDueSoonAmount,
            'unreconciled_transactions' => $unreconciled,
        ];
    }

    /**
     * Get P&L summary for dashboard widget (MTD)
     */
    public function getProfitLossSummary(string $companyId): array
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth()->toDateString();
        $endOfMonth = $now->copy()->endOfMonth()->toDateString();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth()->toDateString();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth()->toDateString();

        // Current month P&L from GL
        $currentMonth = $this->calculatePLFromGL($companyId, $startOfMonth, $endOfMonth);
        $lastMonth = $this->calculatePLFromGL($companyId, $startOfLastMonth, $endOfLastMonth);

        // Calculate growth
        $profitGrowth = $lastMonth['profit'] != 0
            ? (($currentMonth['profit'] - $lastMonth['profit']) / abs($lastMonth['profit'])) * 100
            : ($currentMonth['profit'] > 0 ? 100 : 0);

        return [
            'income' => $currentMonth['income'],
            'expenses' => $currentMonth['expenses'],
            'profit' => $currentMonth['profit'],
            'last_month_profit' => $lastMonth['profit'],
            'profit_growth' => round($profitGrowth, 1),
            'period' => $now->format('F Y'),
        ];
    }

    /**
     * Calculate P&L from General Ledger entries
     */
    private function calculatePLFromGL(string $companyId, string $startDate, string $endDate): array
    {
        $incomeTypes = ['revenue', 'other_income'];
        $expenseTypes = ['expense', 'cogs', 'other_expense'];

        $results = DB::table('acct.journal_entries as je')
            ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
            ->join('acct.accounts as a', 'a.id', '=', 'je.account_id')
            ->where('t.company_id', $companyId)
            ->where('t.status', 'posted')
            ->whereBetween('t.transaction_date', [$startDate, $endDate])
            ->whereIn('a.type', array_merge($incomeTypes, $expenseTypes))
            ->selectRaw('
                a.type,
                a.normal_balance,
                SUM(je.debit_amount) as total_debit,
                SUM(je.credit_amount) as total_credit
            ')
            ->groupBy('a.type', 'a.normal_balance')
            ->get();

        $income = 0;
        $expenses = 0;

        foreach ($results as $row) {
            $debit = (float) ($row->total_debit ?? 0);
            $credit = (float) ($row->total_credit ?? 0);

            // Net based on normal balance
            $net = $row->normal_balance === 'credit'
                ? ($credit - $debit)
                : ($debit - $credit);

            if (in_array($row->type, $incomeTypes)) {
                $income += $net;
            } else {
                $expenses += $net;
            }
        }

        return [
            'income' => $income,
            'expenses' => $expenses,
            'profit' => $income - $expenses,
        ];
    }
}
