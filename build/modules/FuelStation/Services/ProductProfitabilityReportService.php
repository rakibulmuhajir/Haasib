<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Models\TankReading;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProductProfitabilityReportService
{
    /** @var array<string,array<string,mixed>> StockStatementService::run results of this run, by item id. */
    private array $statements = [];

    /** @var array<int,string>|null Product keys of the category being reported, null for every product. */
    private ?array $categoryKeys = null;

    private ?ProfitValueTrail $trail = null;

    /**
     * @return array{
     *   filters: array<string,string>,
     *   totals: array<string,float|int>,
     *   productRows: array<int,array<string,mixed>>,
     *   periodRows: array<int,array<string,mixed>>,
     *   rateChangeRows: array<int,array<string,mixed>>,
     *   productOptions: array<int,array{key:string,name:string}>
     * }
     */
    public function run(string $companyId, string $startDate, string $endDate, string $groupBy = 'day', string $product = 'all', ?string $categoryId = null, bool $includeTrail = false): array
    {
        $groupBy = in_array($groupBy, ['day', 'week', 'month'], true) ? $groupBy : 'day';
        $this->statements = [];
        $this->trail = $includeTrail ? app(ProfitValueTrail::class) : null;
        $items = $this->items($companyId);
        $category = null;
        $this->categoryKeys = null;
        if ($categoryId) {
            $category = ItemCategory::where('company_id', $companyId)->find($categoryId);
            $this->categoryKeys = $category
                ? array_keys(array_filter($items, fn ($i) => ($i['category_id'] ?? null) === $categoryId))
                : [];
        }
        $transactions = Transaction::where('company_id', $companyId)
            ->where('transaction_type', 'fuel_daily_close')
            ->where('status', 'posted')
            ->whereNull('deleted_at')
            ->whereNull('reversed_by_id')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date')
            ->get(['id', 'transaction_number', 'transaction_date', 'metadata', 'posted_at', 'posted_by_user_id', 'created_at', 'created_by_user_id']);

        // Posted cost corrections (fuel:recost-closes) laid over each close's own figures.
        app(DailyCloseCostCorrectionService::class)->applyTo($companyId, $transactions);
        $trailCorrections = $includeTrail
            ? app(DailyCloseCostCorrectionService::class)->correctionsFor($companyId, $transactions->pluck('id')->all())
            : [];
        // Month-end lubricant cost corrections (fuel:lubricant-cost): unit cost per item, by month,
        // for other-sale lines a close posted without a cost.
        $lubricantCosts = app(LubricantCostService::class)->unitCostsByMonth($companyId);

        $products = [];
        $periods = [];
        $rateChangeRows = [];

        foreach ($transactions as $transaction) {
            $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
            $date = $transaction->transaction_date instanceof Carbon
                ? $transaction->transaction_date->copy()
                : Carbon::parse($transaction->transaction_date);

            foreach ($this->fuelSalesRows($metadata['fuel_sales'] ?? [], $items) as $row) {
                if ($this->skipsKey($row['key'], $product)) {
                    continue;
                }

                $this->addProductSale($products, $row);
                $this->addPeriodSale($periods, $date, $groupBy, $row, $transaction->id, $transaction->transaction_number);
                $this->trail?->closeSale($row, $transaction, $items[$row['key']]['id'] ?? null, $this->periodKey($date, $groupBy), $trailCorrections[$transaction->id] ?? []);
            }

            foreach ($this->otherSalesRows($metadata['other_sales_details'] ?? [], $items, $lubricantCosts[$date->format('Y-m')] ?? []) as $row) {
                if ($this->skipsKey($row['key'], $product)) {
                    continue;
                }

                $this->addProductSale($products, $row);
                $this->addPeriodSale($periods, $date, $groupBy, $row, $transaction->id, $transaction->transaction_number);
                $this->trail?->closeSale($row, $transaction, $items[$row['key']]['id'] ?? null, $this->periodKey($date, $groupBy), []);
            }

            foreach (($metadata['rate_change_segments'] ?? []) as $segmentIndex => $segment) {
                if (! is_array($segment)) {
                    continue;
                }

                $key = $this->itemKey($segment['item_id'] ?? null, $items, $segment['item_name'] ?? null);
                if ($this->skipsKey($key, $product)) {
                    continue;
                }

                $oldLiters = (float) ($segment['old_rate_liters'] ?? 0);
                $newLiters = (float) ($segment['new_rate_liters'] ?? 0);
                $fallbackLiters = (float) ($segment['fallback_liters'] ?? 0);
                $oldRate = (float) ($segment['old_rate'] ?? 0);
                $newRate = (float) ($segment['new_rate'] ?? 0);

                $rateChangeRows[] = [
                    'trail_key' => 'rate:'.$transaction->id.':'.$segmentIndex,
                    'date' => $date->toDateString(),
                    'date_label' => $date->format('d M Y'),
                    'transaction_id' => $transaction->id,
                    'transaction_number' => $transaction->transaction_number,
                    'product_key' => $key,
                    'product_name' => $this->productName($key, $items, $segment['item_name'] ?? null),
                    'old_rate' => $oldRate,
                    'new_rate' => $newRate,
                    'old_rate_liters' => $oldLiters,
                    'new_rate_liters' => $newLiters,
                    'fallback_liters' => $fallbackLiters,
                    'revenue' => (float) ($segment['revenue'] ?? 0),
                    'estimated_rate_change_effect' => round(($newRate - $oldRate) * $newLiters, 2),
                ];
                $this->trail?->rateChange('rate:'.$transaction->id.':'.$segmentIndex, $rateChangeRows[array_key_last($rateChangeRows)], $transaction);
            }
        }

        $this->addOffTankerSales($companyId, $startDate, $endDate, $groupBy, $product, $items, $products, $periods);
        $this->addWritedowns($companyId, $startDate, $endDate, $groupBy, $product, $items, $products, $periods);
        $this->addStockVariance($companyId, $startDate, $endDate, $product, $items, $products);
        $this->addPurchases($companyId, $startDate, $endDate, $groupBy, $product, $items, $products, $periods);

        $productRows = array_values($products);
        foreach ($productRows as &$row) {
            $this->finishProductRow($row);
        }
        unset($row);
        $this->addBookProfit($companyId, $startDate, $endDate, $items, $productRows);
        usort($productRows, fn (array $a, array $b) => $b['revenue'] <=> $a['revenue']);

        // A period with a delivery but no posted close is added after the others; keep date order.
        ksort($periods);
        $periodRows = array_values($periods);
        foreach ($periodRows as &$row) {
            $this->finishPeriodRow($row);
        }
        unset($row);

        $report = [
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'group_by' => $groupBy,
                'product' => $product,
                'category_id' => $category?->id ?? '',
            ],
            'category' => $category ? ['id' => $category->id, 'name' => $category->name] : null,
            'categoryOptions' => ItemCategory::where('company_id', $companyId)->where('is_active', true)
                ->orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->all(),
            'totals' => $this->totals($productRows),
            'productRows' => $productRows,
            'periodRows' => $periodRows,
            'rateChangeRows' => $rateChangeRows,
            'productOptions' => $this->productOptions($items),
            'valueHints' => ProfitValueTrail::HINTS,
        ];

        if ($this->trail) {
            $report['valueTrail'] = $this->trail->finish($report);
        }

        return $report;
    }

    /**
     * The key this report files an item's row under (a fuel's category, else the item id), or null
     * when the item is not sellable. The Calculator reads a product's row through it.
     */
    public function keyForItem(string $companyId, string $itemId): ?string
    {
        foreach ($this->items($companyId) as $key => $item) {
            if (($item['id'] ?? null) === $itemId) {
                return (string) $key;
            }
        }

        return null;
    }

    /** True when a product key is outside the chosen product or category. */
    private function skipsKey(string $key, string $product): bool
    {
        if ($this->categoryKeys !== null && ! in_array($key, $this->categoryKeys, true)) {
            return true;
        }

        return $product !== 'all' && $key !== $product;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function items(string $companyId): array
    {
        // A category names one product (the closes key fuel sales by it). When several share one --
        // packaged lubricants marked 'lubricant' beside the open drum -- the one with a tank keeps
        // the category and the rest go by their own id, or they would overwrite each other here
        // and their sales would lose their cost.
        $tankItems = DB::table('inv.warehouses')->where('company_id', $companyId)->where('warehouse_type', 'tank')
            ->whereNotNull('linked_item_id')->pluck('linked_item_id')->all();
        $all = Item::where('company_id', $companyId)
            ->where('is_sellable', true)
            ->whereNull('deleted_at')
            ->get(['id', 'sku', 'name', 'fuel_category', 'avg_cost', 'cost_price', 'unit_of_measure', 'asset_account_id', 'category_id']);
        $sharing = $all->whereNotNull('fuel_category')->groupBy('fuel_category')->filter(fn ($g) => $g->count() > 1);
        $categoryOwner = $sharing->map(fn ($g) => ($g->first(fn ($i) => in_array($i->id, $tankItems, true)) ?? $g->first())->id);

        return $all
            ->mapWithKeys(function (Item $item) use ($categoryOwner) {
                $category = $item->fuel_category;
                if ($category && isset($categoryOwner[$category]) && $categoryOwner[$category] !== $item->id) {
                    $category = null;
                }
                $key = $this->itemKey($item->id, [], $item->name, $category);

                return [$key => [
                    'id' => $item->id,
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'fuel_category' => $item->fuel_category,
                    'avg_cost' => (float) ($item->avg_cost ?: $item->cost_price ?: 0),
                    'unit' => $item->unit_of_measure ?: 'L',
                    'asset_account_id' => $item->asset_account_id,
                    'category_id' => $item->category_id,
                ]];
            })
            ->all();
    }

    /**
     * @param  array<string,array<string,mixed>>  $items
     * @return array<int,array<string,mixed>>
     */
    private function fuelSalesRows(mixed $fuelSales, array $items): array
    {
        if (! is_array($fuelSales)) {
            return [];
        }

        $rows = [];
        foreach ($fuelSales as $key => $sale) {
            if (! is_array($sale)) {
                continue;
            }

            $productKey = (string) $key;
            $rows[] = [
                'key' => $productKey,
                'name' => $this->productName($productKey, $items),
                'unit' => 'L',
                'quantity' => (float) ($sale['liters'] ?? 0),
                'revenue' => (float) ($sale['revenue'] ?? 0),
                'cogs' => (float) ($sale['cogs'] ?? 0),
                'estimated_cogs' => false,
                'source' => 'fuel',
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string,array<string,mixed>>  $items
     * @return array<int,array<string,mixed>>
     */
    private function otherSalesRows(mixed $otherSales, array $items, array $correctedUnitCosts = []): array
    {
        if (! is_array($otherSales)) {
            return [];
        }

        $rows = [];
        foreach ($otherSales as $sale) {
            if (! is_array($sale)) {
                continue;
            }

            $key = $this->itemKey($sale['item_id'] ?? null, $items, $sale['item_name'] ?? null);
            $quantity = (float) ($sale['quantity'] ?? 0);
            $revenue = (float) ($sale['amount'] ?? 0);
            // The cost the close recorded when it posted; else the month's posted correction;
            // else today's average cost as an estimate.
            $recorded = (float) ($sale['cost'] ?? 0);
            $corrected = (float) ($correctedUnitCosts[(string) ($sale['item_id'] ?? '')] ?? 0);
            $estimated = $recorded <= 0 && $corrected <= 0;
            $cogs = $recorded > 0
                ? round($recorded, 2)
                : round($quantity * ($corrected > 0 ? $corrected : (float) ($items[$key]['avg_cost'] ?? 0)), 2);

            $rows[] = [
                'key' => $key,
                'name' => $this->productName($key, $items, $sale['item_name'] ?? null),
                'unit' => (string) ($items[$key]['unit'] ?? 'unit'),
                'quantity' => $quantity,
                'revenue' => $revenue,
                'cogs' => $cogs,
                'estimated_cogs' => $estimated,
                'source' => 'other_sale',
                'recorded_price' => isset($sale['unit_price']) ? (float) $sale['unit_price'] : null,
                'cost_basis' => $recorded > 0
                    ? 'The cost recorded when this daily close posted.'
                    : ($corrected > 0
                        ? 'Quantity multiplied by the month’s posted lubricant cost correction. The original close did not record a cost.'
                        : 'Estimated using the product’s current average cost (or cost price). Historical cost was not recorded; this estimate can change when the product cost changes.'),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string,array<string,mixed>>  $products
     * @param  array<string,mixed>  $row
     */
    private function addProductSale(array &$products, array $row): void
    {
        if (! isset($products[$row['key']])) {
            $products[$row['key']] = $this->emptyProductRow($row['key'], $row['name'], $row['unit']);
        }

        $products[$row['key']]['quantity'] += $row['quantity'];
        $products[$row['key']]['revenue'] += $row['revenue'];
        $products[$row['key']]['cogs'] += $row['cogs'];
        $products[$row['key']]['estimated_cogs'] = $products[$row['key']]['estimated_cogs'] || $row['estimated_cogs'];
    }

    /**
     * @param  array<string,array<string,mixed>>  $periods
     * @param  array<string,mixed>  $row
     */
    private function addPeriodSale(array &$periods, Carbon $date, string $groupBy, array $row, string $transactionId, string $transactionNumber): void
    {
        $periodKey = $this->ensurePeriod($periods, $date, $groupBy);

        $periods[$periodKey]['quantity'] += $row['quantity'];
        $periods[$periodKey]['revenue'] += $row['revenue'];
        $periods[$periodKey]['cogs'] += $row['cogs'];
        $periods[$periodKey]['daily_close_ids'][$transactionId] = $transactionId;
        $periods[$periodKey]['daily_close_numbers'][$transactionNumber] = $transactionNumber;
    }

    /**
     * @param  array<string,array<string,mixed>>  $periods
     */
    private function ensurePeriod(array &$periods, Carbon $date, string $groupBy): string
    {
        $periodKey = $this->periodKey($date, $groupBy);
        if (! isset($periods[$periodKey])) {
            $periods[$periodKey] = [
                'key' => $periodKey,
                'label' => $this->periodLabel($date, $groupBy),
                'quantity' => 0.0,
                'purchased_quantity' => 0.0,
                'revenue' => 0.0,
                'cogs' => 0.0,
                'gross_profit' => 0.0,
                'gross_margin_percent' => 0.0,
                'margin_per_unit' => 0.0,
                'daily_close_ids' => [],
                'daily_close_numbers' => [],
                'daily_close_count' => 0,
                'detail_url_id' => null,
            ];
        }

        return $periodKey;
    }

    /**
     * Litres (or units) bought, by bill date: every bill line for a sellable item on a bill
     * that counts (not draft, void or cancelled), whether it went into a tank or was sold
     * straight off the tanker. Only lines that went to a tank or store are counted: a bill
     * split between suppliers keeps its own lines, and its share bills carry money only
     * (quantity 1, no warehouse), so counting those would add phantom litres.
     *
     * @param  array<string,array<string,mixed>>  $items
     * @param  array<string,array<string,mixed>>  $products
     * @param  array<string,array<string,mixed>>  $periods
     */
    private function addPurchases(string $companyId, string $startDate, string $endDate, string $groupBy, string $product, array $items, array &$products, array &$periods): void
    {
        $lines = DB::table('acct.bill_line_items as l')
            ->join('acct.bills as b', 'b.id', '=', 'l.bill_id')
            ->where('b.company_id', $companyId)
            ->whereNull('b.deleted_at')
            ->whereNull('l.deleted_at')
            ->whereNotIn('b.status', ['draft', 'void', 'cancelled'])
            ->whereBetween('b.bill_date', [$startDate, $endDate])
            ->whereNotNull('l.item_id')
            ->whereNotNull('l.warehouse_id')
            ->get(['b.id as bill_id', 'b.bill_number', 'b.bill_date', 'l.item_id', 'l.quantity']);

        $itemIds = array_column($items, 'id');
        foreach ($lines as $line) {
            if (! in_array($line->item_id, $itemIds, true)) {
                continue;
            }
            $key = $this->itemKey($line->item_id, $items);
            if ($this->skipsKey($key, $product)) {
                continue;
            }

            if (! isset($products[$key])) {
                $products[$key] = $this->emptyProductRow($key, $this->productName($key, $items), (string) ($items[$key]['unit'] ?? 'L'));
            }
            $quantity = (float) $line->quantity;
            $products[$key]['purchased_quantity'] += $quantity;

            $periodKey = $this->ensurePeriod($periods, Carbon::parse($line->bill_date), $groupBy);
            $periods[$periodKey]['purchased_quantity'] += $quantity;
            $this->trail?->purchase($key, $periodKey, $line, (string) ($items[$key]['unit'] ?? 'L'));
        }
    }

    /**
     * Fuel sold straight off the tanker never passes a pump, so no close has it. The stock
     * statement knows it per day: the litres, what the direct-delivery invoices charged, and
     * the bills' own cost (bill amount x direct litres / bill litres).
     *
     * @param  array<string,array<string,mixed>>  $items
     * @param  array<string,array<string,mixed>>  $products
     * @param  array<string,array<string,mixed>>  $periods
     */
    private function addOffTankerSales(string $companyId, string $startDate, string $endDate, string $groupBy, string $product, array $items, array &$products, array &$periods): void
    {
        $tankItemIds = DB::table('inv.warehouses')
            ->where('company_id', $companyId)
            ->where('warehouse_type', 'tank')
            ->whereNotNull('linked_item_id')
            ->distinct()
            ->pluck('linked_item_id')
            ->all();

        foreach ($items as $key => $item) {
            if (! in_array($item['id'], $tankItemIds, true)) {
                continue;
            }
            if ($this->skipsKey($key, $product)) {
                continue;
            }

            foreach ($this->statement($companyId, (string) $item['id'], $startDate, $endDate)['rows'] as $row) {
                $bills = (array) ($row['bills'] ?? []);
                // A day without a close has no sold_direct; its bills still say how much went off the tanker.
                $quantity = (float) ($row['sold_direct'] ?? array_sum(array_column($bills, 'direct')));
                if ($quantity <= 0.0001) {
                    continue;
                }
                $revenue = (float) ($row['direct_amount'] ?? $row['sale_amount'] ?? 0);
                $cogs = 0.0;
                foreach ($bills as $bill) {
                    if ((float) ($bill['direct'] ?? 0) > 0 && (float) ($bill['quantity'] ?? 0) > 0) {
                        $cogs += (float) ($bill['amount'] ?? 0) * (float) $bill['direct'] / (float) $bill['quantity'];
                    }
                }
                $cogs = round($cogs, 2);

                if (! isset($products[$key])) {
                    $products[$key] = $this->emptyProductRow($key, $this->productName($key, $items), (string) ($item['unit'] ?? 'L'));
                }
                $products[$key]['quantity'] += $quantity;
                $products[$key]['revenue'] += $revenue;
                $products[$key]['cogs'] += $cogs;
                $products[$key]['direct_quantity'] += $quantity;
                $products[$key]['direct_revenue'] += $revenue;

                $periodKey = $this->ensurePeriod($periods, Carbon::parse($row['date']), $groupBy);
                $periods[$periodKey]['quantity'] += $quantity;
                $periods[$periodKey]['revenue'] += $revenue;
                $periods[$periodKey]['cogs'] += $cogs;
                $this->trail?->directSale($key, $periodKey, $row, $quantity, $revenue, $cogs);
            }
        }
    }

    /** The stock statement for an item over the range, run once per report. */
    private function statement(string $companyId, string $itemId, string $startDate, string $endDate): array
    {
        return $this->statements[$itemId] ??= app(StockStatementService::class)->run($companyId, $itemId, $startDate, $endDate);
    }

    /**
     * The station manager's formula from the books, per tank fuel: sales + closing stock - opening
     * stock - purchases, where stock is the item's inventory account balance (opening: the day
     * before the range, closing: the range end) and purchases are the fuel bills of the range (the
     * stock statement's purchase_amount). Null for anything else, and for a fuel whose inventory
     * account is shared with another product, since its balance isn't the fuel's own.
     *
     * @param  array<string,array<string,mixed>>  $items
     * @param  array<int,array<string,mixed>>  $productRows
     */
    private function addBookProfit(string $companyId, string $startDate, string $endDate, array $items, array &$productRows): void
    {
        $tankItemIds = DB::table('inv.warehouses')
            ->where('company_id', $companyId)->where('warehouse_type', 'tank')
            ->whereNotNull('linked_item_id')->whereNull('deleted_at')
            ->distinct()->pluck('linked_item_id')->all();
        $valuation = app(MonthEndStockValuationService::class);
        $dayBefore = Carbon::parse($startDate)->subDay()->toDateString();

        foreach ($productRows as &$row) {
            $item = $items[$row['key']] ?? null;
            $accountId = $item['asset_account_id'] ?? null;
            if (! $item || ! $accountId || ! in_array($item['id'], $tankItemIds, true)) {
                continue;
            }
            $shared = DB::table('inv.items')
                ->where('company_id', $companyId)->where('asset_account_id', $accountId)
                ->where('id', '!=', $item['id'])->whereNull('deleted_at')->exists();
            if ($shared) {
                continue;
            }
            $purchases = (float) ($this->statement($companyId, (string) $item['id'], $startDate, $endDate)['totals']['purchase_amount'] ?? 0);
            $opening = $valuation->accountBalance($companyId, $accountId, $dayBefore);
            $closing = $valuation->accountBalance($companyId, $accountId, $endDate);
            $row['book_opening'] = round($opening, 2);
            $row['book_closing'] = round($closing, 2);
            $row['book_purchases'] = round($purchases, 2);
            $row['book_profit'] = round($row['revenue'] + $closing - $opening - $purchases, 2);
            $this->trail?->book($companyId, $row['key'], $accountId, $dayBefore, $endDate, $row, $this->statement($companyId, (string) $item['id'], $startDate, $endDate));
            // Margins follow the books where the books have the figure (tank gains and losses,
            // later costs and the month-end write-down all in); elsewhere they stay on gross profit.
            $row['margin_per_unit'] = $row['quantity'] > 0 ? $row['book_profit'] / $row['quantity'] : 0;
            $row['gross_margin_percent'] = $row['revenue'] > 0 ? ($row['book_profit'] / $row['revenue']) * 100 : 0;
        }
        unset($row);
    }

    /**
     * Month-end stock valued at the new purchase rate (MonthEndStockValuationService): a cost
     * of that fuel in the month that held the litres, on the journal's own date, so gross profit
     * matches the books.
     *
     * @param  array<string,array<string,mixed>>  $items
     * @param  array<string,array<string,mixed>>  $products
     * @param  array<string,array<string,mixed>>  $periods
     */
    private function addWritedowns(string $companyId, string $startDate, string $endDate, string $groupBy, string $product, array $items, array &$products, array &$periods): void
    {
        $itemIds = array_column($items, 'id');
        foreach (app(MonthEndStockValuationService::class)->liveWritedowns($companyId, null, $startDate, $endDate) as $writedown) {
            $itemId = $writedown->metadata['item_id'] ?? null;
            if (! in_array($itemId, $itemIds, true)) {
                continue;
            }
            $key = $this->itemKey($itemId, $items);
            if ($this->skipsKey($key, $product)) {
                continue;
            }
            $amount = (float) ($writedown->metadata['amount'] ?? 0);
            if (! isset($products[$key])) {
                $products[$key] = $this->emptyProductRow($key, $this->productName($key, $items), (string) ($items[$key]['unit'] ?? 'L'));
            }
            $products[$key]['cogs'] += $amount;
            $products[$key]['writedown'] += $amount;

            $periodKey = $this->ensurePeriod($periods, Carbon::parse($writedown->transaction_date), $groupBy);
            $periods[$periodKey]['cogs'] += $amount;
            $this->trail?->writedown($key, $periodKey, $writedown, $amount);
        }
    }

    /**
     * @param  array<string,array<string,mixed>>  $products
     * @param  array<string,array<string,mixed>>  $items
     */
    private function addStockVariance(string $companyId, string $startDate, string $endDate, string $product, array $items, array &$products): void
    {
        $corrections = DB::table('fuel.daily_close_reading_corrections as corrections')
            ->join('acct.transactions as close_transactions', 'close_transactions.id', '=', 'corrections.close_transaction_id')
            ->where('corrections.company_id', $companyId)
            ->where('corrections.reading_type', 'tank')
            ->whereBetween('close_transactions.transaction_date', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ])
            ->orderBy('corrections.revision')
            ->get([
                'corrections.reading_id',
                'corrections.close_transaction_id',
                'corrections.effects',
                'corrections.revision',
            ])
            ->groupBy('reading_id');

        $correctedReadingIds = $corrections->keys()->all();
        $readings = TankReading::where('company_id', $companyId)
            ->whereBetween('reading_date', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ])
            ->where(function ($query) use ($correctedReadingIds) {
                $query->where('variance_type', '!=', TankReading::VARIANCE_NONE);

                if ($correctedReadingIds !== []) {
                    $query->orWhereIn('id', $correctedReadingIds);
                }
            })
            ->with('item:id,name,fuel_category,avg_cost,cost_price,unit_of_measure')
            ->get();

        foreach ($readings as $reading) {
            $key = $this->itemKey($reading->item_id, $items, $reading->item?->name, $reading->item?->fuel_category);
            if ($this->skipsKey($key, $product)) {
                continue;
            }

            if (! isset($products[$key])) {
                $products[$key] = $this->emptyProductRow($key, $this->productName($key, $items, $reading->item?->name), 'L');
            }

            $history = $corrections->get($reading->id, collect());
            $physicalEffect = (float) $history->sum(function ($correction) {
                $effects = is_array($correction->effects)
                    ? $correction->effects
                    : json_decode($correction->effects, true);

                return (float) ($effects['physical_liters_effect'] ?? 0);
            });
            $expectedEffect = (float) $history->sum(function ($correction) {
                $effects = is_array($correction->effects)
                    ? $correction->effects
                    : json_decode($correction->effects, true);

                return (float) ($effects['expected_liters_effect'] ?? 0);
            });
            $latestCorrection = $history->sortByDesc('revision')->first();
            $latestEffects = $latestCorrection
                ? (is_array($latestCorrection->effects)
                    ? $latestCorrection->effects
                    : json_decode($latestCorrection->effects, true))
                : [];
            $variance = (float) $reading->variance_liters + $physicalEffect - $expectedEffect;
            $unitCost = (float) ($latestEffects['unit_cost']
                ?? ($items[$key]['avg_cost'] ?? $reading->item?->avg_cost ?? $reading->item?->cost_price ?? 0));
            $value = round(abs($variance) * $unitCost, 2);
            $this->trail?->stockVariance($key, $reading, $variance, $unitCost, $history->all(), ! isset($latestEffects['unit_cost']));

            if ($variance < 0) {
                $products[$key]['stock_loss_quantity'] += abs($variance);
                $products[$key]['stock_loss_value'] += $value;
            } elseif ($variance > 0) {
                $products[$key]['stock_gain_quantity'] += $variance;
                $products[$key]['stock_gain_value'] += $value;
            }
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function emptyProductRow(string $key, string $name, string $unit): array
    {
        return [
            'key' => $key,
            'name' => $name,
            'unit' => $unit,
            'quantity' => 0.0,
            'purchased_quantity' => 0.0,
            'revenue' => 0.0,
            'cogs' => 0.0,
            'gross_profit' => 0.0,
            'gross_margin_percent' => 0.0,
            'avg_rate' => 0.0,
            'avg_cost' => 0.0,
            'margin_per_unit' => 0.0,
            'estimated_cogs' => false,
            'direct_quantity' => 0.0,
            'direct_revenue' => 0.0,
            'writedown' => 0.0,
            'book_profit' => null,
            'book_opening' => null,
            'book_closing' => null,
            'book_purchases' => null,
            'stock_loss_quantity' => 0.0,
            'stock_loss_value' => 0.0,
            'stock_gain_quantity' => 0.0,
            'stock_gain_value' => 0.0,
        ];
    }

    /**
     * @param  array<string,mixed>  $row
     */
    private function finishProductRow(array &$row): void
    {
        $row['gross_profit'] = $row['revenue'] - $row['cogs'];
        $row['gross_margin_percent'] = $row['revenue'] > 0 ? ($row['gross_profit'] / $row['revenue']) * 100 : 0;
        $row['avg_rate'] = $row['quantity'] > 0 ? $row['revenue'] / $row['quantity'] : 0;
        $row['avg_cost'] = $row['quantity'] > 0 ? $row['cogs'] / $row['quantity'] : 0;
        $row['margin_per_unit'] = $row['quantity'] > 0 ? $row['gross_profit'] / $row['quantity'] : 0;
    }

    /**
     * @param  array<string,mixed>  $row
     */
    private function finishPeriodRow(array &$row): void
    {
        $row['daily_close_ids'] = array_values($row['daily_close_ids']);
        $row['daily_close_numbers'] = array_values($row['daily_close_numbers']);
        $row['daily_close_count'] = count($row['daily_close_ids']);
        $row['detail_url_id'] = $row['daily_close_count'] === 1 ? $row['daily_close_ids'][0] : null;
        $row['gross_profit'] = $row['revenue'] - $row['cogs'];
        $row['gross_margin_percent'] = $row['revenue'] > 0 ? ($row['gross_profit'] / $row['revenue']) * 100 : 0;
        $row['margin_per_unit'] = $row['quantity'] > 0 ? $row['gross_profit'] / $row['quantity'] : 0;
    }

    /**
     * @param  array<int,array<string,mixed>>  $rows
     * @return array<string,float|int|null>
     */
    private function totals(array $rows): array
    {
        $totals = [
            'product_count' => count($rows),
            'quantity' => array_sum(array_column($rows, 'quantity')),
            'purchased_quantity' => array_sum(array_column($rows, 'purchased_quantity')),
            'revenue' => array_sum(array_column($rows, 'revenue')),
            'cogs' => array_sum(array_column($rows, 'cogs')),
            'writedown' => array_sum(array_column($rows, 'writedown')),
            'gross_profit' => array_sum(array_column($rows, 'gross_profit')),
            'stock_loss_quantity' => array_sum(array_column($rows, 'stock_loss_quantity')),
            'stock_loss_value' => array_sum(array_column($rows, 'stock_loss_value')),
            'stock_gain_quantity' => array_sum(array_column($rows, 'stock_gain_quantity')),
            'stock_gain_value' => array_sum(array_column($rows, 'stock_gain_value')),
        ];

        $book = array_filter(array_column($rows, 'book_profit'), fn ($v) => $v !== null);
        $totals['book_profit'] = $book === [] ? null : round(array_sum($book), 2);

        // Each product's own basis: the books' profit where there is one, gross profit otherwise.
        $profit = array_sum(array_map(fn ($r) => $r['book_profit'] ?? $r['gross_profit'], $rows));
        $totals['profit'] = round($profit, 2);
        $totals['gross_margin_percent'] = $totals['revenue'] > 0 ? ($profit / $totals['revenue']) * 100 : 0;
        $totals['margin_per_unit'] = $totals['quantity'] > 0 ? ($profit / $totals['quantity']) : 0;

        return $totals;
    }

    /**
     * @param  array<string,array<string,mixed>>  $items
     * @return array<int,array{key:string,name:string}>
     */
    private function productOptions(array $items): array
    {
        return collect($items)
            ->map(fn (array $item, string $key) => [
                'key' => $key,
                'name' => (string) $item['name'],
            ])
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * @param  array<string,array<string,mixed>>  $items
     */
    private function itemKey(mixed $itemId, array $items, ?string $fallbackName = null, ?string $fuelCategory = null): string
    {
        if ($fuelCategory) {
            return (string) $fuelCategory;
        }

        if ($itemId) {
            foreach ($items as $key => $item) {
                if (($item['id'] ?? null) === $itemId) {
                    return $key;
                }
            }

            return (string) $itemId;
        }

        return str($fallbackName ?: 'other')->slug('-')->toString();
    }

    /**
     * @param  array<string,array<string,mixed>>  $items
     */
    private function productName(string $key, array $items, ?string $fallbackName = null): string
    {
        return (string) ($items[$key]['name'] ?? $fallbackName ?? str($key)->replace(['_', '-'], ' ')->title());
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
            'week' => 'Week of '.$date->copy()->startOfWeek()->format('d M Y'),
            'month' => $date->format('F Y'),
            default => $date->format('d M Y'),
        };
    }
}
