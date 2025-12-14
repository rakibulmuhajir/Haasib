<?php

namespace App\Modules\Accounting\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProfitLossReportService
{
    /**
     * @return array{
     *   income: array<int, array{id:string,code:string,name:string,type:string,debit:float,credit:float,net:float}>,
     *   expenses: array<int, array{id:string,code:string,name:string,type:string,debit:float,credit:float,net:float}>,
     *   totals: array{income:float,expenses:float,profit:float}
     * }
     */
    public function run(string $companyId, string $startDate, string $endDate): array
    {
        /** @var Collection<int, object{ id:string, code:string, name:string, type:string, normal_balance:string, debit:string|float|null, credit:string|float|null }> $rows */
        $rows = DB::table('acct.journal_entries as je')
            ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
            ->join('acct.accounts as a', 'a.id', '=', 'je.account_id')
            ->where('t.company_id', $companyId)
            ->where('t.status', 'posted')
            ->whereBetween('t.transaction_date', [$startDate, $endDate])
            ->whereIn('a.type', ['revenue', 'other_income', 'expense', 'cogs', 'other_expense'])
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type', 'a.normal_balance')
            ->selectRaw('a.id, a.code, a.name, a.type, a.normal_balance, SUM(je.debit_amount) AS debit, SUM(je.credit_amount) AS credit')
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
                'net' => $net,
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
            'totals' => [
                'income' => $totalIncome,
                'expenses' => $totalExpenses,
                'profit' => $totalIncome - $totalExpenses,
            ],
        ];
    }

    private function net(string $normalBalance, float $debit, float $credit): float
    {
        return $normalBalance === 'credit'
            ? ($credit - $debit)
            : ($debit - $credit);
    }
}

