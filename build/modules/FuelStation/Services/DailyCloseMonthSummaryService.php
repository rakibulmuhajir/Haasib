<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\Accounting\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One month of posted Daily Closes added up into the same four-part sheet a single day shows.
 *
 * Only live closes count: posted, not soft-deleted (an edited day leaves its old versions
 * behind), not reversed. Every figure is read from what the closes stored; nothing is forced
 * to balance, so closing = opening + in - out + short/over holds only if the closes chain.
 */
class DailyCloseMonthSummaryService
{
    /** How a day sheet names something recorded on another screen; the month uses the same words. */
    private const OTHER_SCREEN_LABELS = [
        'invoice' => 'Invoice', 'payment' => 'Customer payment', 'bill' => 'Bill',
        'bill_payment' => 'Supplier payment', 'expense' => 'Expense',
    ];

    public function run(string $companyId, string $month): array
    {
        $start = Carbon::createFromFormat('!Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $today = now()->startOfDay();

        $closes = Transaction::where('company_id', $companyId)
            ->where('transaction_type', 'fuel_daily_close')
            ->where('status', 'posted')
            ->whereNull('deleted_at')
            ->whereNull('reversed_by_id')
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('transaction_date')->orderBy('created_at')
            ->get();

        $rows = $closes->map(fn (Transaction $t) => [
            'id' => $t->id,
            'number' => $t->transaction_number,
            'date' => Carbon::parse($t->transaction_date)->toDateString(),
            'm' => (array) ($t->metadata ?? []),
        ])->values();

        $countedDates = $rows->pluck('date')->unique()->all();
        $missing = [];
        for ($d = $start->copy(); $d->lte($end) && $d->lte($today); $d->addDay()) {
            if (! in_array($d->toDateString(), $countedDates, true)) {
                $missing[] = $d->toDateString();
            }
        }

        $next = $start->copy()->addMonth();
        $result = [
            'month' => $start->format('Y-m'),
            'label' => $start->format('F Y'),
            'prev_month' => $start->copy()->subMonth()->format('Y-m'),
            'next_month' => $next->gt($today) ? null : $next->format('Y-m'),
            'days_in_month' => $start->daysInMonth,
            'close_count' => $rows->count(),
            'missing_dates' => $missing,
            'closes' => $rows->map(fn ($r) => ['id' => $r['id'], 'transaction_number' => $r['number'], 'date' => $r['date']])->all(),
        ];

        if ($rows->isEmpty()) {
            return $result + [
                'cash' => ['opening' => 0, 'money_in' => 0, 'money_out' => 0, 'short_over' => 0, 'closing' => 0, 'short_days' => 0, 'over_days' => 0],
                'sales' => [], 'sales_total' => 0, 'tanks' => [],
                'money_in' => [], 'money_in_total' => 0, 'money_out' => [], 'money_out_total' => 0,
            ];
        }

        $n = fn ($v) => (float) ($v ?? 0);
        $tot = fn (array $m, string $key) => $n(($m['posting_snapshot']['totals'][$key] ?? null) ?? ($m[$key] ?? null));

        // ---- cash ----
        $first = $rows->first()['m'];
        $last = $rows->last()['m'];
        $moneyIn = $rows->sum(fn ($r) => $tot($r['m'], 'money_in'));
        $moneyOut = $rows->sum(fn ($r) => $tot($r['m'], 'money_out'));
        $variances = $rows->map(fn ($r) => $tot($r['m'], 'variance'));
        $cash = [
            'opening' => $tot($first, 'opening_cash'),
            'money_in' => (float) $moneyIn,
            'money_out' => (float) $moneyOut,
            'short_over' => (float) $variances->sum(),
            'closing' => $tot($last, 'closing_cash'),
            'short_days' => $variances->filter(fn ($v) => round($v) < 0)->count(),
            'over_days' => $variances->filter(fn ($v) => round($v) > 0)->count(),
            // Each day that did not balance, so the month's short/over can be traced to its days.
            'variance_days' => $rows->map(fn ($r) => [
                'id' => $r['id'],
                'date' => $r['date'],
                'amount' => $tot($r['m'], 'variance'),
            ])->filter(fn ($d) => round($d['amount']) != 0)->values()->all(),
        ];

        // ---- names ----
        $accountIds = [];
        foreach ($rows as $r) {
            $m = $r['m'];
            foreach (['bank_withdrawals_by_account', 'bank_deposits_by_account'] as $k) {
                $accountIds = array_merge($accountIds, array_keys((array) ($m[$k] ?? [])));
            }
            foreach ((array) ($m['form_input']['expenses'] ?? []) as $e) {
                $accountIds[] = $e['account_id'] ?? null;
            }
        }
        $accountIds = array_values(array_unique(array_filter($accountIds)));
        $accountNames = $accountIds
            ? DB::table('acct.accounts')->whereIn('id', $accountIds)->pluck('name', 'id')->all()
            : [];
        $accName = fn ($id) => $accountNames[$id] ?? 'Account';

        $fuelNames = DB::table('inv.items')->where('company_id', $companyId)
            ->whereNotNull('fuel_category')->pluck('name', 'fuel_category')->all();

        // ---- grouping helper: label + detail => amount and distinct days ----
        $group = function (array &$bag, string $label, ?string $detail, float $amount, string $date): void {
            if (abs($amount) < 1e-9) {
                return;
            }
            $key = $label."\0".($detail ?? '');
            $bag[$key] ??= ['label' => $label, 'detail' => $detail, 'amount' => 0.0, 'dates' => []];
            $bag[$key]['amount'] += $amount;
            $bag[$key]['dates'][$date] = true;
        };
        // Lines of one kind stay together (kinds in the order they first appear), largest first.
        $finish = function (array $bag): array {
            $order = array_flip(array_values(array_unique(array_column($bag, 'label'))));
            $lines = array_values(array_map(fn ($g) => [
                'label' => $g['label'],
                'detail' => $g['detail'],
                'amount' => $g['amount'],
                'days' => count($g['dates']),
            ], $bag));
            usort($lines, fn ($a, $b) => [$order[$a['label']], -$a['amount']] <=> [$order[$b['label']], -$b['amount']]);

            return $lines;
        };

        // ---- sales ----
        $fuel = [];
        $other = [];
        foreach ($rows as $r) {
            foreach ((array) ($r['m']['fuel_sales'] ?? []) as $cat => $f) {
                $fuel[$cat] ??= ['liters' => 0.0, 'revenue' => 0.0];
                $fuel[$cat]['liters'] += $n($f['liters'] ?? 0);
                $fuel[$cat]['revenue'] += $n($f['revenue'] ?? 0);
            }
            foreach ((array) ($r['m']['other_sales_details'] ?? []) as $o) {
                $name = $o['item_name'] ?? 'Other sale';
                $other[$name] ??= ['qty' => 0.0, 'amount' => 0.0];
                $other[$name]['qty'] += $n($o['quantity'] ?? 0);
                $other[$name]['amount'] += $n($o['amount'] ?? 0);
            }
        }
        $fmt = fn (float $v) => rtrim(rtrim(number_format($v, 2, '.', ','), '0'), '.');
        $sales = [];
        foreach ($fuel as $cat => $f) {
            $sales[] = ['label' => $fuelNames[$cat] ?? ucwords(str_replace('_', ' ', (string) $cat)), 'detail' => $fmt($f['liters']).' L', 'amount' => $f['revenue']];
        }
        foreach ($other as $name => $o) {
            $avg = $o['qty'] > 0 ? $o['amount'] / $o['qty'] : 0;
            $sales[] = ['label' => (string) $name, 'detail' => $fmt($o['qty']).' × '.$fmt($avg), 'amount' => $o['amount']];
        }
        $salesTotal = (float) $rows->sum(fn ($r) => $tot($r['m'], 'total_revenue'));

        // ---- money in / out ----
        $in = [];
        $out = [];
        $otherSales = [];
        foreach ($rows as $r) {
            $m = $r['m'];
            $d = $r['date'];
            $group($in, 'Meter sales', null, $n($m['total_revenue'] ?? 0), $d);
            $group($in, 'Lubricants & other sales', null, $n($m['other_sales'] ?? 0), $d);
            foreach ((array) ($m['bank_withdrawals_by_account'] ?? []) as $id => $a) {
                $group($in, 'Cash withdrawn from bank', $accName($id), $n($a), $d);
            }
            foreach ((array) ($m['payments_received_details'] ?? []) as $p) {
                $group($in, 'Payment received', $p['customer_name'] ?? null, $n($p['amount'] ?? 0), $d);
            }
            $group($in, 'Partner deposits', null, $n($m['partner_deposits'] ?? 0), $d);
            foreach ((array) ($m['amanat_deposit_details'] ?? []) as $a) {
                $group($in, 'Amanat deposit', $a['customer_name'] ?? null, $n($a['amount'] ?? 0), $d);
            }
            foreach ((array) ($m['other_deposit_details'] ?? []) as $o) {
                $group($in, 'Other cash in', ($o['description'] ?? null) ?: ($o['deposit_type'] ?? null), $n($o['amount'] ?? 0), $d);
            }

            $group($out, 'Credit sales', null, $n($m['credit_sales_total'] ?? 0), $d);
            foreach ((array) ($m['payment_receipt_postings'] ?? []) as $p) {
                $group($out, $p['channel_label'] ?? 'Card / bank sale', null, $n($p['amount'] ?? 0), $d);
            }
            foreach ((array) ($m['bank_deposits_by_account'] ?? []) as $id => $a) {
                $group($out, 'Bank deposit', $accName($id), $n($a), $d);
            }
            foreach ((array) ($m['pay_supplier_details'] ?? []) as $p) {
                $group($out, 'Paid supplier', $p['vendor_name'] ?? null, $n($p['amount'] ?? 0), $d);
            }
            foreach ((array) ($m['bill_payment_details'] ?? []) as $b) {
                $group($out, 'Supplier bill payment', $b['vendor_name'] ?? null, $n($b['amount'] ?? 0), $d);
            }
            foreach ((array) ($m['amanat_disbursement_details'] ?? []) as $a) {
                $group($out, 'Amanat withdrawal', $a['customer_name'] ?? null, $n($a['amount'] ?? 0), $d);
            }
            foreach ((array) ($m['form_input']['expenses'] ?? []) as $e) {
                $group($out, 'Expense', $accName($e['account_id'] ?? null), $n($e['amount'] ?? 0), $d);
            }
            $group($out, 'Partner withdrawals', null, $n($m['partner_withdrawals'] ?? 0), $d);
            $group($out, 'Salary advances', null, $n($m['employee_advances'] ?? 0), $d);
            foreach ((array) ($m['payroll_payout_details'] ?? []) as $p) {
                $group($out, 'Salary paid', $p['employee_name'] ?? null, $n($p['amount'] ?? 0), $d);
            }

            // Recorded on other screens that day, as the close read it -- the same filter the
            // day sheet uses: stock movements and the close's own expenses / supplier payments
            // are already counted above; its inline purchases are not.
            $ownExpenses = (array) ($m['expense_transaction_ids'] ?? []);
            foreach ((array) ($m['posting_snapshot']['sources'] ?? []) as $s) {
                $type = (string) ($s['type'] ?? '');
                $source = (string) ($s['source'] ?? '');
                if (str_starts_with($type, 'stock:') || in_array($s['id'] ?? null, $ownExpenses, true)
                    || (str_starts_with($source, 'close_') && $source !== 'close_purchase')) {
                    continue;
                }
                $label = self::OTHER_SCREEN_LABELS[$type] ?? ucfirst(str_replace(['_', ':'], ' ', $type));
                $group($otherSales, $label, null, $n($s['sales'] ?? 0), $d);
                if ($n($s['money_in'] ?? 0) > 0) {
                    $group($in, $label, 'other screens', $n($s['money_in']), $d);
                }
                if ($n($s['money_out'] ?? 0) > 0) {
                    $group($out, $label, 'other screens', $n($s['money_out']), $d);
                }
            }
        }
        foreach ($finish($otherSales) as $line) {
            $sales[] = ['label' => $line['label'] === 'Invoice' ? 'Direct / invoiced sales' : $line['label'], 'detail' => $line['days'].' days', 'amount' => $line['amount']];
        }
        $diff = $salesTotal - array_sum(array_column($sales, 'amount'));
        if (abs($diff) >= 1) {
            $sales[] = ['label' => 'Other sales', 'detail' => null, 'amount' => round($diff)];
        }
        $inLines = array_merge(
            [['label' => 'Opening cash', 'detail' => $start->format('j M'), 'amount' => $cash['opening'], 'days' => 1]],
            $finish($in),
        );
        $moneyInTotal = $cash['opening'] + $cash['money_in'];
        $diff = $moneyInTotal - array_sum(array_column($inLines, 'amount'));
        if (abs($diff) >= 1) {
            $inLines[] = ['label' => 'Other money in', 'detail' => null, 'amount' => round($diff), 'days' => 0];
        }
        $outLines = $finish($out);
        $diff = $cash['money_out'] - array_sum(array_column($outLines, 'amount'));
        if (abs($diff) >= 1) {
            $outLines[] = ['label' => 'Other money out', 'detail' => null, 'amount' => round($diff), 'days' => 0];
        }

        // ---- tanks ----
        $tanks = [];
        $sold = [];
        foreach ($rows as $r) {
            foreach ((array) ($r['m']['posting_snapshot']['tanks'] ?? []) as $t) {
                $id = $t['tank_id'] ?? null;
                if (! $id) {
                    continue;
                }
                $tanks[$id] ??= ['id' => $id, 'name' => $t['tank_name'] ?? 'Tank', 'closing' => null, 'variance' => 0.0];
                $tanks[$id]['name'] = $t['tank_name'] ?? $tanks[$id]['name'];
                $tanks[$id]['closing'] = $n($t['physical_liters'] ?? 0);
                $tanks[$id]['variance'] += $n($t['variance_liters'] ?? 0);
            }
            foreach ((array) ($r['m']['posting_snapshot']['nozzles'] ?? []) as $z) {
                $id = $z['tank_id'] ?? null;
                if (! $id) {
                    continue;
                }
                $sold[$id] = ($sold[$id] ?? 0.0) + (isset($z['liters_dispensed'])
                    ? $n($z['liters_dispensed'])
                    : $n($z['meter_liters'] ?? 0) - $n($z['returned_liters'] ?? 0));
            }

            // A tank with no nozzle (open lubricant) is drawn down by its product's other sales.
            $nozzleTanks = array_column((array) ($r['m']['posting_snapshot']['nozzles'] ?? []), 'tank_id');
            $tankByItem = [];
            foreach ((array) ($r['m']['posting_snapshot']['tanks'] ?? []) as $t) {
                if (! empty($t['item_id']) && ! empty($t['tank_id']) && ! in_array($t['tank_id'], $nozzleTanks, true)) {
                    $tankByItem[$t['item_id']] = $t['tank_id'];
                }
            }
            foreach ((array) ($r['m']['other_sales_details'] ?? []) as $o) {
                $id = $tankByItem[$o['item_id'] ?? ''] ?? null;
                if ($id) {
                    $sold[$id] = ($sold[$id] ?? 0.0) + $n($o['quantity'] ?? 0);
                }
            }
        }

        $openings = [];
        $before = Transaction::where('company_id', $companyId)
            ->where('transaction_type', 'fuel_daily_close')->where('status', 'posted')
            ->whereNull('deleted_at')->whereNull('reversed_by_id')
            ->where('transaction_date', '<', $start->toDateString())
            ->orderByDesc('transaction_date')->orderByDesc('created_at')
            ->get(['id', 'metadata']);
        foreach ($before as $t) {
            foreach ((array) ($t->metadata['posting_snapshot']['tanks'] ?? []) as $tk) {
                $id = $tk['tank_id'] ?? null;
                if ($id && isset($tanks[$id]) && ! array_key_exists($id, $openings)) {
                    $openings[$id] = $n($tk['physical_liters'] ?? 0);
                }
            }
            if (count($openings) === count($tanks)) {
                break;
            }
        }

        // The station's first month has no earlier close: its tanks open on the opening stock.
        $noOpening = array_values(array_diff(array_keys($tanks), array_keys($openings)));
        if ($noOpening) {
            $stock = DB::table('inv.stock_movements')
                ->where('company_id', $companyId)
                ->where('movement_type', 'opening')
                ->whereIn('warehouse_id', $noOpening)
                ->where('movement_date', '<', $start->toDateString())
                ->groupBy('warehouse_id')
                ->selectRaw('warehouse_id, SUM(quantity) as qty')
                ->pluck('qty', 'warehouse_id')->all();
            foreach ($stock as $id => $qty) {
                $openings[$id] = (float) $qty;
            }
        }

        // What actually went into each tank, from the stock ledger -- not what was billed: a bill's
        // litres can be sold straight off the tanker, or not yet received.
        $delivered = $tanks ? DB::table('inv.stock_movements')
            ->where('company_id', $companyId)
            ->where('movement_type', 'purchase')
            ->whereIn('warehouse_id', array_keys($tanks))
            ->whereBetween('movement_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('warehouse_id')
            ->selectRaw('warehouse_id as tank_id, SUM(quantity) as qty')
            ->pluck('qty', 'tank_id')->all() : [];

        $tankRows = [];
        foreach ($tanks as $id => $t) {
            $opening = $openings[$id] ?? null;
            $del = (float) ($delivered[$id] ?? 0);
            $s = (float) ($sold[$id] ?? 0);
            $expected = $opening === null ? null : $opening + $del - $s;
            $tankRows[] = [
                'name' => $t['name'],
                'opening' => $opening,
                'delivered' => $del,
                'sold' => $s,
                'expected' => $expected,
                'closing' => $t['closing'],
                // The row's own arithmetic, so it reads across; what the daily dips posted rides along.
                'variance' => $expected === null ? $t['variance'] : $t['closing'] - $expected,
                'daily_variance' => $t['variance'],
            ];
        }

        return $result + [
            'cash' => $cash,
            'sales' => $sales,
            'sales_total' => $salesTotal,
            'tanks' => $tankRows,
            'money_in' => $inLines,
            'money_in_total' => $moneyInTotal,
            'money_out' => $outLines,
            'money_out_total' => $cash['money_out'],
        ];
    }
}
