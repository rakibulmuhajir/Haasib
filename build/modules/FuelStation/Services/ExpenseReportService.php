<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\Accounting\Models\Account;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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
            ],
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
            ->whereIn('a.type', ['expense', 'other_expense'])
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

        $types = $this->sourceMap()[$source] ?? [];
        if ($types) {
            $query->whereIn('t.transaction_type', $types);
        }

        return $query->get()
            ->map(function ($row) {
                $amount = round((float) $row->debit_amount - (float) $row->credit_amount, 2);
                $source = $this->sourceFor((string) $row->transaction_type);
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
            ->values()
            ->all();
    }

    /**
     * @return array<string,array<int,string>>
     */
    private function sourceMap(): array
    {
        return [
            'all' => [],
            'daily_close' => ['fuel_daily_close'],
            'bill' => ['bill'],
            'payroll' => ['payroll_accrual'],
            'adjustment' => ['adjustment', 'fuel_variance', 'inventory_revaluation'],
            'manual' => ['manual'],
        ];
    }

    /**
     * @return array{key:string,label:string}
     */
    private function sourceFor(string $transactionType): array
    {
        return match ($transactionType) {
            'fuel_daily_close' => ['key' => 'daily_close', 'label' => 'Daily Close'],
            'bill' => ['key' => 'bill', 'label' => 'Bill'],
            'payroll_accrual' => ['key' => 'payroll', 'label' => 'Payroll'],
            'adjustment', 'fuel_variance', 'inventory_revaluation' => ['key' => 'adjustment', 'label' => 'Adjustment'],
            'manual' => ['key' => 'manual', 'label' => 'Manual Journal'],
            default => ['key' => 'other', 'label' => str($transactionType)->replace('_', ' ')->title()->toString()],
        };
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
            ->whereIn('type', ['expense', 'other_expense'])
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
            ['value' => 'bill', 'label' => 'Bills'],
            ['value' => 'payroll', 'label' => 'Payroll'],
            ['value' => 'adjustment', 'label' => 'Adjustments'],
            ['value' => 'manual', 'label' => 'Manual Journals'],
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
