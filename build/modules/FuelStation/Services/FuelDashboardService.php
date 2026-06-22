<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Models\AttendantHandover;
use App\Modules\FuelStation\Models\CustomerProfile;
use App\Modules\FuelStation\Models\Investor;
use App\Modules\FuelStation\Models\Pump;
use App\Modules\FuelStation\Models\PumpReading;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\FuelStation\Models\SaleMetadata;
use App\Modules\FuelStation\Models\TankReading;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\ProductCatalogService;
use Illuminate\Support\Carbon;

class FuelDashboardService
{
    /**
     * Lightweight summary for the company home dashboard.
     *
     * @return array{
     *   summary: array{active_pumps:int,today_readings:int,pending_tank_readings:int},
     *   pendingHandovers: array{count:int,total_amount:float},
     *   tanks: array<int, array{tank_id:string,item_name:string,capacity:float,current_level:float,fill_percentage:float,last_reading_date:mixed}>,
     *   rates: array<int, array{item_name:string,purchase_rate:float,sale_rate:float,margin:float,effective_date:mixed}>
     * }
     */
    public function getHomeCards(string $companyId, ?Carbon $asOfDate = null): array
    {
        $asOfDate ??= Carbon::today();
        $summary = $this->getSummary($companyId, $asOfDate);

        $pending = $this->getPendingHandovers($companyId);
        $pendingCount = is_iterable($pending['items'] ?? null) ? count($pending['items']) : 0;

        $tanks = collect($this->getTankLevels($companyId))
            ->sortBy('fill_percentage')
            ->take(3)
            ->map(function ($row) {
                return [
                    'tank_id' => (string) ($row['tank']->id ?? ''),
                    'item_name' => (string) ($row['item_name'] ?? 'Unknown'),
                    'capacity' => (float) ($row['capacity'] ?? 0),
                    'current_level' => (float) ($row['current_level'] ?? 0),
                    'fill_percentage' => (float) ($row['fill_percentage'] ?? 0),
                    'last_reading_date' => $row['last_reading_date'] ?? null,
                ];
            })
            ->values()
            ->all();

        $rates = collect($this->getCurrentRates($companyId))
            ->map(function ($row) {
                return [
                    'item_name' => (string) ($row['item']->name ?? 'Unknown'),
                    'purchase_rate' => (float) ($row['purchase_rate'] ?? 0),
                    'sale_rate' => (float) ($row['sale_rate'] ?? 0),
                    'margin' => (float) ($row['margin'] ?? 0),
                    'effective_date' => $row['effective_date'] ?? null,
                ];
            })
            ->values()
            ->all();

        return [
            'summary' => $summary,
            'pendingHandovers' => [
                'count' => $pendingCount,
                'total_amount' => (float) ($pending['total_amount'] ?? 0),
            ],
            'tanks' => $tanks,
            'rates' => $rates,
            'products' => $this->getProductDashboard($companyId, $asOfDate),
        ];
    }

    /**
     * Get comprehensive dashboard data for fuel station.
     */
    public function getDashboardData(string $companyId): array
    {
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();

        return [
            'summary' => $this->getSummary($companyId, $today),
            'tankLevels' => $this->getTankLevels($companyId),
            'currentRates' => $this->getCurrentRates($companyId),
            'todaySales' => $this->getTodaySales($companyId, $today),
            'monthlySales' => $this->getMonthlySales($companyId, $startOfMonth),
            'pendingHandovers' => $this->getPendingHandovers($companyId),
            'outstandingInvestorCommissions' => $this->getOutstandingInvestorCommissions($companyId),
            'amanatSummary' => $this->getAmanatSummary($companyId),
            'vendorCardReceivable' => $this->getVendorCardReceivable($companyId),
        ];
    }

    /**
     * Get summary metrics.
     */
    private function getSummary(string $companyId, Carbon $today): array
    {
        $activePumps = Pump::where('company_id', $companyId)
            ->where('is_active', true)
            ->count();

        $todayReadings = PumpReading::where('company_id', $companyId)
            ->whereDate('reading_date', $today)
            ->count();

        $pendingTankReadings = TankReading::where('company_id', $companyId)
            ->where('status', TankReading::STATUS_DRAFT)
            ->count();

        return [
            'active_pumps' => $activePumps,
            'today_readings' => $todayReadings,
            'pending_tank_readings' => $pendingTankReadings,
        ];
    }

