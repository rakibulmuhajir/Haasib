<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\Accounting\Models\Account;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Expenses are what was entered as an expense: Daily Close > Money out > Expenses, and the Record
 * Expense page -- every `expense` transaction, whatever account it went to (furniture and building
 * work included, since that is where they are entered). Other costs that reach expense accounts
 * without being entered as expenses -- tank shrinkage, cash short/over, payroll, bills, journals --
 * are listed apart as "other costs", outside the expense total, so the report still ties to the P&L.
 */
class ExpenseReportService
{
    /**
     * @return array{
     *   filters: array<string,string>,
     *   totals: array<string,float|int>,
     *   periodRows: array<int,array<string,mixed>>,
     *   accountRows: array<int,array<string,mixed>>,
     *   sourceRows: array<int,array<string,mixed>>,
     *   detailRows: array<int,array<string,mixed>>,
     *   accountOptions: array<int,array{id:string,code:string,name:string}>,
     *   sourceOptions: array<int,array{value:string,label:string}>
     * }
     */
    public function run(string $companyId, string $startDate, string $endDate, string $groupBy = 'day', string $accountId = 'all', string $source = 'all'): array
    {
        $groupBy = in_array($groupBy, ['day', 'week', 'month'], true) ? $groupBy : 'day';
        $source = array_key_exists($source, $this->sourceMap()) ? $source : 'all';

        $rows = $this->expenseLines($companyId, $startDate, $endDate, $accountId, $source);
        $otherRows = $this->otherCostRows($companyId, $startDate, $endDate);
        $periodRows = [];
        $accountRows = [];
        $sourceTotals = [];

        foreach ($rows as $row) {
            $date = Carbon::parse($row['date']);
            $periodKey = $this->periodKey($date, $groupBy);
            if (!isset($periodRows[$periodKey])) {
                $periodRows[$periodKey] = [
                    'key' => $periodKey,
                    'label' => $this->periodLabel($date, $groupBy),
                    'amount' => 0.0,
                    'line_count' => 0,
                    'transaction_ids' => [],
                    'detail_url_id' => null,
                ];
            }

            $periodRows[$periodKey]['amount'] += $row['amount'];
            $periodRows[$periodKey]['line_count']++;
            $periodRows[$periodKey]['transaction_ids'][$row['transaction_id']] = $row['transaction_id'];

            if (!isset($accountRows[$row['account_id']])) {
                $accountRows[$row['account_id']] = [
                    'account_id' => $row['account_id'],
                    'account_code' => $row['account_code'],
                    'account_name' => $row['account_name'],
                    'account_type' => $row['account_type'],
                    'amount' => 0.0,
                    'line_count' => 0,
                ];
            }

            $accountRows[$row['account_id']]['amount'] += $row['amount'];
            $accountRows[$row['account_id']]['line_count']++;

            if (!isset($sourceTotals[$row['source_key']])) {
                $sourceTotals[$row['source_key']] = [
                    'source' => $row['source_key'],
                    'label' => $row['source_label'],
                    'amount' => 0.0,
                    'line_count' => 0,
                ];
            }
            $sourceTotals[$row['source_key']]['amount'] += $row['amount'];
            $sourceTotals[$row['source_key']]['line_count']++;
        }

        foreach ($periodRows as &$periodRow) {
            $periodRow['transaction_ids'] = array_values($periodRow['transaction_ids']);
            $periodRow['transaction_count'] = count($periodRow['transaction_ids']);
            $periodRow['detail_url_id'] = $periodRow['transaction_count'] === 1 ? $periodRow['transaction_ids'][0] : null;
            $periodRow['amount'] = round((float) $periodRow['amount'], 2);
        }
        unset($periodRow);

        $accountRows = array_values($accountRows);
        foreach ($accountRows as &$accountRow) {
            $accountRow['amount'] = round((float) $accountRow['amount'], 2);
        }
        unset($accountRow);
        usort($accountRows, fn (array $a, array $b) => $b['amount'] <=> $a['amount']);

        $sourceRows = array_values($sourceTotals);
        foreach ($sourceRows as &$sourceRow) {
            $sourceRow['amount'] = round((float) $sourceRow['amount'], 2);
        }
        unset($sourceRow);
        usort($sourceRows, fn (array $a, array $b) => $b['amount'] <=> $a['amount']);

        return [
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'group_by' => $groupBy,
                'account_id' => $accountId,
                'source' => $source,
            ],
            'totals' => [
                'amount' => round(array_sum(array_column($rows, 'amount')), 2),
                'line_count' => count($rows),
                'account_count' => count($accountRows),
                'transaction_count' => count(array_unique(array_column($rows, 'transaction_id'))),
                'other_amount' => round(array_sum(array_column($otherRows, 'amount')), 2),
            ],
            'otherRows' => $otherRows,
            'periodRows' => array_values($periodRows),
            'accountRows' => $accountRows,
            'sourceRows' => $sourceRows,
            'detailRows' => $rows,
            'accountOptions' => $this->accountOptions($companyId),
            'sourceOptions' => $this->sourceOptions(),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function expenseLines(string $companyId, string $startDate, string $endDate, string $accountId, string $source): array
    {
        $query = DB::table('acct.journal_entries as je')
            ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
            ->join('acct.accounts as a', 'a.id', '=', 'je.account_id')
            ->where('t.company_id', $companyId)
            ->where('t.status', 'posted')
            ->whereNull('t.deleted_at')
            ->whereNull('t.reversed_by_id')
            ->whereBetween('t.transaction_date', [$startDate, $endDate])
            ->where('t.transaction_type', 'expense')
            ->where('je.debit_amount', '>', 0)
            ->select([
                'je.id as line_id',
                'je.transaction_id',
                'je.description as line_description',
                'je.debit_amount',
                'je.credit_amount',
                'a.id as account_id',
                'a.code as account_code',
                'a.name as account_name',
                'a.type as account_type',
                't.transaction_number',
                't.transaction_type',
                't.reference_type',
                't.reference_id',
                't.description as transaction_description',
                't.transaction_date',
                't.metadata',
            ])
            ->orderByDesc('t.transaction_date')
            ->orderBy('a.code');

        if ($accountId !== 'all') {
            $query->where('a.id', $accountId);
        }

        return $query->get()
            ->map(function ($row) {
                $amount = round((float) $row->debit_amount - (float) $row->credit_amount, 2);
                $source = $this->sourceFor((string) $row->reference_type);
                $metadata = is_string($row->metadata) ? json_decode($row->metadata, true) : [];

                return [
                    'line_id' => $row->line_id,
                    'transaction_id' => $row->transaction_id,
                    'transaction_number' => $row->transaction_number,
                    'date' => Carbon::parse($row->transaction_date)->toDateString(),
                    'date_label' => Carbon::parse($row->transaction_date)->format('d M Y'),
                    'account_id' => $row->account_id,
                    'account_code' => $row->account_code,
                    'account_name' => $row->account_name,
                    'account_type' => $row->account_type,
                    'description' => $this->description((string) ($row->line_description ?: $row->transaction_description), $metadata),
                    'amount' => $amount,
                    'source_key' => $source['key'],
                    'source_label' => $source['label'],
                    'transaction_type' => $row->transaction_type,
                    'reference_type' => $row->reference_type,
                    'reference_id' => $row->reference_id,
                    'detail_route' => $this->detailRoute((string) $row->transaction_type, $row->reference_id, $row->transaction_id),
                ];
            })
            ->filter(fn (array $row) => abs((float) $row['amount']) > 0.005)
            ->filter(fn (array $row) => $source === 'all' || $row['source_key'] === $source)
            ->values()
            ->all();
    }

    /**
     * Costs on expense accounts that were not entered as expenses, per account and kind -- tank
     * shrinkage, cash short/over, payroll, bills, journals.
     *
     * @return array<int,array{account_id:string,account_code:string,account_name:string,label:string,amount:float}>
     */
    private function otherCostRows(string $companyId, string $startDate, string $endDate): array
    {
        return DB::table('acct.journal_entries as je')
            ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
            ->join('acct.accounts as a', 'a.id', '=', 'je.account_id')
            ->where('t.company_id', $companyId)
            ->where('t.status', 'posted')
            ->whereNull('t.deleted_at')
            ->whereNull('t.reversed_by_id')
            ->whereBetween('t.transaction_date', [$startDate, $endDate])
            ->whereIn('a.type', ['expense', 'other_expense'])
            ->where('t.transaction_type', '!=', 'expense')
            ->groupBy('a.id', 'a.code', 'a.name', 't.transaction_type')
            ->orderBy('a.code')
            ->selectRaw('a.id as account_id, a.code as account_code, a.name as account_name, t.transaction_type, SUM(je.debit_amount - je.credit_amount) as amount')
            ->get()
            ->groupBy('account_id')
            ->map(fn ($lines) => [
                'account_id' => $lines->first()->account_id,
                'account_code' => $lines->first()->account_code,
                'account_name' => $lines->first()->account_name,
                'label' => $lines->pluck('transaction_type')->map(fn ($type) => $this->otherLabel((string) $type))->unique()->implode(', '),
                'amount' => round((float) $lines->sum('amount'), 2),
            ])
            ->filter(fn (array $row) => abs($row['amount']) > 0.005)
            ->values()
            ->all();
    }

    private function otherLabel(string $transactionType): string
    {
        return match ($transactionType) {
            'fuel_daily_close' => 'Daily close (dips, cash count)',
            'fuel_close_cost_fix' => 'Cost correction',
            'bill' => 'Bill',
            'payroll_accrual' => 'Payroll',
            'adjustment', 'fuel_variance', 'inventory_revaluation' => 'Stock adjustment',
            'manual' => 'Journal',
            default => str($transactionType)->replace('_', ' ')->title()->toString(),
        };
    }

    /**
     * @return array<string,array<int,string>>
     */
    private function sourceMap(): array
    {
        return ['all' => [], 'daily_close' => [], 'recorded' => []];
    }

    /**
     * @return array{key:string,label:string}
     */
    private function sourceFor(string $referenceType): array
    {
        return $referenceType === 'fuel.daily_close_expense'
            ? ['key' => 'daily_close', 'label' => 'Daily Close']
            : ['key' => 'recorded', 'label' => 'Record Expense'];
    }

    /**
     * @param array<string,mixed> $metadata
     */
    private function description(string $fallback, array $metadata): string
    {
        if (($metadata['notes'] ?? null) && is_string($metadata['notes'])) {
            return $metadata['notes'];
        }

        return $fallback ?: 'Expense';
    }

    private function detailRoute(string $transactionType, ?string $referenceId, string $transactionId): string
    {
        if ($transactionType === 'fuel_daily_close') {
            return 'daily_close';
        }

        if ($transactionType === 'bill' && $referenceId) {
            return 'bill';
        }

        return 'journal';
    }

    /**
     * @return array<int,array{id:string,code:string,name:string}>
     */
    private function accountOptions(string $companyId): array
    {
        return Account::where('company_id', $companyId)
            ->moneyOutTarget()
            ->whereNull('deleted_at')
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int,array{value:string,label:string}>
     */
    private function sourceOptions(): array
    {
        return [
            ['value' => 'all', 'label' => 'All sources'],
            ['value' => 'daily_close', 'label' => 'Daily Close'],
            ['value' => 'recorded', 'label' => 'Record Expense'],
        ];
    }

    private function periodKey(Carbon $date, string $groupBy): string
    {
        return match ($groupBy) {
            'week' => $date->copy()->startOfWeek()->toDateString(),
            'month' => $date->format('Y-m'),
            default => $date->toDateString(),
        };
    }

    private function periodLabel(Carbon $date, string $groupBy): string
    {
        return match ($groupBy) {
            'week' => 'Week of ' . $date->copy()->startOfWeek()->format('d M Y'),
            'month' => $date->format('F Y'),
            default => $date->format('d M Y'),
        };
    }
}
