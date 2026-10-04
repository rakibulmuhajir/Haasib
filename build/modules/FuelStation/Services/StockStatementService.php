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
    /**
     * How a tank fuel's profit over the range is worked out, from the books: sales + closing stock
     * - opening stock - bought, with the litres behind it and the tank gain or loss the closing dip
     * holds. Null for anything but a tank fuel with a stock account of its own (shared accounts
     * cannot say what this one fuel's stock is worth).
     *
     * @param  array<string,mixed>  $report  this item's run() result
     * @return array<string,mixed>|null
     */
    public function profitWorking(string $companyId, string $itemId, string $startDate, string $endDate, array $report): ?array
    {
        if (empty($report['has_tank']) || ! empty($report['combined'])) {
            return null;
        }
        $accountId = DB::table('inv.items')->where('company_id', $companyId)->where('id', $itemId)->value('asset_account_id');
        if (! $accountId || DB::table('inv.items')->where('company_id', $companyId)->where('asset_account_id', $accountId)
            ->where('id', '!=', $itemId)->whereNull('deleted_at')->exists()) {
            return null;
        }
        $t = $report['totals'];
        $books = app(MonthEndStockValuationService::class);
        $openingValue = $books->accountBalance($companyId, $accountId, Carbon::parse($startDate)->subDay()->toDateString());
        $closingValue = $books->accountBalance($companyId, $accountId, $endDate);
        $opening = (float) ($t['opening'] ?? 0);
        $bought = (float) ($t['received'] ?? 0);
        $sold = (float) ($t['sold'] ?? 0);
        $closing = (float) ($t['closing'] ?? 0);
        $sales = round((float) ($t['sale_amount'] ?? 0), 2);
        $purchases = round((float) ($t['purchase_amount'] ?? 0), 2);
        $closingRate = $closing > 0 ? $closingValue / $closing : null;
        $shouldBeLeft = $opening + $bought - $sold;
        $variance = round($closing - $shouldBeLeft, 3);

        return [
            'method' => DB::table('fuel.station_settings')->where('company_id', $companyId)->value('month_end_stock_valuation') ?: 'inventory_cost',
            'opening_litres' => $opening, 'bought_litres' => $bought, 'sold_litres' => $sold,
            'should_be_left' => round($shouldBeLeft, 3), 'closing_litres' => $closing, 'variance_litres' => $variance,
            'sales' => $sales,
            'closing_value' => $closingValue, 'closing_rate' => $closingRate !== null ? round($closingRate, 4) : null,
            'opening_value' => $openingValue, 'opening_rate' => $opening > 0 ? round($openingValue / $opening, 4) : null,
            'purchases' => $purchases,
            'profit' => round($sales + $closingValue - $openingValue - $purchases, 2),
            'variance_value' => $closingRate !== null ? round($variance * $closingRate, 2) : null,
            // Litres in the tank the books give no value to (an opening never valued): the
            // profit above would count that stock as free, so the page says so instead.
            'unvalued' => ($opening > 0.0001 && abs($openingValue) < 0.005) || ($closing > 0.0001 && abs($closingValue) < 0.005),
        ];
    }

    public function run(string $companyId, string $itemId, string $startDate, string $endDate): array
    {
        $n = fn ($v) => (float) ($v ?? 0);
        $products = $this->products($companyId);
        $item = $itemId === '' ? null : DB::table('inv.items')->where('company_id', $companyId)->where('id', $itemId)->first(['id', 'name', 'fuel_category']);

        $result = [
            'item' => ['id' => $itemId, 'name' => $item->name ?? ''],
            'rows' => [],
            'totals' => ['opening' => null, 'received' => null, 'purchase_amount' => 0.0, 'purchase_rate' => null, 'sold' => 0.0, 'sale_amount' => 0.0, 'rate' => null, 'closing' => null, 'variance' => 0.0, 'opening_rate' => null, 'opening_value' => null, 'available' => null, 'available_value' => null],
            'products' => $products,
            'has_tank' => true,
        ];
        if (! $item) {
            return $result;
        }
        if (! DB::table('inv.warehouses')->where('company_id', $companyId)->where('warehouse_type', 'tank')->where('linked_item_id', $itemId)->exists()) {
            return $this->runBook($companyId, $item, $startDate, $endDate, $result);
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

        // What was bought, from the bills (by bill date) -- including litres sold straight off the
        // tanker, which never enter the tank and so never show in a close's dip arithmetic.
        $bought = $this->purchases($companyId, $itemId, $startDate, $endDate);
        $directSales = $this->directSales($companyId, $itemId, $startDate, $endDate, $bought);

        $today = now()->startOfDay();
        $rows = [];
        $prevDip = null;
        $first = true;
        $openingTotal = null;
        $closing = null;
        $tot = ['received' => null, 'purchase_amount' => 0.0, 'sold' => 0.0, 'sale_amount' => 0.0, 'variance' => 0.0];

        for ($d = Carbon::parse($startDate)->startOfDay(), $e = Carbon::parse($endDate)->startOfDay(); $d->lte($e); $d->addDay()) {
            $date = $d->toDateString();
            $t = $byDate[$date] ?? null;
            $dayBills = $bought[$date] ?? [];
            $dayBought = (float) array_sum(array_column($dayBills, 'quantity'));
            $dayDirect = (float) array_sum(array_column($dayBills, 'direct'));
            $dayPurchase = (float) array_sum(array_column($dayBills, 'amount'));
            $dayPurchaseRate = $dayBought > 0 ? round($dayPurchase / $dayBought, 2) : null;
            $dayDirectAmount = $directSales[$date]['amount'] ?? 0.0;
            if (! $t) {
                if ($d->lte($today)) {
                    // No close, but a purchase that day still counts as bought.
                    $tot['received'] = ($tot['received'] ?? 0.0) + $dayBought;
                    $tot['purchase_amount'] += $dayPurchase;
                    $tot['sold'] += $dayDirect;
                    $tot['sale_amount'] += $dayDirectAmount;
                    $rows[] = ['date' => $date, 'missing' => true, 'received' => $dayBought, 'bills' => $dayBills,
                        'sale_amount' => $dayDirectAmount, 'sale_running' => $tot['sale_amount'],
                        'purchase_amount' => $dayPurchase, 'purchase_rate' => $dayPurchaseRate, 'purchase_running' => $tot['purchase_amount']];
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
                $prevDip = $this->openingBefore($companyId, $startDate, $date, $tankIds, $itemId);
                $openingTotal = $prevDip;
            }
            $opening = $prevDip;
            $soldPumps = $sold;
            $sold += $dayDirect;
            $saleAmount += $dayDirectAmount;
            // The row reads across: opening + bought − sold = expected; the dip against it is the variance.
            $rowExpected = $opening === null ? $expected : $opening + $dayBought - $sold;

            $rows[] = [
                'date' => $date,
                'close_id' => $t->id,
                'transaction_number' => $t->transaction_number,
                'opening' => $opening,
                'received' => $dayBought,
                'received_direct' => $dayDirect,
                'purchase_amount' => $dayPurchase,
                'purchase_rate' => $dayPurchaseRate,
                'sold' => $sold,
                'sold_pumps' => $soldPumps,
                'sold_direct' => $dayDirect,
                'rates' => $rates,
                'sale_amount' => $saleAmount,
                'direct_amount' => $dayDirectAmount,
                'direct_invoices' => $directSales[$date]['invoices'] ?? [],
                'expected' => $rowExpected,
                'close_expected' => $expected,
                'dip' => $dip,
                'physical_reading_complete' => $tankIds !== [] && array_diff($tankIds, $ids) === []
                    && count(array_filter($tanks, fn ($tk) => ($tk['physical_liters'] ?? null) !== null)) === count($tanks),
                'variance' => $dip - $rowExpected,
                'bills' => $dayBills,
            ];

            $tot['received'] = ($tot['received'] ?? 0.0) + $dayBought;
            $tot['sold'] += $sold;
            $tot['sale_amount'] += $saleAmount;
            $tot['variance'] += $dip - $rowExpected;
            // Sales to date, like a running balance on a bank statement.
            $tot['purchase_amount'] += $dayPurchase;
            $rows[array_key_last($rows)]['sale_running'] = $tot['sale_amount'];
            $rows[array_key_last($rows)]['purchase_running'] = $tot['purchase_amount'];
            $prevDip = $dip;
            $first = false;
            $closing = $dip;
        }

        $result['rows'] = $rows;
        $result['totals'] = [
            'opening' => $openingTotal,
            // Opening stock at cost, and opening + bought: what was there to sell. Not folded into
            // "bought" -- this month's opening is last month's closing, already bought then.
            'opening_rate' => $openingRate = $this->openingRate($companyId, $itemId, $startDate, true),
            'opening_value' => $openingTotal !== null && $openingRate !== null ? round($openingTotal * $openingRate, 2) : null,
            'available' => $openingTotal === null ? null : $openingTotal + ($tot['received'] ?? 0.0),
            'available_value' => $openingTotal !== null && $openingRate !== null ? round($openingTotal * $openingRate, 2) + $tot['purchase_amount'] : null,
            'received' => $tot['received'],
            'sold' => $tot['sold'],
            'sale_amount' => $tot['sale_amount'],
            'rate' => $tot['sold'] > 0 ? round($tot['sale_amount'] / $tot['sold'], 2) : null,
            'purchase_amount' => $tot['purchase_amount'],
            'purchase_rate' => ($tot['received'] ?? 0) > 0 ? round($tot['purchase_amount'] / $tot['received'], 2) : null,
            'closing' => $closing,
            'variance' => $tot['variance'],
        ];

        return $result;
    }

    /**
     * What a litre of the opening stock cost: the last purchase before the range (weighted over
     * that bill date), else the opening-stock entry's own cost. Null when neither carries a cost.
     */
    private function openingRate(string $companyId, string $itemId, string $start, bool $tank): ?float
    {
        $bills = fn () => DB::table('acct.bill_line_items as l')
            ->join('acct.bills as b', 'b.id', '=', 'l.bill_id')
            ->where('b.company_id', $companyId)
            ->whereNull('b.deleted_at')->whereNull('l.deleted_at')
            ->whereNotIn('b.status', ['draft', 'void', 'cancelled'])
            ->where('l.item_id', $itemId)
            ->when($tank, fn ($q) => $q->whereNotNull('l.warehouse_id'))
            ->where('b.bill_date', '<', $start);
        $lastDate = $bills()->max('b.bill_date');
        if ($lastDate) {
            $row = $bills()->whereDate('b.bill_date', $lastDate)->selectRaw('SUM(l.total) as amount, SUM(l.quantity) as qty')->first();
            if ((float) $row->qty > 0) {
                return round((float) $row->amount / (float) $row->qty, 4);
            }
        }

        $opening = DB::table('inv.stock_movements')
            ->where('company_id', $companyId)
            ->where('item_id', $itemId)
            ->where('movement_type', 'opening')
            ->where('movement_date', '<', $start)
            ->where('unit_cost', '>', 0)
            ->selectRaw('SUM(total_cost) as amount, SUM(quantity) as qty')
            ->first();

        return $opening && (float) $opening->qty > 0 ? round((float) $opening->amount / (float) $opening->qty, 4) : null;
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
    private function openingBefore(string $companyId, string $rangeStart, string $firstDate, array $tankIds, string $itemId): ?float
    {
        $found = [];
        $earlier = $this->liveCloses($companyId)
            ->where('transaction_date', '<', $rangeStart)
            ->orderByDesc('transaction_date')->orderByDesc('created_at')
            ->get(['id', 'metadata']);
        foreach ($earlier as $t) {
            foreach ((array) ($t->metadata['posting_snapshot']['tanks'] ?? []) as $tk) {
                $id = $tk['tank_id'] ?? null;
                if ($id && in_array($id, $tankIds, true) && ! array_key_exists($id, $found) && ($tk['physical_liters'] ?? null) !== null) {
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
                // Only this product's opening: another item's opening entry may sit in the same tank.
                ->where('item_id', $itemId)
                ->whereIn('warehouse_id', $missing)
                ->where('movement_date', '<=', $firstDate)
                ->groupBy('warehouse_id')
                ->selectRaw('warehouse_id, SUM(quantity) as qty')
                ->pluck('qty', 'warehouse_id')->all();
            foreach ($stock as $id => $qty) {
                $found[$id] = (float) $qty;
            }
        }

        return $tankIds !== [] && count($found) === count($tankIds) ? array_sum($found) : null;
    }

    /**
     * Litres bought per bill date: every line for the product on a bill that counts (not draft,
     * void or cancelled), with how much of it was sold straight off the tanker. Only lines that
     * name a tank or store: a bill split between suppliers keeps its own lines, and its share
     * bills carry money only.
     *
     * @return array<string, array<int, array{id:string,bill_number:string,quantity:float,direct:float}>>
     */
    private function purchases(string $companyId, string $itemId, string $start, string $end, bool $tank = true): array
    {
        $out = [];
        $lines = DB::table('acct.bill_line_items as l')
            ->join('acct.bills as b', 'b.id', '=', 'l.bill_id')
            ->where('b.company_id', $companyId)
            ->whereNull('b.deleted_at')->whereNull('l.deleted_at')
            ->whereNotIn('b.status', ['draft', 'void', 'cancelled'])
            ->where('l.item_id', $itemId)
            ->when($tank, fn ($q) => $q->whereNotNull('l.warehouse_id'))
            ->whereBetween('b.bill_date', [$start, $end])
            ->orderBy('b.bill_number')
            ->get(['b.id', 'b.bill_number', 'b.bill_date', 'l.quantity', 'l.direct_quantity', 'l.total']);
        foreach ($lines as $l) {
            $date = Carbon::parse($l->bill_date)->toDateString();
            $out[$date][$l->id] ??= ['id' => $l->id, 'bill_number' => $l->bill_number, 'quantity' => 0.0, 'direct' => 0.0, 'amount' => 0.0];
            $out[$date][$l->id]['quantity'] += (float) $l->quantity;
            $out[$date][$l->id]['amount'] += (float) $l->total;
            $out[$date][$l->id]['direct'] += (float) $l->direct_quantity;
        }

        return array_map('array_values', $out);
    }

    /**
     * What the litres sold off the tanker were invoiced for, per day: direct-delivery invoice
     * lines for the product. Lines from before invoices carried an item are matched by date and
     * quantity to that day's direct litres on the product's bills.
     *
     * @param  array<string, array<int, array{direct:float}>>  $bought
     * @return array<string, array{amount:float, invoices:array}>
     */
    private function directSales(string $companyId, string $itemId, string $start, string $end, array $bought): array
    {
        $out = [];
        $lines = DB::table('acct.invoice_line_items as l')
            ->join('acct.invoices as i', 'i.id', '=', 'l.invoice_id')
            ->where('i.company_id', $companyId)
            ->where('i.is_direct_delivery', true)
            ->whereNull('i.deleted_at')->whereNull('l.deleted_at')
            ->whereNotIn('i.status', ['draft', 'void', 'cancelled'])
            ->whereBetween('i.invoice_date', [$start, $end])
            ->where(fn ($q) => $q->where('l.item_id', $itemId)->orWhereNull('l.item_id'))
            ->get(['i.id', 'i.invoice_number', 'i.invoice_date', 'l.item_id', 'l.quantity', 'l.total']);
        foreach ($lines as $l) {
            $date = Carbon::parse($l->invoice_date)->toDateString();
            if ($l->item_id === null) {
                $directs = array_map(fn ($b) => round($b['direct'], 3), $bought[$date] ?? []);
                if (! in_array(round((float) $l->quantity, 3), $directs, true)) {
                    continue;
                }
            }
            $out[$date] ??= ['amount' => 0.0, 'invoices' => []];
            $out[$date]['amount'] += (float) $l->total;
            $out[$date]['invoices'][] = ['id' => $l->id, 'invoice_number' => $l->invoice_number, 'quantity' => (float) $l->quantity, 'amount' => (float) $l->total];
        }

        return $out;
    }

    /** Every sellable item: tank products first, then the rest by name. */
    private function products(string $companyId): array
    {
        $tankIds = DB::table('inv.warehouses')->where('company_id', $companyId)->where('warehouse_type', 'tank')
            ->whereNotNull('linked_item_id')->pluck('linked_item_id')->unique()->all();

        $items = DB::table('inv.items')->where('company_id', $companyId)->where('is_sellable', true)->whereNull('deleted_at')
            ->orderBy('name')->get(['id', 'name', 'unit_of_measure'])
            ->map(fn ($i) => ['id' => $i->id, 'name' => $i->name, 'unit' => $i->unit_of_measure, 'has_tank' => in_array($i->id, $tankIds, true)])
            ->all();

        return array_merge(
            array_values(array_filter($items, fn ($p) => $p['has_tank'])),
            array_values(array_filter($items, fn ($p) => ! $p['has_tank'])),
        );
    }

    /**
     * An item without a tank: no dip, so a book balance (opening + bought - sold). Rows only for
     * days with activity; sold comes from the closes' other sales plus direct-delivery invoices.
     */
    private function runBook(string $companyId, object $item, string $startDate, string $endDate, array $result): array
    {
        $n = fn ($v) => (float) ($v ?? 0);
        $itemId = $item->id;
        $result['has_tank'] = false;

        $closes = $this->liveCloses($companyId)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date')->orderBy('created_at')
            ->get(['id', 'transaction_number', 'transaction_date', 'metadata']);
        $byDate = [];
        foreach ($closes as $t) {
            $byDate[Carbon::parse($t->transaction_date)->toDateString()] = $t;
        }

        $bought = $this->purchases($companyId, $itemId, $startDate, $endDate, false);
        $hasDirect = false;
        foreach ($bought as $dayBills) {
            if (array_sum(array_column($dayBills, 'direct')) > 0) {
                $hasDirect = true;
            }
        }
        $directSales = $hasDirect ? $this->directSales($companyId, $itemId, $startDate, $endDate, $bought) : [];

        // Book opening: opening-stock entries + earlier bills (net of what went straight off the
        // tanker) - earlier other sales.
        $openingStock = (float) DB::table('inv.stock_movements')->where('company_id', $companyId)->where('item_id', $itemId)
            ->where('movement_type', 'opening')->where('movement_date', '<', $startDate)->sum('quantity');
        $earlierBills = DB::table('acct.bill_line_items as l')
            ->join('acct.bills as b', 'b.id', '=', 'l.bill_id')
            ->where('b.company_id', $companyId)
            ->whereNull('b.deleted_at')->whereNull('l.deleted_at')
            ->whereNotIn('b.status', ['draft', 'void', 'cancelled'])
            ->where('l.item_id', $itemId)
            ->where('b.bill_date', '<', $startDate)
            ->selectRaw('COALESCE(SUM(l.quantity), 0) - COALESCE(SUM(l.direct_quantity), 0) as qty')->value('qty');
        $earlierSold = 0.0;
        foreach ($this->liveCloses($companyId)->where('transaction_date', '<', $startDate)->get(['id', 'metadata']) as $t) {
            foreach ((array) ($t->metadata['other_sales_details'] ?? []) as $o) {
                if (($o['item_id'] ?? null) === $itemId) {
                    $earlierSold += $n($o['quantity'] ?? 0);
                }
            }
        }
        $openingTotal = $openingStock + (float) $earlierBills - $earlierSold;

        $rows = [];
        $balance = $openingTotal;
        $tot = ['received' => 0.0, 'purchase_amount' => 0.0, 'sold' => 0.0, 'sale_amount' => 0.0];

        for ($d = Carbon::parse($startDate)->startOfDay(), $e = Carbon::parse($endDate)->startOfDay(); $d->lte($e); $d->addDay()) {
            $date = $d->toDateString();
            $t = $byDate[$date] ?? null;
            $dayBills = $bought[$date] ?? [];
            $dayBought = (float) array_sum(array_column($dayBills, 'quantity'));
            $dayDirect = (float) array_sum(array_column($dayBills, 'direct'));
            $dayPurchase = (float) array_sum(array_column($dayBills, 'amount'));
            $dayDirectAmount = $directSales[$date]['amount'] ?? 0.0;

            $soldClose = 0.0;
            $saleAmount = 0.0;
            $rates = [];
            if ($t) {
                foreach ((array) (((array) ($t->metadata ?? []))['other_sales_details'] ?? []) as $o) {
                    if (($o['item_id'] ?? null) !== $itemId) {
                        continue;
                    }
                    $soldClose += $n($o['quantity'] ?? 0);
                    $saleAmount += $n($o['amount'] ?? 0);
                    if (isset($o['unit_price'])) {
                        $rates[] = round($n($o['unit_price']), 2);
                    }
                }
            }
            $sold = $soldClose + $dayDirect;
            $saleAmount += $dayDirectAmount;
            if ($dayBought <= 0 && $sold <= 0) {
                continue;
            }
            $rates = array_values(array_unique($rates));
            sort($rates);

            $opening = $balance;
            $balance = $opening + $dayBought - $sold;
            $tot['received'] += $dayBought;
            $tot['purchase_amount'] += $dayPurchase;
            $tot['sold'] += $sold;
            $tot['sale_amount'] += $saleAmount;

            $rows[] = [
                'date' => $date,
                'close_id' => $t->id ?? null,
                'transaction_number' => $t->transaction_number ?? null,
                'opening' => $opening,
                'received' => $dayBought,
                'received_direct' => $dayDirect,
                'purchase_amount' => $dayPurchase,
                'purchase_rate' => $dayBought > 0 ? round($dayPurchase / $dayBought, 2) : null,
                'sold' => $sold,
                'sold_pumps' => $soldClose,
                'sold_direct' => $dayDirect,
                'rates' => $rates,
                'sale_amount' => $saleAmount,
                'direct_amount' => $dayDirectAmount,
                'direct_invoices' => $directSales[$date]['invoices'] ?? [],
                'expected' => $balance,
                'close_expected' => $balance,
                'dip' => $balance,
                'variance' => 0.0,
                'bills' => $dayBills,
                'book' => true,
                'sale_running' => $tot['sale_amount'],
                'purchase_running' => $tot['purchase_amount'],
            ];
        }

        $openingRate = $this->openingRate($companyId, $itemId, $startDate, false);
        $result['rows'] = $rows;
        $result['totals'] = [
            'opening' => $openingTotal,
            'opening_rate' => $openingRate,
            'opening_value' => $openingRate !== null ? round($openingTotal * $openingRate, 2) : null,
            'available' => $openingTotal + $tot['received'],
            'available_value' => $openingRate !== null ? round($openingTotal * $openingRate, 2) + $tot['purchase_amount'] : null,
            'received' => $tot['received'],
            'sold' => $tot['sold'],
            'sale_amount' => $tot['sale_amount'],
            'rate' => $tot['sold'] > 0 ? round($tot['sale_amount'] / $tot['sold'], 2) : null,
            'purchase_amount' => $tot['purchase_amount'],
            'purchase_rate' => $tot['received'] > 0 ? round($tot['purchase_amount'] / $tot['received'], 2) : null,
            'closing' => $balance,
            'variance' => 0.0,
        ];

        return $result;
    }

    /**
     * Several products in one statement: each product's rows (no gaps), tagged with the product and
     * its unit, date-sorted, with the totals summed. Units may differ, so rates are per unit.
     *
     * @param  array<int, string>  $itemIds
     */
    public function runMany(string $companyId, array $itemIds, string $start, string $end): array
    {
        $products = $this->products($companyId);
        $rows = [];
        $sum = ['received' => null, 'purchase_amount' => 0.0, 'sold' => 0.0, 'sale_amount' => 0.0, 'opening' => null, 'closing' => null, 'variance' => 0.0, 'opening_value' => null, 'available' => null, 'available_value' => null];
        $add = function (string $k, $v) use (&$sum) {
            if ($v !== null) {
                $sum[$k] = ($sum[$k] ?? 0.0) + (float) $v;
            }
        };
        $anyTank = false;
        $count = 0;

        foreach (array_values(array_unique($itemIds)) as $id) {
            $r = $this->run($companyId, $id, $start, $end);
            if (($r['item']['name'] ?? '') === '') {
                continue;
            }
            $count++;
            $unit = collect($products)->firstWhere('id', $id)['unit'] ?? null;
            $anyTank = $anyTank || ($r['has_tank'] ?? true);
            foreach ($r['rows'] as $row) {
                if (! empty($row['missing'])) {
                    continue;
                }
                $rows[] = $row + ['product' => $r['item']['name'], 'unit' => $unit];
            }
            foreach (['received', 'opening', 'closing', 'opening_value', 'available', 'available_value'] as $k) {
                $add($k, $r['totals'][$k] ?? null);
            }
            foreach (['purchase_amount', 'sold', 'sale_amount', 'variance'] as $k) {
                $add($k, $r['totals'][$k] ?? 0);
            }
        }

        usort($rows, fn ($a, $b) => [$a['date'], $a['product']] <=> [$b['date'], $b['product']]);
        $purchaseRunning = 0.0;
        $saleRunning = 0.0;
        foreach ($rows as &$row) {
            $purchaseRunning += (float) ($row['purchase_amount'] ?? 0);
            $saleRunning += (float) ($row['sale_amount'] ?? 0);
            $row['purchase_running'] = $purchaseRunning;
            $row['sale_running'] = $saleRunning;
        }
        unset($row);

        $received = $sum['received'];
        $all = $count > 0 && $count === count($products);

        return [
            'item' => ['id' => $all ? 'all' : 'some', 'name' => $all ? 'All products' : $count.' products'],
            'rows' => $rows,
            'totals' => [
                'opening' => $sum['opening'],
                'received' => $received,
                'purchase_amount' => $sum['purchase_amount'],
                'purchase_rate' => ($received ?? 0) > 0 ? round($sum['purchase_amount'] / $received, 2) : null,
                'sold' => $sum['sold'],
                'sale_amount' => $sum['sale_amount'],
                'rate' => $sum['sold'] > 0 ? round($sum['sale_amount'] / $sum['sold'], 2) : null,
                'closing' => $sum['closing'],
                'variance' => $sum['variance'],
                'opening_rate' => null,
                'opening_value' => $sum['opening_value'],
                'available' => $sum['available'],
                'available_value' => $sum['available_value'],
            ],
            'products' => $products,
            'has_tank' => $anyTank,
            'combined' => true,
        ];
    }
}