    /**
     * Get current tank levels.
     */
    private function getTankLevels(string $companyId): array
    {
        $tanks = Warehouse::where('company_id', $companyId)
            ->where('warehouse_type', 'tank')
            ->with('linkedItem')
            ->get();

        $levels = [];
        foreach ($tanks as $tank) {
            // Get latest tank reading for current level
            $latestReading = TankReading::where('company_id', $companyId)
                ->where('tank_id', $tank->id)
                ->orderByDesc('reading_date')
                ->first();

            $levels[] = [
                'tank' => $tank,
                'item_name' => $tank->linkedItem?->name ?? 'Unknown',
                'capacity' => $tank->capacity ?? 0,
                'current_level' => $latestReading?->dip_measurement_liters ?? 0,
                'fill_percentage' => $tank->capacity > 0 && $latestReading
                    ? round(($latestReading->dip_measurement_liters / $tank->capacity) * 100, 1)
                    : 0,
                'last_reading_date' => $latestReading?->reading_date,
            ];
        }

        return $levels;
    }

    /**
     * Get current fuel rates.
     */
    private function getCurrentRates(string $companyId): array
    {
        // Use fuel_category column to identify fuel items
        $fuelItems = Item::where('company_id', $companyId)
            ->whereNotNull('fuel_category')
            ->get();

        $rates = [];
        foreach ($fuelItems as $item) {
            $rate = RateChange::getCurrentRate($companyId, $item->id);
            if ($rate) {
                $rates[] = [
                    'item' => $item,
                    'purchase_rate' => $rate->purchase_rate,
                    'sale_rate' => $rate->sale_rate,
                    'margin' => $rate->margin,
                    'effective_date' => $rate->effective_date,
                ];
            }
        }

        return $rates;
    }

