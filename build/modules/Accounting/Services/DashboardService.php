<?php

namespace App\Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardService
{
    /**
     * What the company actually holds, read from the general ledger.
     *
     * This used to sum acct.company_bank_accounts.current_balance, which is the
     * statement side: acct.update_account_balance() maintains it from imported bank feed
     * rows, and nothing else writes it. Haasib's money does not arrive that way. A daily
     * close posts journals; so does an invoice payment, a bill payment and a transfer.
     * None of them creates a feed row.
     *
     * So on a station that had traded for five days and held 3,527,862 in the ledger, this
     * returned 0.00, and the dashboard reported it to the owner as the company's cash. A
     * figure that is only correct for companies importing bank statements is not a cash
     * position; it is a bank reconciliation input that was being read as one.
     *
     * The ledger is the source of truth for what is held, so this reads the ledger.
     */
    public function getCashPosition(string $companyId): array
    {
        $accounts = DB::table('acct.accounts as a')
            ->leftJoin('acct.journal_entries as j', 'j.account_id', '=', 'a.id')
            ->where('a.company_id', $companyId)
            ->whereIn('a.subtype', ['cash', 'bank'])
            ->where('a.is_active', true)
            ->whereNull('a.deleted_at')
            ->groupBy('a.id', 'a.code', 'a.name', 'a.subtype', 'a.currency')
            ->orderBy('a.code')
            ->selectRaw('a.name, a.subtype, a.currency, COALESCE(SUM(j.debit_amount) - SUM(j.credit_amount), 0) as balance')
            ->get();

        return [
            'total' => (float) $accounts->sum('balance'),
            // Kept separate because they answer different questions: one is countable in the
            // drawer tonight, the other has to clear.
            'cash' => (float) $accounts->where('subtype', 'cash')->sum('balance'),
            'bank' => (float) $accounts->where('subtype', 'bank')->sum('balance'),
            'accounts' => $accounts->map(fn ($a) => [
                'name' => $a->name,
                'balance' => (float) $a->balance,
                'currency' => $a->currency,
                'kind' => $a->subtype,
            ])->values()->toArray(),
        ];
    }

    /**
     * Where the company stands: what it holds, what is owed to it, and what it owes.
     *
     * Every figure comes from the general ledger, so the four cannot disagree with each
     * other or with the reports. Receivables and payables are the control-account balances
     * rather than a sum over invoice and bill rows - a document that was posted but later
     * adjusted in the ledger would otherwise show one number here and another on the books.
     *
     * @return array{cash: float, bank: float, receivable: float, payable: float, net: float, accounts: array}
     */
    public function getFinancialPosition(string $companyId): array
    {
        $position = $this->getCashPosition($companyId);

        $control = function (array $subtypes, bool $creditNormal) use ($companyId): float {
            $balance = (float) DB::table('acct.accounts as a')
                ->leftJoin('acct.journal_entries as j', 'j.account_id', '=', 'a.id')
                ->where('a.company_id', $companyId)
                ->whereIn('a.subtype', $subtypes)
                ->whereNull('a.deleted_at')
                ->sum(DB::raw('COALESCE(j.debit_amount, 0) - COALESCE(j.credit_amount, 0)'));

            // A payable sits credit-normal, so its ledger balance is negative when money is
            // owed. Flip it here rather than at the call site, where the sign convention
            // would have to be remembered.
            return $creditNormal ? -$balance : $balance;
        };

        $receivable = $control(['accounts_receivable'], false);
        $payable = $control(['accounts_payable'], true);

        $byKind = collect($position['accounts'])->groupBy('kind');

        return [
            'cash' => $position['cash'],
            'bank' => $position['bank'],
            'receivable' => $receivable,
            'payable' => $payable,
            'net' => round($position['cash'] + $position['bank'] + $receivable - $payable, 2),
            'accounts' => $position['accounts'],

            // What each figure is made of, so a number on the dashboard can be opened
            // rather than merely read. Every list is ordered largest first: the question
            // behind the click is almost always "who is most of it".
            'breakdown' => [
                'cash' => $this->asBreakdown($byKind->get('cash', collect()), $position['cash']),
                'bank' => $this->asBreakdown($byKind->get('bank', collect()), $position['bank']),
                'receivable' => $this->asBreakdown($this->receivableByCustomer($companyId), $receivable),
                'payable' => $this->asBreakdown($this->payableByVendor($companyId), $payable),
            ],
        ];
    }

    /**
     * Normalise a breakdown and reconcile it to the figure it explains.
     *
     * The headline comes from the control account, the detail from the documents behind it.
     * They should agree, and when they do not, a journal has touched the control account
     * without a document - which is worth seeing, not hiding. The remainder row makes the
     * parts add up to the whole they are shown under, so the drill-down can never silently
     * contradict the number the user clicked.
     */
    private function asBreakdown($rows, float $total): array
    {
        $items = collect($rows)
            ->map(fn ($r) => ['label' => (string) ($r['label'] ?? $r['name'] ?? 'Unnamed'), 'amount' => round((float) ($r['amount'] ?? $r['balance'] ?? 0), 2)])
            ->filter(fn ($r) => $r['amount'] != 0.0)
            ->sortByDesc('amount')
            ->values();

        $remainder = round($total - $items->sum('amount'), 2);
        if (abs($remainder) >= 0.01) {
            $items->push(['label' => 'Other ledger entries', 'amount' => $remainder]);
        }

        return $items->all();
    }

    private function receivableByCustomer(string $companyId)
    {
        return DB::table('acct.invoices as i')
            ->join('acct.customers as c', 'c.id', '=', 'i.customer_id')
            ->where('i.company_id', $companyId)
            ->whereNotIn('i.status', ['void', 'draft'])
            ->where('i.balance', '>', 0)
            ->whereNull('i.deleted_at')
            ->groupBy('c.id', 'c.name')
            ->selectRaw('c.name as label, SUM(i.balance) as amount')
            ->get()
            ->map(fn ($r) => ['label' => $r->label, 'amount' => (float) $r->amount]);
    }

    private function payableByVendor(string $companyId)
    {
        return DB::table('acct.bills as b')
            ->join('acct.vendors as v', 'v.id', '=', 'b.vendor_id')
            ->where('b.company_id', $companyId)
            ->whereNotIn('b.status', ['void', 'draft'])
            ->where('b.balance', '>', 0)
            ->whereNull('b.deleted_at')
            ->groupBy('v.id', 'v.name')
            ->selectRaw('v.name as label, SUM(b.balance) as amount')
            ->get()
            ->map(fn ($r) => ['label' => $r->label, 'amount' => (float) $r->amount]);
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
