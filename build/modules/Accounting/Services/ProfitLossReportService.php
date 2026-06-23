<?php

namespace App\Modules\Accounting\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProfitLossReportService
{
    /**
     * @return array{
     *   income: array<int, array{id:string,code:string,name:string,type:string,debit:float,credit:float,net:float,line_count:int,transaction_count:int}>,
     *   expenses: array<int, array{id:string,code:string,name:string,type:string,debit:float,credit:float,net:float,line_count:int,transaction_count:int}>,
     *   period_breakdown: array<int, array{period:string,income:float,expenses:float,profit:float}>,
     *   source_breakdown: array<int, array{source:string,income:float,expenses:float,profit:float,transaction_count:int}>,
     *   recent_lines: array<int, array{id:string,transaction_id:string,transaction_number:string,transaction_type:string,transaction_date:string,account_id:string,account_code:string,account_name:string,account_type:string,description:?string,debit:float,credit:float,net:float}>,
     *   totals: array{income:float,expenses:float,profit:float}
     * }
     */
    public function run(string $companyId, string $startDate, string $endDate): array
    {
        /** @var Collection<int, object{ id:string, code:string, name:string, type:string, normal_balance:string, debit:string|float|null, credit:string|float|null, line_count:int, transaction_count:int }> $rows */
        $rows = DB::table('acct.journal_entries as je')
            ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
            ->join('acct.accounts as a', 'a.id', '=', 'je.account_id')
            ->where('t.company_id', $companyId)
            ->where('t.status', 'posted')
            ->whereBetween('t.transaction_date', [$startDate, $endDate])
            ->whereIn('a.type', ['revenue', 'other_income', 'expense', 'cogs', 'other_expense'])
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type', 'a.normal_balance')
            ->selectRaw('a.id, a.code, a.name, a.type, a.normal_balance, SUM(je.debit_amount) AS debit, SUM(je.credit_amount) AS credit, COUNT(*) AS line_count, COUNT(DISTINCT t.id) AS transaction_count')
            ->orderBy('a.type')
            ->orderBy('a.code')
            ->get();

        $accounts = $rows->map(function ($r) {
            $debit = (float) ($r->debit ?? 0);
            $credit = (float) ($r->credit ?? 0);
            $net = $this->net($r->normal_balance, $debit, $credit);

            return [
                'id' => $r->id,
                'code' => $r->code,
                'name' => $r->name,
                'type' => $r->type,
                'debit' => $debit,
                'credit' => $credit,
                'net' => round($net, 2),
                'line_count' => (int) $r->line_count,
                'transaction_count' => (int) $r->transaction_count,
            ];
        });

        $incomeTypes = ['revenue', 'other_income'];
        $expenseTypes = ['expense', 'cogs', 'other_expense'];

        $income = $accounts->whereIn('type', $incomeTypes)->values()->all();
        $expenses = $accounts->whereIn('type', $expenseTypes)->values()->all();

        $totalIncome = (float) $accounts->whereIn('type', $incomeTypes)->sum('net');
        $totalExpenses = (float) $accounts->whereIn('type', $expenseTypes)->sum('net');

        return [
            'income' => $income,
            'expenses' => $expenses,
            'period_breakdown' => $this->periodBreakdown($companyId, $startDate, $endDate),
            'source_breakdown' => $this->sourceBreakdown($companyId, $startDate, $endDate),
            'recent_lines' => $this->recentLines($companyId, $startDate, $endDate),
            'totals' => [
                'income' => round($totalIncome, 2),
                'expenses' => round($totalExpenses, 2),
                'profit' => round($totalIncome - $totalExpenses, 2),
            ],
        ];
    }

    private function net(string $normalBalance, float $debit, float $credit): float
    {
        return $normalBalance === 'credit'
            ? ($credit - $debit)
            : ($debit - $credit);
    }

    /**
     * @return array<int, array{period:string,income:float,expenses:float,profit:float}>
     */
    private function periodBreakdown(string $companyId, string $startDate, string $endDate): array
    {
        return $this->baseLineQuery($companyId, $startDate, $endDate)
            ->selectRaw("
                to_char(t.transaction_date, 'YYYY-MM') AS period,
                SUM(CASE WHEN a.type IN ('revenue', 'other_income') THEN CASE WHEN a.normal_balance = 'credit' THEN je.credit_amount - je.debit_amount ELSE je.debit_amount - je.credit_amount END ELSE 0 END) AS income,
                SUM(CASE WHEN a.type IN ('expense', 'cogs', 'other_expense') THEN CASE WHEN a.normal_balance = 'credit' THEN je.credit_amount - je.debit_amount ELSE je.debit_amount - je.credit_amount END ELSE 0 END) AS expenses
            ")
            ->groupByRaw("to_char(t.transaction_date, 'YYYY-MM')")
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => [
                'period' => (string) $row->period,
                'income' => round((float) $row->income, 2),
                'expenses' => round((float) $row->expenses, 2),
                'profit' => round((float) $row->income - (float) $row->expenses, 2),
            ])
            ->all();
    }

