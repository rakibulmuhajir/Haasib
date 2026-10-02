<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\Accounting\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One product's litres over a date range, read like a bank statement: an opening balance, one row
 * per posted Daily Close, and a closing line. Every figure is what the closes stored; received is
 * derived (expected - opening + sold) so each row reads across exactly as the close computed it.
 *
 * Tanks are inv.warehouses of type 'tank'; the item they hold is linked_item_id, and a tank's id
 * is the same uuid the close snapshot and the stock ledger use.
 */
class StockStatementService
{
    public function run(string $companyId, string $itemId, string $startDate, string $endDate): array
    {
        $n = fn ($v) => (float) ($v ?? 0);
        $products = $this->products($companyId);
        $item = $itemId === '' ? null : DB::table('inv.items')->where('company_id', $companyId)->where('id', $itemId)->first(['id', 'name', 'fuel_category']);

        $result = [
            'item' => ['id' => $itemId, 'name' => $item->name ?? ''],
            'rows' => [],
            'totals' => ['opening' => null, 'received' => null, 'sold' => 0.0, 'sale_amount' => 0.0, 'rate' => null, 'closing' => null, 'variance' => 0.0],
            'products' => $products,
        ];
        if (! $item) {
            return $result;
        }

        $closes = $this->liveCloses($companyId)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date')->orderBy('created_at')
            ->get(['id', 'transaction_number', 'transaction_date', 'metadata']);

        // One row per date: if a date somehow holds two live closes, the later one stands.
        $byDate = [];
        foreach ($closes as $t) {
            $byDate[Carbon::parse($t->transaction_date)->toDateString()] = $t;
        }

        // Tanks holding this product: the ledger's tanks, plus any the closes name for it.
        $tankIds = DB::table('inv.warehouses')->where('company_id', $companyId)->where('warehouse_type', 'tank')
            ->where('linked_item_id', $itemId)->pluck('id')->all();
        foreach ($byDate as $t) {
            foreach ((array) ($t->metadata['posting_snapshot']['tanks'] ?? []) as $tk) {
                if (($tk['item_id'] ?? null) === $itemId && ! empty($tk['tank_id'])) {
                    $tankIds[] = $tk['tank_id'];
                }
            }
        }
        $tankIds = array_values(array_unique($tankIds));

        $today = now()->startOfDay();
        $rows = [];
        $prevDip = null;
        $first = true;
        $openingTotal = null;
        $closing = null;
        $tot = ['received' => null, 'sold' => 0.0, 'sale_amount' => 0.0, 'variance' => 0.0];

        for ($d = Carbon::parse($startDate)->startOfDay(), $e = Carbon::parse($endDate)->startOfDay(); $d->lte($e); $d->addDay()) {
            $date = $d->toDateString();
            $t = $byDate[$date] ?? null;
            if (! $t) {
                if ($d->lte($today)) {
                    $rows[] = ['date' => $date, 'missing' => true];
                }

                continue;
            }

            $m = (array) ($t->metadata ?? []);
            $snap = (array) ($m['posting_snapshot'] ?? []);
            $mine = fn ($tk) => ($tk['item_id'] ?? null) === $itemId || in_array($tk['tank_id'] ?? null, $tankIds, true);
            $tanks = array_values(array_filter((array) ($snap['tanks'] ?? []), $mine));
            $ids = array_column($tanks, 'tank_id');
            $nozzles = array_values(array_filter((array) ($snap['nozzles'] ?? []), fn ($z) => in_array($z['tank_id'] ?? null, $ids, true)));
            $bare = array_values(array_diff($ids, array_column($nozzles, 'tank_id')));
            $others = array_values(array_filter((array) ($m['other_sales_details'] ?? []), fn ($o) => ($o['item_id'] ?? null) === $itemId));

            $sold = 0.0;
            $rates = [];
            foreach ($nozzles as $z) {
                $l = isset($z['liters_dispensed']) ? $n($z['liters_dispensed']) : $n($z['meter_liters'] ?? 0) - $n($z['returned_liters'] ?? 0);
                $sold += $l;
                if ($l > 0 && isset($z['sale_rate'])) {
                    $rates[] = round($n($z['sale_rate']), 2);
                }
            }
            $saleAmount = 0.0;
            if ($nozzles && $item->fuel_category) {
                $saleAmount += $n($m['fuel_sales'][$item->fuel_category]['revenue'] ?? 0);
            }
            if ($bare) {
                foreach ($others as $o) {
                    $sold += $n($o['quantity'] ?? 0);
                    $saleAmount += $n($o['amount'] ?? 0);
                    if (isset($o['unit_price'])) {
                        $rates[] = round($n($o['unit_price']), 2);
                    }
                }
            }
            $rates = array_values(array_unique($rates));
            sort($rates);

            $expected = array_sum(array_map(fn ($tk) => $n($tk['expected_liters'] ?? 0), $tanks));
            $dip = array_sum(array_map(fn ($tk) => $n($tk['physical_liters'] ?? 0), $tanks));

            if ($first) {
                $prevDip = $this->openingBefore($companyId, $startDate, $date, $tankIds);
                $openingTotal = $prevDip;
            }
            $opening = $prevDip;
            $received = $opening === null ? null : $expected - $opening + $sold;

            $rows[] = [
                'date' => $date,
                'close_id' => $t->id,
                'transaction_number' => $t->transaction_number,
                'opening' => $opening,
                'received' => $received,
                'sold' => $sold,
                'rates' => $rates,
                'sale_amount' => $saleAmount,
                'expected' => $expected,
                'dip' => $dip,
                'variance' => $dip - $expected,
                'bills' => $this->bills($companyId, $tankIds, $date),
            ];

            if ($received !== null) {
                $tot['received'] = ($tot['received'] ?? 0.0) + $received;
            }
            $tot['sold'] += $sold;
            $tot['sale_amount'] += $saleAmount;
            $tot['variance'] += $dip - $expected;
            $prevDip = $dip;
            $first = false;
            $closing = $dip;
        }

        $result['rows'] = $rows;
        $result['totals'] = [
            'opening' => $openingTotal,
            'received' => $tot['received'],
            'sold' => $tot['sold'],
            'sale_amount' => $tot['sale_amount'],
            'rate' => $tot['sold'] > 0 ? round($tot['sale_amount'] / $tot['sold'], 2) : null,
            'closing' => $closing,
            'variance' => $tot['variance'],
        ];

        return $result;
    }

