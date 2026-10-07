<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Bill;
use Carbon\CarbonImmutable;

/**
 * What we owe suppliers, and how long we have owed it: the mirror of
 * ReceivablesAgingReportService, from unpaid bills. Aged from the due date (the bill date
 * when there is none), worst first.
 */
class PayablesAgingReportService
{
    public function run(string $companyId, string $asOf, bool $includeTrail = false): array
    {
        $asOfDate = CarbonImmutable::parse($asOf)->startOfDay();

        $bills = Bill::where('company_id', $companyId)
            ->whereNotIn('status', ['draft', 'void', 'cancelled'])
            ->where('balance', '>', 0)
            ->whereDate('bill_date', '<=', $asOfDate->toDateString())
            ->with('vendor:id,name,vendor_number')
            ->orderBy('due_date')
            ->get();

        $buckets = ['current', 'd1_30', 'd31_60', 'd61_90', 'd90_plus'];
        $builder = app(StatementValueTrail::class);
        $graph = ['nodes' => [], 'roots' => [], 'context' => ['label' => 'Ageing at '.$asOf.' using current saved balances']];
        $contributions = [];
        $rows = [];
        foreach ($bills as $bill) {
            $balance = round((float) $bill->balance, 2);
            if ($balance <= 0) {
                continue;
            }
            $due = $bill->due_date ?: $bill->bill_date;
            $daysPastDue = (int) max(0, $due ? CarbonImmutable::parse($due)->startOfDay()->diffInDays($asOfDate, false) : 0);

            $vendorId = $bill->vendor_id ?? 'unassigned';
            $rows[$vendorId] ??= [
                'vendor_id' => $vendorId,
                'vendor_name' => $bill->vendor?->name ?? 'Unassigned',
                'vendor_number' => $bill->vendor?->vendor_number,
                ...array_fill_keys($buckets, 0.0),
                'total' => 0.0,
                'oldest_days_past_due' => 0,
            ];
            $bucket = match (true) {
                $daysPastDue <= 0 => 'current',
                $daysPastDue <= ReceivablesAgingReportService::BUCKETS[0] => 'd1_30',
                $daysPastDue <= ReceivablesAgingReportService::BUCKETS[1] => 'd31_60',
                $daysPastDue <= ReceivablesAgingReportService::BUCKETS[2] => 'd61_90',
                default => 'd90_plus',
            };
            $rows[$vendorId][$bucket] = round($rows[$vendorId][$bucket] + $balance, 2);
            if ($includeTrail) {
                $id = $builder->node($graph, 'document:'.$bill->id, 'Saved bill balance', $balance,
                    source: ['document_link' => 'bills/'.$bill->id, 'label' => $bill->bill_number, 'date' => $bill->bill_date?->toDateString(), 'recorded_at' => $bill->created_at?->toIso8601String()]);
                $graph['nodes'][$id]['explanation'] = 'Current saved unpaid balance. Aged from '.($due?->toDateString() ?? 'the document date').' at '.$asOf.'. This report does not reconstruct historical payment balances.';
                $contributions[$vendorId][$bucket][] = $id;
            }
            $rows[$vendorId]['total'] = round($rows[$vendorId]['total'] + $balance, 2);
            $rows[$vendorId]['oldest_days_past_due'] = max($rows[$vendorId]['oldest_days_past_due'], $daysPastDue);
        }

        $rows = array_values($rows);
        usort($rows, fn ($a, $b) => [$b['oldest_days_past_due'], $b['total']] <=> [$a['oldest_days_past_due'], $a['total']]);

        $totals = array_fill_keys([...$buckets, 'total'], 0.0);
        foreach ($rows as $row) {
            foreach (array_keys($totals) as $key) {
                $totals[$key] = round($totals[$key] + $row[$key], 2);
            }
        }

        $result = [
            'as_of' => $asOfDate->toDateString(),
            'rows' => $rows,
            'totals' => $totals,
            'vendor_count' => count($rows),
        ];
        if ($includeTrail) {
            $labels = ['current' => 'Not yet due', 'd1_30' => '1 to 30 days overdue', 'd31_60' => '31 to 60 days overdue', 'd61_90' => '61 to 90 days overdue', 'd90_plus' => 'Over 90 days overdue', 'total' => 'Total unpaid'];
            $totalChildren = [];
            foreach ($rows as $row) {
                $partyId = $row['vendor_id'];
                foreach (array_keys($totals) as $key) {
                    $children = $key === 'total' ? array_merge(...array_values($contributions[$partyId] ?? [])) : ($contributions[$partyId][$key] ?? []);
                    $id = $builder->node($graph, 'party:'.$partyId.':'.$key, $row['vendor_name'].' · '.$labels[$key], $row[$key], $children, 'Sum of saved unpaid document balances');
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
}
