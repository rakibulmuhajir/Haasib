<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\Accounting\Models\Invoice;
use App\Modules\FuelStation\Models\AttendantHandover;
use App\Modules\FuelStation\Models\CustomerProfile;
use App\Modules\FuelStation\Models\Investor;
use App\Modules\FuelStation\Models\Pump;
use App\Modules\FuelStation\Models\PumpReading;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\FuelStation\Models\SaleMetadata;
use App\Modules\FuelStation\Models\TankReading;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
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
    public function getHomeCards(string $companyId): array
    {
        $summary = $this->getSummary($companyId, Carbon::today());

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
}
