<?php

namespace App\Modules\FuelStation\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What the station spent over a range, in one list of categories (expense accounts):
 *  - Expenses: what was entered as an expense -- Daily Close > Money out > Expenses and the
 *    Record Expense page (every `expense` transaction, whatever account it went to).
 *  - Salaries: approved payslips (payroll accruals), on their pay period's last day.
 *  - Card charges: what the bank or card supplier kept on card sales (CardChargeService).
 * Other costs on expense accounts that are not spending the station chose -- tank shrinkage, cash
 * short/over, bills, journals -- are listed apart, outside the total, so the report still ties to
 * the Profit & Loss. Reversed entries and their reversals are both left out.
 */
class ExpenseReportService
{
    public const KINDS = ['expense' => 'Expenses', 'salary' => 'Salaries', 'card_charge' => 'Card charges'];

    /**
     * @return array{
     *   filters: array<string,string>,
     *   totals: array<string,float|int>,
     *   kindRows: array<int,array<string,mixed>>,
     *   accountRows: array<int,array<string,mixed>>,
     *   detailRows: array<int,array<string,mixed>>,
     *   otherRows: array<int,array<string,mixed>>
     * }
     */
    public function run(string $companyId, string $startDate, string $endDate): array
    {
        $cardTransactions = DB::table('fuel.card_charges')->where('company_id', $companyId)->whereNull('deleted_at')
            ->whereNotNull('transaction_id')->pluck('transaction_id')->all();

        $rows = array_merge(
            $this->expenseLines($companyId, $startDate, $endDate),
            $this->salaryLines($companyId, $startDate, $endDate),
            $this->cardChargeLines($companyId, $startDate, $endDate, $cardTransactions),
        );
        usort($rows, fn (array $a, array $b) => [$b['date'], $a['account_code']] <=> [$a['date'], $b['account_code']]);

        $total = round(array_sum(array_column($rows, 'amount')), 2);

        $accounts = [];
        foreach ($rows as $row) {
            $accounts[$row['account_id']] ??= [
                'account_id' => $row['account_id'], 'account_code' => $row['account_code'], 'account_name' => $row['account_name'],
                'account_type' => $row['account_type'], 'kinds' => [], 'amount' => 0.0, 'line_count' => 0, 'lines' => [],
            ];
            $accounts[$row['account_id']]['amount'] += $row['amount'];
            $accounts[$row['account_id']]['line_count']++;
            $accounts[$row['account_id']]['kinds'][$row['kind']] = self::KINDS[$row['kind']];
            $accounts[$row['account_id']]['lines'][] = $row;
        }
        $accountRows = array_values(array_map(fn (array $a) => [
            ...$a,
            'amount' => round($a['amount'], 2),
            'kinds' => array_values($a['kinds']),
            'share' => $total > 0 ? round($a['amount'] / $total * 100, 1) : 0.0,
        ], $accounts));
        usort($accountRows, fn (array $a, array $b) => $b['amount'] <=> $a['amount']);

        $kindRows = [];
        foreach (self::KINDS as $kind => $label) {
            $kindRows[] = ['kind' => $kind, 'label' => $label,
                'amount' => round(array_sum(array_column(array_filter($rows, fn ($r) => $r['kind'] === $kind), 'amount')), 2)];
        }

        $otherRows = $this->otherCostRows($companyId, $startDate, $endDate, $cardTransactions);

        return [
            'filters' => ['start_date' => $startDate, 'end_date' => $endDate],
            'totals' => [
                'amount' => $total,
                'line_count' => count($rows),
                'account_count' => count($accountRows),
                'transaction_count' => count(array_unique(array_column($rows, 'transaction_id'))),
                'other_amount' => round(array_sum(array_column($otherRows, 'amount')), 2),
            ],
            'kindRows' => $kindRows,
            'accountRows' => $accountRows,
            'detailRows' => $rows,
            'otherRows' => $otherRows,
        ];
    }

    /** Journal lines on live, posted transactions in the range, reversals and reversed ones left out. */
    private function lines(string $companyId, string $startDate, string $endDate)
    {
        return DB::table('acct.journal_entries as je')
            ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
            ->join('acct.accounts as a', 'a.id', '=', 'je.account_id')
            ->where('t.company_id', $companyId)
            ->whereIn('t.status', ['posted', 'locked'])
            ->whereNull('t.deleted_at')->whereNull('t.reversed_by_id')->whereNull('t.reversal_of_id')
            ->whereBetween('t.transaction_date', [$startDate, $endDate]);
    }

    private const COLUMNS = ['je.id as line_id', 'je.transaction_id', 'je.description as line_description', 'je.debit_amount', 'je.credit_amount',
        'a.id as account_id', 'a.code as account_code', 'a.name as account_name', 'a.type as account_type',
        't.transaction_number', 't.reference_id', 't.description as transaction_description', 't.transaction_date', 't.metadata'];

