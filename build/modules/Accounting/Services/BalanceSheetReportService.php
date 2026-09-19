<?php

namespace App\Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;

/**
 * What the business owns, owes and is worth at a point in time.
 *
 * The profit and loss covers a period and answers "what did we earn in September". At a fuel
 * station most of the money is not in that answer: it is stock in the tanks, cash owed by
 * credit buyers, cash owed to the supplier and capital put in by investors. Only a balance
 * sheet shows whether a good month actually left the business better off or merely converted
 * cash into receivables.
 *
 * Revenue and expense accounts are never closed out to retained earnings in this ledger, so
 * the result for the year to date is computed here and presented as its own equity line.
 * Without it the sheet cannot balance.
 */
class BalanceSheetReportService
{
    private const POSTED = ['posted', 'locked'];

    private const ASSET_TYPES = ['asset'];
    private const LIABILITY_TYPES = ['liability'];
    private const EQUITY_TYPES = ['equity'];
    private const INCOME_TYPES = ['revenue', 'other_income'];
    private const EXPENSE_TYPES = ['expense', 'cogs', 'other_expense'];

    /**
     * @return array{
     *   as_of: string,
     *   assets: array<int, array{id:string,code:string,name:string,subtype:?string,amount:float}>,
     *   liabilities: array<int, array{id:string,code:string,name:string,subtype:?string,amount:float}>,
     *   equity: array<int, array{id:string,code:string,name:string,subtype:?string,amount:float}>,
     *   retained_earnings: float,
     *   totals: array{assets:float,liabilities:float,equity:float,liabilities_and_equity:float,difference:float},
     *   is_balanced: bool
     * }
     */
    public function run(string $companyId, string $asOf): array
    {
        $rows = $this->balances($companyId, $asOf, array_merge(
            self::ASSET_TYPES, self::LIABILITY_TYPES, self::EQUITY_TYPES
        ));

        $assets = [];
        $liabilities = [];
        $equity = [];

        foreach ($rows as $row) {
            $debit = (float) $row->debit;
            $credit = (float) $row->credit;
            $isAsset = in_array($row->type, self::ASSET_TYPES, true);

            // Assets sit on the debit side; liabilities and equity on the credit side. Sign
            // comes from the section, so a contra account inside a section (accumulated
            // depreciation, owner drawings) correctly subtracts from it.
            $amount = round($isAsset ? $debit - $credit : $credit - $debit, 2);
            if ($amount === 0.0) {
                continue;
            }

            $entry = [
                'id' => (string) $row->id,
                'code' => (string) $row->code,
                'name' => (string) $row->name,
                'subtype' => $row->subtype !== null ? (string) $row->subtype : null,
                'amount' => $amount,
            ];

            if ($isAsset) {
                $assets[] = $entry;
            } elseif (in_array($row->type, self::LIABILITY_TYPES, true)) {
                $liabilities[] = $entry;
            } else {
                $equity[] = $entry;
            }
        }

        $retainedEarnings = $this->retainedEarnings($companyId, $asOf);

        $totalAssets = round(array_sum(array_column($assets, 'amount')), 2);
        $totalLiabilities = round(array_sum(array_column($liabilities, 'amount')), 2);
        $totalEquity = round(array_sum(array_column($equity, 'amount')) + $retainedEarnings, 2);
        $liabilitiesAndEquity = round($totalLiabilities + $totalEquity, 2);

        return [
            'as_of' => $asOf,
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'retained_earnings' => $retainedEarnings,
            'totals' => [
                'assets' => $totalAssets,
                'liabilities' => $totalLiabilities,
                'equity' => $totalEquity,
                'liabilities_and_equity' => $liabilitiesAndEquity,
                'difference' => round($totalAssets - $liabilitiesAndEquity, 2),
            ],
            'is_balanced' => abs($totalAssets - $liabilitiesAndEquity) < 0.01,
        ];
    }

    /**
     * Everything earned less everything spent, from the first entry in the ledger up to the
     * as-of date. This is the equity the trading itself created.
     */
    private function retainedEarnings(string $companyId, string $asOf): float
    {
        $rows = $this->balances($companyId, $asOf, array_merge(self::INCOME_TYPES, self::EXPENSE_TYPES));

        $result = 0.0;
        foreach ($rows as $row) {
            $debit = (float) $row->debit;
            $credit = (float) $row->credit;
            $result += in_array($row->type, self::INCOME_TYPES, true)
                ? $credit - $debit
                : -($debit - $credit);
        }

        return round($result, 2);
    }

    /**
     * @param  array<int, string>  $types
     */
    private function balances(string $companyId, string $asOf, array $types)
    {
        return DB::table('acct.journal_entries as je')
            ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
            ->join('acct.accounts as a', 'a.id', '=', 'je.account_id')
            ->where('t.company_id', $companyId)
            ->whereIn('t.status', self::POSTED)
            ->whereDate('t.transaction_date', '<=', $asOf)
            ->whereIn('a.type', $types)
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type', 'a.subtype')
            ->selectRaw('a.id, a.code, a.name, a.type, a.subtype, SUM(je.debit_amount) AS debit, SUM(je.credit_amount) AS credit')
            ->orderBy('a.code')
            ->get();
    }
}