    private function liveCloses(string $companyId)
    {
        return Transaction::where('company_id', $companyId)
            ->where('transaction_type', 'fuel_daily_close')
            ->where('status', 'posted')
            ->whereNull('deleted_at')
            ->whereNull('reversed_by_id');
    }

    /** Litres in the product's tanks as the first row's day opens: the last count before the range, else opening stock. */
    private function openingBefore(string $companyId, string $rangeStart, string $firstDate, array $tankIds): ?float
    {
        $found = [];
        $earlier = $this->liveCloses($companyId)
            ->where('transaction_date', '<', $rangeStart)
            ->orderByDesc('transaction_date')->orderByDesc('created_at')
            ->get(['id', 'metadata']);
        foreach ($earlier as $t) {
            foreach ((array) ($t->metadata['posting_snapshot']['tanks'] ?? []) as $tk) {
                $id = $tk['tank_id'] ?? null;
                if ($id && in_array($id, $tankIds, true) && ! array_key_exists($id, $found)) {
                    $found[$id] = (float) ($tk['physical_liters'] ?? 0);
                }
            }
            if ($tankIds && count($found) === count($tankIds)) {
                break;
            }
        }

        $missing = array_values(array_diff($tankIds, array_keys($found)));
        if ($missing) {
            $stock = DB::table('inv.stock_movements')
                ->where('company_id', $companyId)
                ->where('movement_type', 'opening')
                ->whereIn('warehouse_id', $missing)
                ->where('movement_date', '<', $firstDate)
                ->groupBy('warehouse_id')
                ->selectRaw('warehouse_id, SUM(quantity) as qty')
                ->pluck('qty', 'warehouse_id')->all();
            foreach ($stock as $id => $qty) {
                $found[$id] = (float) $qty;
            }
        }

        return $found ? array_sum($found) : null;
    }

    /** Receipts into the product's tanks on one day, one entry per bill. */
    private function bills(string $companyId, array $tankIds, string $date): array
    {
        if (! $tankIds) {
            return [];
        }

        return DB::table('inv.stock_movements as sm')
            ->leftJoin('acct.bills as b', 'b.id', '=', 'sm.reference_id')
            ->where('sm.company_id', $companyId)
            ->where('sm.movement_type', 'purchase')
            ->whereIn('sm.warehouse_id', $tankIds)
            ->whereDate('sm.movement_date', $date)
            ->where('sm.reference_type', 'acct.bills')
            ->groupBy('sm.reference_id', 'b.bill_number')
            ->selectRaw('sm.reference_id as id, b.bill_number, SUM(sm.quantity) as quantity')
            ->orderBy('b.bill_number')
            ->get()
            ->map(fn ($r) => ['id' => $r->id, 'bill_number' => $r->bill_number, 'quantity' => (float) $r->quantity])
            ->all();
    }

    /** Items that own at least one tank. */
    private function products(string $companyId): array
    {
        $ids = DB::table('inv.warehouses')->where('company_id', $companyId)->where('warehouse_type', 'tank')
            ->whereNotNull('linked_item_id')->pluck('linked_item_id')->unique()->all();

        return $ids
            ? DB::table('inv.items')->where('company_id', $companyId)->whereIn('id', $ids)->orderBy('name')
                ->get(['id', 'name'])->map(fn ($i) => ['id' => $i->id, 'name' => $i->name])->all()
            : [];
    }
}
