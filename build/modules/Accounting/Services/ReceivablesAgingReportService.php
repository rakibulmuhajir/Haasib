<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Invoice;
use Carbon\CarbonImmutable;

/**
 * Who owes money, and how long they have owed it.
 *
 * A total receivable figure says nothing about whether it is collectable. Rs 2m owed
 * within terms and Rs 2m ninety days overdue are the same number and completely
 * different businesses. This is the weekly question at a fuel station, where credit
 * buyers are transport firms whose balances drift quietly until someone looks.
 *
 * Age runs from the due date, not the invoice date: an invoice on 30-day terms is not
 * overdue on day 31 of its life, it is overdue on day 31 after it fell due. An invoice
 * with no due date falls back to its invoice date, which is the conservative reading.
 */
class ReceivablesAgingReportService
{
    /** Upper bound of each overdue bucket, in days past due. The last bucket is open-ended. */
    public const BUCKETS = [30, 60, 90];

    /**
     * @return array{
     *   as_of: string,
     *   buckets: array<int, array{key:string,label:string}>,
     *   rows: array<int, array{customer_id:string,customer_name:string,customer_number:?string,
     *          current:float,d1_30:float,d31_60:float,d61_90:float,d90_plus:float,total:float,
     *          oldest_days_past_due:int}>,
     *   totals: array{current:float,d1_30:float,d31_60:float,d61_90:float,d90_plus:float,total:float},
     *   customer_count: int
     * }
     */
    public function run(string $companyId, string $asOf, bool $includeTrail = false): array
    {
        $asOfDate = CarbonImmutable::parse($asOf)->startOfDay();

        $invoices = Invoice::where('company_id', $companyId)
            ->whereNotIn('status', ['draft', 'void', 'cancelled'])
            ->where('balance', '>', 0)
            ->whereDate('invoice_date', '<=', $asOfDate->toDateString())
            ->with('customer:id,name,customer_number')
            ->orderBy('due_date')
            ->get();

        $builder = app(StatementValueTrail::class);
        $graph = ['nodes' => [], 'roots' => [], 'context' => ['label' => 'Ageing at '.$asOf.' using current saved balances']];
        $contributions = [];
        $rows = [];

        foreach ($invoices as $invoice) {
            $balance = round((float) $invoice->balance, 2);
            if ($balance <= 0) {
                continue;
            }

            $due = $invoice->due_date ?: $invoice->invoice_date;
            $daysPastDue = $due
                ? CarbonImmutable::parse($due)->startOfDay()->diffInDays($asOfDate, false)
                : 0;
            $daysPastDue = (int) max(0, $daysPastDue);

            $customerId = $invoice->customer_id ?? 'unassigned';
            if (! isset($rows[$customerId])) {
                $rows[$customerId] = [
                    'customer_id' => $customerId,
                    'customer_name' => $invoice->customer?->name ?? 'Unassigned',
                    'customer_number' => $invoice->customer?->customer_number,
                    'current' => 0.0,
                    'd1_30' => 0.0,
                    'd31_60' => 0.0,
                    'd61_90' => 0.0,
                    'd90_plus' => 0.0,
                    'total' => 0.0,
                    'oldest_days_past_due' => 0,
                ];
            }

            $bucket = $this->bucketFor($daysPastDue);
            $rows[$customerId][$bucket] += $balance;
            if ($includeTrail) {
                $id = $builder->node($graph, 'document:'.$invoice->id, 'Saved invoice balance', $balance,
                    source: ['document_link' => 'invoices/'.$invoice->id, 'label' => $invoice->invoice_number, 'date' => $invoice->invoice_date?->toDateString(), 'recorded_at' => $invoice->created_at?->toIso8601String()]);
                $graph['nodes'][$id]['explanation'] = 'Current saved unpaid balance. Aged from '.($due?->toDateString() ?? 'the document date').' at '.$asOf.'. This report does not reconstruct historical payment balances.';
                $contributions[$customerId][$bucket][] = $id;
            }
            $rows[$customerId]['total'] = round($rows[$customerId]['total'] + $balance, 2);
            $rows[$customerId]['oldest_days_past_due'] = max($rows[$customerId]['oldest_days_past_due'], $daysPastDue);
        }

        foreach ($rows as &$row) {
            foreach (['current', 'd1_30', 'd31_60', 'd61_90', 'd90_plus'] as $key) {
                $row[$key] = round($row[$key], 2);
            }
        }
        unset($row);

        // Worst first: the row a collector should act on today is the one at the top.
        $rows = array_values($rows);
        usort($rows, fn ($a, $b) => [$b['oldest_days_past_due'], $b['total']] <=> [$a['oldest_days_past_due'], $a['total']]);

        $totals = ['current' => 0.0, 'd1_30' => 0.0, 'd31_60' => 0.0, 'd61_90' => 0.0, 'd90_plus' => 0.0, 'total' => 0.0];
        foreach ($rows as $row) {
            foreach (array_keys($totals) as $key) {
                $totals[$key] = round($totals[$key] + $row[$key], 2);
            }
        }

        $result = [
            'as_of' => $asOfDate->toDateString(),
            'buckets' => [
                ['key' => 'current', 'label' => 'Not yet due'],
                ['key' => 'd1_30', 'label' => '1–30 days'],
                ['key' => 'd31_60', 'label' => '31–60 days'],
                ['key' => 'd61_90', 'label' => '61–90 days'],
                ['key' => 'd90_plus', 'label' => 'Over 90 days'],
            ],
            'rows' => $rows,
            'totals' => $totals,
            'customer_count' => count($rows),
        ];
        if ($includeTrail) {
            $labels = ['current' => 'Not yet due', 'd1_30' => '1 to 30 days overdue', 'd31_60' => '31 to 60 days overdue', 'd61_90' => '61 to 90 days overdue', 'd90_plus' => 'Over 90 days overdue', 'total' => 'Total unpaid'];
            $totalChildren = [];
            foreach ($rows as $row) {
                $partyId = $row['customer_id'];
                foreach (array_keys($totals) as $key) {
                    $children = $key === 'total' ? array_merge(...array_values($contributions[$partyId] ?? [])) : ($contributions[$partyId][$key] ?? []);
                    $id = $builder->node($graph, 'party:'.$partyId.':'.$key, $row['customer_name'].' · '.$labels[$key], $row[$key], $children, 'Sum of saved unpaid document balances');
                    $totalChildren[$key][] = $id;
                }
            }
            foreach ($totals as $key => $value) {
                $builder->node($graph, 'total:'.$key, $labels[$key], $value, $totalChildren[$key] ?? [], 'Sum of party amounts in this bucket');
            }
            $overdue = ['total:d1_30', 'total:d31_60', 'total:d61_90', 'total:d90_plus'];
            $builder->node($graph, 'total:overdue', 'Overdue', array_sum(array_map(fn ($id) => $graph['nodes'][$id]['value'], $overdue)), $overdue, 'Sum of overdue buckets');
            $result['valueTrail'] = $graph;
        }

        return $result;
    }

    private function bucketFor(int $daysPastDue): string
    {
        if ($daysPastDue <= 0) {
            return 'current';
        }
        if ($daysPastDue <= self::BUCKETS[0]) {
            return 'd1_30';
        }
        if ($daysPastDue <= self::BUCKETS[1]) {
            return 'd31_60';
        }
        if ($daysPastDue <= self::BUCKETS[2]) {
            return 'd61_90';
        }

        return 'd90_plus';
    }
}