    /**
     * @return array<int, array{source:string,income:float,expenses:float,profit:float,transaction_count:int}>
     */
    private function sourceBreakdown(string $companyId, string $startDate, string $endDate): array
    {
        return $this->baseLineQuery($companyId, $startDate, $endDate)
            ->selectRaw("
                COALESCE(NULLIF(t.reference_type, ''), t.transaction_type) AS source,
                SUM(CASE WHEN a.type IN ('revenue', 'other_income') THEN CASE WHEN a.normal_balance = 'credit' THEN je.credit_amount - je.debit_amount ELSE je.debit_amount - je.credit_amount END ELSE 0 END) AS income,
                SUM(CASE WHEN a.type IN ('expense', 'cogs', 'other_expense') THEN CASE WHEN a.normal_balance = 'credit' THEN je.credit_amount - je.debit_amount ELSE je.debit_amount - je.credit_amount END ELSE 0 END) AS expenses,
                COUNT(DISTINCT t.id) AS transaction_count
            ")
            ->groupByRaw("COALESCE(NULLIF(t.reference_type, ''), t.transaction_type)")
            ->orderByDesc(DB::raw('ABS(SUM(CASE WHEN a.type IN (\'revenue\', \'other_income\') THEN CASE WHEN a.normal_balance = \'credit\' THEN je.credit_amount - je.debit_amount ELSE je.debit_amount - je.credit_amount END ELSE 0 END) - SUM(CASE WHEN a.type IN (\'expense\', \'cogs\', \'other_expense\') THEN CASE WHEN a.normal_balance = \'credit\' THEN je.credit_amount - je.debit_amount ELSE je.debit_amount - je.credit_amount END ELSE 0 END))'))
            ->get()
            ->map(fn ($row) => [
                'source' => (string) $row->source,
                'income' => round((float) $row->income, 2),
                'expenses' => round((float) $row->expenses, 2),
                'profit' => round((float) $row->income - (float) $row->expenses, 2),
                'transaction_count' => (int) $row->transaction_count,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id:string,transaction_id:string,transaction_number:string,transaction_type:string,transaction_date:string,account_id:string,account_code:string,account_name:string,account_type:string,description:?string,debit:float,credit:float,net:float}>
     */
    private function recentLines(string $companyId, string $startDate, string $endDate): array
    {
        return $this->baseLineQuery($companyId, $startDate, $endDate)
            ->selectRaw('je.id, t.id AS transaction_id, t.transaction_number, t.transaction_type, t.transaction_date, t.description, a.id AS account_id, a.code AS account_code, a.name AS account_name, a.type AS account_type, a.normal_balance, je.debit_amount AS debit, je.credit_amount AS credit')
            ->orderByDesc('t.transaction_date')
            ->orderByDesc('t.created_at')
            ->limit(25)
            ->get()
            ->map(function ($row) {
                $debit = (float) $row->debit;
                $credit = (float) $row->credit;

                return [
                    'id' => (string) $row->id,
                    'transaction_id' => (string) $row->transaction_id,
                    'transaction_number' => (string) $row->transaction_number,
                    'transaction_type' => (string) $row->transaction_type,
                    'transaction_date' => (string) $row->transaction_date,
                    'account_id' => (string) $row->account_id,
                    'account_code' => (string) $row->account_code,
                    'account_name' => (string) $row->account_name,
                    'account_type' => (string) $row->account_type,
                    'description' => $row->description ? (string) $row->description : null,
                    'debit' => round($debit, 2),
                    'credit' => round($credit, 2),
                    'net' => round($this->net((string) $row->normal_balance, $debit, $credit), 2),
                ];
            })
            ->all();
    }

    private function baseLineQuery(string $companyId, string $startDate, string $endDate)
    {
        return DB::table('acct.journal_entries as je')
            ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
            ->join('acct.accounts as a', 'a.id', '=', 'je.account_id')
            ->where('t.company_id', $companyId)
            ->where('t.status', 'posted')
            ->whereBetween('t.transaction_date', [$startDate, $endDate])
            ->whereIn('a.type', ['revenue', 'other_income', 'expense', 'cogs', 'other_expense']);
    }
}