    private function row(object $r, string $kind, string $description, string $href): array
    {
        return [
            'kind' => $kind,
            'kind_label' => self::KINDS[$kind],
            'line_id' => $r->line_id,
            'transaction_id' => $r->transaction_id,
            'transaction_number' => $r->transaction_number,
            'date' => Carbon::parse($r->transaction_date)->toDateString(),
            'date_label' => Carbon::parse($r->transaction_date)->format('d M Y'),
            'account_id' => $r->account_id,
            'account_code' => $r->account_code,
            'account_name' => $r->account_name,
            'account_type' => $r->account_type,
            'description' => $description,
            'amount' => round((float) $r->debit_amount - (float) $r->credit_amount, 2),
            'href' => $href,
        ];
    }

    /** Entered as expenses: Daily Close > Money out > Expenses and Record Expense. */
    private function expenseLines(string $companyId, string $startDate, string $endDate): array
    {
        return $this->lines($companyId, $startDate, $endDate)
            ->where('t.transaction_type', 'expense')->where('je.debit_amount', '>', 0)
            ->get([...self::COLUMNS, 't.reference_type'])
            ->map(function ($r) {
                $metadata = is_string($r->metadata) ? (json_decode($r->metadata, true) ?: []) : [];
                $notes = is_string($metadata['notes'] ?? null) && $metadata['notes'] !== '' ? $metadata['notes'] : null;
                $from = $r->reference_type === 'fuel.daily_close_expense' ? 'Daily Close' : 'Record Expense';

                return $this->row($r, 'expense', ($notes ?? ($r->line_description ?: $r->transaction_description ?: 'Expense')).' · '.$from, "journals/{$r->transaction_id}");
            })
            ->filter(fn (array $row) => abs($row['amount']) > 0.005)->values()->all();
    }

    /** Approved payslips: the pay cost on each expense account, one line per payslip and account. */
    private function salaryLines(string $companyId, string $startDate, string $endDate): array
    {
        $lines = $this->lines($companyId, $startDate, $endDate)
            ->where('t.transaction_type', 'payroll_accrual')->whereIn('a.type', ['expense', 'other_expense'])
            ->get(self::COLUMNS);
        $employeeIds = $lines->map(fn ($r) => (is_string($r->metadata) ? json_decode($r->metadata, true) : [])['employee_id'] ?? null)->filter()->unique()->values()->all();
        $names = $employeeIds ? DB::table('pay.employees')->whereIn('id', $employeeIds)
            ->get(['id', 'first_name', 'last_name'])->mapWithKeys(fn ($e) => [$e->id => trim($e->first_name.' '.$e->last_name)])->all() : [];

        return $lines->map(function ($r) use ($names) {
            $metadata = is_string($r->metadata) ? (json_decode($r->metadata, true) ?: []) : [];
            $who = $names[$metadata['employee_id'] ?? ''] ?? null;
            $what = $r->line_description ?: 'Salary';

            return $this->row($r, 'salary', $who ? "{$who} · {$what}" : $r->transaction_description, $r->reference_id ? "payslips/{$r->reference_id}" : "journals/{$r->transaction_id}");
        })->filter(fn (array $row) => abs($row['amount']) > 0.005)->values()->all();
    }

    /** Card charges: the expense side of each charge's own transaction (a bill for supplier cards). */
    private function cardChargeLines(string $companyId, string $startDate, string $endDate, array $cardTransactions): array
    {
        if ($cardTransactions === []) {
            return [];
        }

        return $this->lines($companyId, $startDate, $endDate)
            ->whereIn('t.id', $cardTransactions)->whereIn('a.type', ['expense', 'other_expense'])
            ->get(self::COLUMNS)
            ->map(fn ($r) => $this->row($r, 'card_charge', $r->line_description ?: $r->transaction_description ?: 'Card charges', 'fuel/vendor-cards/pending'))
            ->filter(fn (array $row) => abs($row['amount']) > 0.005)->values()->all();
    }

    /**
     * Costs on expense accounts that are none of the above, per account and kind -- tank shrinkage,
     * cash short/over, bills, journals.
     *
     * @return array<int,array{account_id:string,account_code:string,account_name:string,label:string,amount:float}>
     */
    private function otherCostRows(string $companyId, string $startDate, string $endDate, array $cardTransactions): array
    {
        return $this->lines($companyId, $startDate, $endDate)
            ->whereIn('a.type', ['expense', 'other_expense'])
            ->whereNotIn('t.transaction_type', ['expense', 'payroll_accrual'])
            ->when($cardTransactions !== [], fn ($q) => $q->whereNotIn('t.id', $cardTransactions))
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
            'adjustment', 'fuel_variance', 'inventory_revaluation' => 'Stock adjustment',
            'manual' => 'Journal',
            default => str($transactionType)->replace('_', ' ')->title()->toString(),
        };
    }
}