    /**
     * Get today's sales breakdown.
     */
    private function getTodaySales(string $companyId, Carbon $today): array
    {
        $salesByType = SaleMetadata::where('company_id', $companyId)
            ->whereHas('invoice', function ($query) use ($today) {
                $query->whereDate('invoice_date', $today);
            })
            ->with('invoice')
            ->get()
            ->groupBy('sale_type')
            ->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total' => $group->sum(fn ($meta) => $meta->invoice->total_amount),
                ];
            });

        // Total from fuel sale metadata (all fuel sales for today)
        $totalSales = SaleMetadata::where('company_id', $companyId)
            ->whereHas('invoice', function ($query) use ($today) {
                $query->whereDate('invoice_date', $today);
            })
            ->with('invoice')
            ->get()
            ->sum(fn ($meta) => $meta->invoice->total_amount);

        return [
            'by_type' => $salesByType,
            'total' => $totalSales,
        ];
    }

    /**
     * Get monthly sales summary.
     */
    private function getMonthlySales(string $companyId, Carbon $startOfMonth): array
    {
        // Total from fuel sale metadata (all fuel sales this month)
        $totalSales = SaleMetadata::where('company_id', $companyId)
            ->whereHas('invoice', function ($query) use ($startOfMonth) {
                $query->whereDate('invoice_date', '>=', $startOfMonth);
            })
            ->with('invoice')
            ->get()
            ->sum(fn ($meta) => $meta->invoice->total_amount);

        $totalLiters = PumpReading::where('company_id', $companyId)
            ->whereDate('reading_date', '>=', $startOfMonth)
            ->sum('liters_dispensed');

        return [
            'total_sales' => $totalSales,
            'total_liters' => $totalLiters,
        ];
    }

    /**
     * Get pending handovers awaiting receipt.
     */
    private function getPendingHandovers(string $companyId): array
    {
        $handovers = AttendantHandover::where('company_id', $companyId)
            ->where('status', AttendantHandover::STATUS_PENDING)
            ->with('attendant')
            ->orderBy('handover_date')
            ->limit(10)
            ->get();

        $totalPending = AttendantHandover::where('company_id', $companyId)
            ->where('status', AttendantHandover::STATUS_PENDING)
            ->sum('total_amount');

        return [
            'items' => $handovers,
            'total_amount' => $totalPending,
        ];
    }

    /**
     * Get outstanding investor commissions.
     */
    private function getOutstandingInvestorCommissions(string $companyId): array
    {
        $investors = Investor::where('company_id', $companyId)
            ->where('is_active', true)
            ->get()
            ->filter(fn ($i) => $i->outstanding_commission > 0)
            ->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->name,
                'outstanding' => $i->outstanding_commission,
            ])
            ->values();

        $totalOutstanding = $investors->sum('outstanding');

        return [
            'investors' => $investors,
            'total' => $totalOutstanding,
        ];
    }

    /**
     * Get amanat (trust deposit) summary.
     */
    private function getAmanatSummary(string $companyId): array
    {
        $profiles = CustomerProfile::where('company_id', $companyId)
            ->where('is_amanat_holder', true)
            ->get();

        return [
            'total_holders' => $profiles->count(),
            'total_balance' => $profiles->sum('amanat_balance'),
        ];
    }

    /**
     * Get vendor card receivable (unpaid vendor card sales).
     */
    private function getVendorCardReceivable(string $companyId): float
    {
        return SaleMetadata::where('company_id', $companyId)
            ->where('sale_type', SaleMetadata::TYPE_VENDOR_CARD)
            ->whereHas('invoice', function ($query) {
                $query->where('status', '!=', 'paid');
            })
            ->with('invoice')
            ->get()
            ->sum(fn ($meta) => (float) ($meta->invoice?->total_amount ?? 0) - (float) ($meta->invoice?->paid_amount ?? 0));
    }

    private function getProductDashboard(string $companyId, Carbon $asOfDate): array
    {
        $products = Item::where('company_id', $companyId)
            ->where('is_sellable', true)
            ->whereIn('item_type', ['product', 'non_inventory'])
            ->orderByRaw('fuel_category IS NULL')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $productIds = $products->pluck('id')->all();
        $stockByItem = $this->getStockByItem($companyId, $productIds, $asOfDate);
        $tankByItem = $this->getTankStockByItem($companyId, $asOfDate);
        $lastMovementByItem = $this->getLatestStockMovementByItem($companyId, $productIds, $asOfDate);
        $salesByItem = $this->getProductSales($companyId, $products, $asOfDate);
        $productCatalog = app(ProductCatalogService::class);

        $rows = $products->map(function (Item $item) use ($stockByItem, $tankByItem, $lastMovementByItem, $salesByItem, $companyId, $productCatalog, $asOfDate) {
            $fuelCategory = $item->fuel_category ?: $productCatalog->inferFuelCategory($item->sku, $item->name);
            $tankStock = $tankByItem[$item->id] ?? null;
            $latestMovement = $lastMovementByItem[$item->id] ?? null;
            $stock = $stockByItem[$item->id] ?? [
                'quantity' => 0.0,
                'available_quantity' => 0.0,
                'reorder_point' => (float) ($item->reorder_point ?? 0),
            ];

            $currentStock = (float) $stock['quantity'];
            $availableStock = (float) $stock['available_quantity'];
            $lastDipQuantity = $tankStock && $tankStock['last_dip_quantity'] !== null
                ? (float) $tankStock['last_dip_quantity']
                : null;
            $stockVariance = $lastDipQuantity !== null ? round($lastDipQuantity - $currentStock, 3) : null;
            $lowStockLevel = $tankStock
                ? (float) $tankStock['low_stock_level']
                : (float) ($stock['reorder_point'] ?: $item->reorder_point ?: 0);
            $capacity = $tankStock ? (float) $tankStock['capacity'] : null;
            $fillPercentage = $capacity && $capacity > 0 ? round(($currentStock / $capacity) * 100, 1) : null;
            $sales = $salesByItem[$item->id] ?? $this->emptyProductSales();
            $rate = $fuelCategory ? RateChange::getRateForDate($companyId, $item->id, $asOfDate->toDateString()) : null;
            $saleRate = (float) ($rate?->sale_rate ?? $item->selling_price ?? 0);
            $purchaseRate = (float) ($rate?->purchase_rate ?? $item->avg_cost ?? $item->cost_price ?? 0);

            return [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'fuel_category' => $fuelCategory,
                'unit' => $item->unit_of_measure,
                'is_active' => (bool) $item->is_active,
                'track_inventory' => (bool) $item->track_inventory,
                'current_stock' => round($currentStock, 3),
                'available_stock' => round($availableStock, 3),
                'last_stock_movement_at' => $latestMovement['created_at'] ?? null,
                'last_stock_movement_date' => $latestMovement['movement_date'] ?? null,
                'last_stock_movement_type' => $latestMovement['movement_type'] ?? null,
                'last_stock_movement_reason' => $latestMovement['reason'] ?? null,
                'low_stock_level' => round($lowStockLevel, 3),
                'is_low_stock' => (bool) ($item->is_active && $item->track_inventory && $lowStockLevel > 0 && $currentStock <= $lowStockLevel),
                'capacity' => $capacity,
                'fill_percentage' => $fillPercentage,
                'last_dip_quantity' => $lastDipQuantity !== null ? round($lastDipQuantity, 3) : null,
                'last_dip_at' => $tankStock['last_dip_at'] ?? null,
                'last_dip_recorded_at' => $tankStock['last_dip_recorded_at'] ?? null,
                'last_dip_status' => $tankStock['last_dip_status'] ?? null,
                'last_tank_reading_type' => $tankStock['last_tank_reading_type'] ?? null,
                'stock_variance' => $stockVariance,
                'stock_value' => round($currentStock * (float) ($item->avg_cost ?: $item->cost_price ?: 0), 2),
                'purchase_rate' => $purchaseRate,
                'sale_rate' => $saleRate,
                'margin' => round($saleRate - $purchaseRate, 2),
                'last_sold_at' => $sales['last_sold_at'],
                'sales' => $sales,
            ];
        })->values();

        $lowStock = $rows->filter(fn ($row) => $row['is_low_stock'])->values();
        $topProducts = $rows
            ->filter(fn ($row) => $row['sales']['last_month']['amount'] > 0)
            ->sortByDesc(fn ($row) => $row['sales']['last_month']['amount'])
            ->take(5)
            ->values();

        return [
            'summary' => [
                'as_of_date' => $asOfDate->toDateString(),
                'total_products' => $rows->count(),
                'active_products' => $rows->filter(fn ($row) => $row['is_active'])->count(),
                'fuel_products' => $rows->filter(fn ($row) => $row['fuel_category'] !== null)->count(),
                'low_stock_count' => $lowStock->count(),
                'inventory_value' => round($rows->sum('stock_value'), 2),
                'yesterday_sales' => round($rows->sum(fn ($row) => $row['sales']['yesterday']['amount']), 2),
                'last_week_sales' => round($rows->sum(fn ($row) => $row['sales']['last_week']['amount']), 2),
                'last_month_sales' => round($rows->sum(fn ($row) => $row['sales']['last_month']['amount']), 2),
                'yesterday_liters' => round($rows->sum(fn ($row) => $row['sales']['yesterday']['quantity']), 3),
                'last_week_liters' => round($rows->sum(fn ($row) => $row['sales']['last_week']['quantity']), 3),
                'last_month_liters' => round($rows->sum(fn ($row) => $row['sales']['last_month']['quantity']), 3),
            ],
            'low_stock' => $lowStock->take(6)->values()->all(),
            'top_products' => $topProducts->all(),
            'items' => $rows->all(),
        ];
    }

    private function getStockByItem(string $companyId, array $productIds, Carbon $asOfDate): array
    {
        if (empty($productIds)) {
            return [];
        }

        $movementStock = StockMovement::where('company_id', $companyId)
            ->whereIn('item_id', $productIds)
            ->whereDate('movement_date', '<=', $asOfDate->toDateString())
            ->selectRaw('item_id, SUM(quantity) as quantity')
            ->groupBy('item_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->item_id => [
                    'quantity' => (float) $row->quantity,
                    'available_quantity' => (float) $row->quantity,
                    'reorder_point' => 0.0,
                ],
            ]);

        $levels = StockLevel::where('company_id', $companyId)
            ->whereIn('item_id', $productIds)
            ->selectRaw('item_id, SUM(quantity) as quantity, SUM(available_quantity) as available_quantity, MAX(COALESCE(reorder_point, 0)) as reorder_point')
            ->groupBy('item_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->item_id => [
                    'quantity' => (float) $row->quantity,
                    'available_quantity' => (float) $row->available_quantity,
                    'reorder_point' => (float) $row->reorder_point,
                ],
            ]);

        return collect($productIds)
            ->mapWithKeys(function ($itemId) use ($movementStock, $levels) {
                $levelRow = $levels->get($itemId);
                $row = $movementStock->get($itemId) ?? $levels->get($itemId) ?? [
                    'quantity' => 0.0,
                    'available_quantity' => 0.0,
                    'reorder_point' => 0.0,
                ];

                $row['reorder_point'] = (float) ($levelRow['reorder_point'] ?? $row['reorder_point'] ?? 0);

                return [$itemId => $row];
            })
            ->all();
    }

    private function getLatestStockMovementByItem(string $companyId, array $productIds, Carbon $asOfDate): array
    {
        if (empty($productIds)) {
            return [];
        }

        return StockMovement::where('company_id', $companyId)
            ->whereIn('item_id', $productIds)
            ->whereDate('movement_date', '<=', $asOfDate->toDateString())
            ->orderByDesc('movement_date')
            ->orderByDesc('created_at')
            ->get(['item_id', 'movement_date', 'movement_type', 'reason', 'created_at'])
            ->unique('item_id')
            ->mapWithKeys(fn (StockMovement $movement) => [
                $movement->item_id => [
                    'movement_date' => $movement->movement_date?->toDateString(),
                    'movement_type' => $movement->movement_type,
                    'reason' => $movement->reason,
                    'created_at' => $movement->created_at?->toIso8601String(),
                ],
            ])
            ->all();
    }

    private function getTankStockByItem(string $companyId, Carbon $asOfDate): array
    {
        $tanks = Warehouse::where('company_id', $companyId)
            ->where('warehouse_type', 'tank')
            ->where('is_active', true)
            ->whereNotNull('linked_item_id')
            ->get(['id', 'name', 'capacity', 'low_level_alert', 'linked_item_id']);

        if ($tanks->isEmpty()) {
            return [];
        }

        $latestReadings = TankReading::where('company_id', $companyId)
            ->whereIn('tank_id', $tanks->pluck('id')->all())
            ->whereDate('reading_date', '<=', $asOfDate->toDateString())
            ->orderByDesc('reading_date')
            ->orderByDesc('created_at')
            ->get(['tank_id', 'reading_date', 'reading_type', 'dip_measurement_liters', 'status', 'created_at'])
            ->unique('tank_id')
            ->keyBy('tank_id');

        $byItem = [];
        foreach ($tanks as $tank) {
            $itemId = (string) $tank->linked_item_id;
            $reading = $latestReadings->get($tank->id);

            if (! isset($byItem[$itemId])) {
                $byItem[$itemId] = [
                    'capacity' => 0.0,
                    'low_stock_level' => 0.0,
                    'tank_count' => 0,
                    'last_dip_quantity' => null,
                    'last_dip_at' => null,
                    'last_dip_recorded_at' => null,
                    'last_dip_status' => null,
                    'last_tank_reading_type' => null,
                ];
            }

            $byItem[$itemId]['capacity'] += (float) ($tank->capacity ?? 0);
            $byItem[$itemId]['low_stock_level'] += (float) ($tank->low_level_alert ?? 0);
            $byItem[$itemId]['tank_count']++;

            if ($reading) {
                $byItem[$itemId]['last_dip_quantity'] = (float) ($byItem[$itemId]['last_dip_quantity'] ?? 0)
                    + (float) ($reading->dip_measurement_liters ?? 0);

                $readingAt = $reading->reading_date;
                $recordedAt = $reading->created_at;
                $currentRecordedAt = $byItem[$itemId]['last_dip_recorded_at']
                    ? Carbon::parse($byItem[$itemId]['last_dip_recorded_at'])
                    : null;

                if (! $currentRecordedAt || ($recordedAt && $recordedAt->gt($currentRecordedAt))) {
                    $byItem[$itemId]['last_dip_at'] = $readingAt?->toIso8601String();
                    $byItem[$itemId]['last_dip_recorded_at'] = $recordedAt?->toIso8601String();
                    $byItem[$itemId]['last_dip_status'] = $reading->status;
                    $byItem[$itemId]['last_tank_reading_type'] = $reading->reading_type;
                }
            }
        }

        return $byItem;
    }

    private function getProductSales(string $companyId, $products, Carbon $asOfDate): array
    {
        $today = $asOfDate->copy();
        $yesterday = $today->copy()->subDay();
        $lastWeekStart = $today->copy()->subDays(7);
        $lastMonthStart = $today->copy()->subDays(30);

        $byId = [];
        $byCategory = [];
        $byName = [];

        foreach ($products as $product) {
            $byId[$product->id] = $this->emptyProductSales();
            if ($product->fuel_category) {
                $byCategory[$product->fuel_category] = $product->id;
                if ($product->fuel_category === 'high_octane') {
                    $byCategory['hi_octane'] = $product->id;
                }
            }
            $byName[strtolower((string) $product->name)] = $product->id;
        }

        $transactions = Transaction::where('company_id', $companyId)
            ->whereIn('transaction_type', ['fuel_daily_close', 'daily_close'])
            ->whereBetween('transaction_date', [$lastMonthStart->toDateString(), $yesterday->toDateString()])
            ->where(function ($query) {
                $query->whereIn('status', ['posted', 'locked'])
                    ->orWhere('is_locked', true);
            })
            ->orderBy('transaction_date')
            ->get(['id', 'transaction_date', 'metadata']);

        foreach ($transactions as $transaction) {
            $date = Carbon::parse($transaction->transaction_date);
            $periods = [];
            if ($date->isSameDay($yesterday)) {
                $periods[] = 'yesterday';
            }
            if ($date->betweenIncluded($lastWeekStart, $yesterday)) {
                $periods[] = 'last_week';
            }
            if ($date->betweenIncluded($lastMonthStart, $yesterday)) {
                $periods[] = 'last_month';
            }
            if (empty($periods)) {
                continue;
            }

            $metadata = $transaction->metadata ?? [];

            foreach (($metadata['fuel_sales'] ?? []) as $category => $sale) {
                $itemId = $byCategory[$category] ?? null;
                if (! $itemId) {
                    continue;
                }
                $this->addProductSale($byId[$itemId], $periods, (float) ($sale['liters'] ?? 0), (float) ($sale['revenue'] ?? 0), (float) ($sale['cogs'] ?? 0), $date);
            }

            foreach (($metadata['sales'] ?? []) as $sale) {
                $itemId = $byName[strtolower((string) ($sale['fuel_name'] ?? ''))] ?? null;
                if (! $itemId) {
                    continue;
                }
                $this->addProductSale($byId[$itemId], $periods, (float) ($sale['liters'] ?? 0), (float) ($sale['amount'] ?? 0), 0.0, $date);
            }

            foreach (($metadata['other_sales_details'] ?? []) as $sale) {
                $itemId = (string) ($sale['item_id'] ?? '');
                if (! isset($byId[$itemId])) {
                    continue;
                }
                $this->addProductSale($byId[$itemId], $periods, (float) ($sale['quantity'] ?? 0), (float) ($sale['amount'] ?? 0), 0.0, $date);
            }
        }

        return $byId;
    }

    private function emptyProductSales(): array
    {
        return [
            'yesterday' => ['quantity' => 0.0, 'amount' => 0.0, 'cogs' => 0.0],
            'last_week' => ['quantity' => 0.0, 'amount' => 0.0, 'cogs' => 0.0],
            'last_month' => ['quantity' => 0.0, 'amount' => 0.0, 'cogs' => 0.0],
            'last_sold_at' => null,
        ];
    }

    private function addProductSale(array &$sales, array $periods, float $quantity, float $amount, float $cogs, Carbon $date): void
    {
        foreach ($periods as $period) {
            $sales[$period]['quantity'] += $quantity;
            $sales[$period]['amount'] += $amount;
            $sales[$period]['cogs'] += $cogs;
        }

        if ($amount > 0 && (! $sales['last_sold_at'] || $date->gt(Carbon::parse($sales['last_sold_at'])))) {
            $sales['last_sold_at'] = $date->toDateString();
        }
    }
}
