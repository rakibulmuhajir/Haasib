<?php

namespace App\Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;

/**
 * Every account's balance as at a date, each on the side it falls, with the two columns
 * proved equal.
 *
 * Nobody reads a trial balance for insight — it is the proof that the ledger is internally
 * consistent. If the columns disagree, something wrote a one-sided entry and every figure
 * downstream (the P&L, the balance sheet, a buyer's statement) is suspect.
 */
class TrialBalanceReportService
{
    /** Nothing writes 'locked' today, but a locked transaction is still posted. */
    private const POSTED = ['posted', 'locked'];

    /**
     * @return array{
     *   rows: array<int, array{id:string,code:string,name:string,type:string,debit:float,credit:float}>,
     *   totals: array{debit:float,credit:float,difference:float},
     *   is_balanced: bool
     * }
     */
    public function run(string $companyId, string $asOf): array
    {
        $rows = DB::table('acct.journal_entries as je')
            ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
            ->join('acct.accounts as a', 'a.id', '=', 'je.account_id')
            ->where('t.company_id', $companyId)
            ->whereIn('t.status', self::POSTED)
            ->whereDate('t.transaction_date', '<=', $asOf)
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type')
            ->selectRaw('a.id, a.code, a.name, a.type, SUM(je.debit_amount) AS debit, SUM(je.credit_amount) AS credit')
            ->orderBy('a.code')
            ->get();

        $accounts = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($rows as $row) {
            // The account's own net position, placed on whichever side it lands. Listing the
            // gross debit and credit turnover instead would also balance, but says nothing
            // about what the account is actually worth.
            $balance = round((float) $row->debit - (float) $row->credit, 2);
            if ($balance === 0.0) {
                continue;
            }

            $debit = $balance > 0 ? $balance : 0.0;
            $credit = $balance < 0 ? abs($balance) : 0.0;
            $totalDebit += $debit;
            $totalCredit += $credit;

            $accounts[] = [
                'id' => (string) $row->id,
                'code' => (string) $row->code,
                'name' => (string) $row->name,
                'type' => (string) $row->type,
                'debit' => $debit,
                'credit' => $credit,
            ];
        }

        $totalDebit = round($totalDebit, 2);
        $totalCredit = round($totalCredit, 2);

        return [
            'rows' => $accounts,
            'totals' => [
                'debit' => $totalDebit,
                'credit' => $totalCredit,
                'difference' => round($totalDebit - $totalCredit, 2),
            ],
            'is_balanced' => abs($totalDebit - $totalCredit) < 0.01,
        ];
    }
}
