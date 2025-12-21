<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\FuelStation\Models\PumpReading;
use App\Modules\FuelStation\Models\TankReading;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockMovement;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TankReadingService
{
    public function __construct(
        private readonly GlPostingService $glPostingService,
    ) {}

    /**
     * Calculate system expected liters based on purchases/sales since last reading.
     *
     * Uses multiple sources:
     * 1. Stock movements (if table exists and has data)
     * 2. Pump readings for dispensed fuel
     * 3. Falls back to item current_stock if no movements
     */
    public function calculateSystemLiters(string $tankId, string $itemId, \DateTimeInterface $readingDate): float
    {
        $company = app(CurrentCompany::class)->get();

        // Get last posted tank reading for this tank
        $lastReading = TankReading::where('company_id', $company->id)
            ->where('tank_id', $tankId)
            ->where('status', 'posted')
            ->where('reading_date', '<', $readingDate)
            ->orderByDesc('reading_date')
            ->first();

        $startingLiters = $lastReading ? (float) $lastReading->dip_measurement_liters : 0;
        $since = $lastReading ? $lastReading->reading_date : now()->subYear();

        // Try to use stock movements if the table exists
        $purchases = 0.0;
        $salesFromMovements = 0.0;

        if ($this->stockMovementsTableExists()) {
            // Get purchases (stock movements IN for this item in this tank/warehouse)
            $purchases = (float) StockMovement::where('company_id', $company->id)
                ->where('warehouse_id', $tankId)
                ->where('item_id', $itemId)
                ->where('movement_date', '>=', $since)
                ->where('movement_date', '<=', $readingDate)
                ->whereIn('movement_type', ['purchase', 'adjustment_in', 'transfer_in', 'initial'])
                ->sum('quantity');

            // Get sales from stock movements (negative quantities or sale type)
            $salesFromMovements = (float) StockMovement::where('company_id', $company->id)
                ->where('warehouse_id', $tankId)
                ->where('item_id', $itemId)
                ->where('movement_date', '>=', $since)
                ->where('movement_date', '<=', $readingDate)
                ->whereIn('movement_type', ['sale', 'adjustment_out', 'transfer_out'])
                ->sum(DB::raw('ABS(quantity)'));
        }

        // Also get sales from pump readings (more reliable for fuel stations)
        // Pumps are linked to tanks, so we can calculate liters dispensed
        $salesFromPumps = (float) PumpReading::where('company_id', $company->id)
            ->whereHas('pump', function ($query) use ($tankId) {
                $query->where('tank_id', $tankId);
            })
            ->where('reading_date', '>=', $since)
            ->where('reading_date', '<=', $readingDate)
            ->sum('liters_dispensed');

        // Use the higher of the two sales figures (pump readings are usually more accurate)
        $sales = max($salesFromMovements, $salesFromPumps);

        // If we have no starting point and no movements, try to get current stock level
        if ($startingLiters <= 0 && $purchases <= 0 && $sales <= 0) {
            $item = Item::find($itemId);
            if ($item && $item->current_stock > 0) {
                return (float) $item->current_stock;
            }
        }

        return $startingLiters + $purchases - $sales;
    }

    /**
     * Check if the stock_movements table exists.
     */
    private function stockMovementsTableExists(): bool
    {
        static $exists = null;

        if ($exists === null) {
            try {
                $exists = Schema::connection('pgsql')->hasTable('inv.stock_movements');
            } catch (\Exception $e) {
                $exists = false;
            }
        }

        return $exists;
    }

    /**
     * Confirm a tank reading (draft → confirmed).
     */
    public function confirm(TankReading $reading, string $userId): TankReading
    {
        if ($reading->status !== TankReading::STATUS_DRAFT) {
            throw new \InvalidArgumentException('Only draft readings can be confirmed.');
        }

        $reading->update([
            'status' => TankReading::STATUS_CONFIRMED,
            'confirmed_by_user_id' => $userId,
            'confirmed_at' => now(),
        ]);

        return $reading->fresh();
    }

    /**
     * Post a tank reading (confirmed → posted) and create variance JE.
     */
    public function post(TankReading $reading): TankReading
    {
        if ($reading->status !== TankReading::STATUS_CONFIRMED) {
            throw new \InvalidArgumentException('Only confirmed readings can be posted.');
        }

        if ($reading->variance_type === TankReading::VARIANCE_NONE) {
            // No variance, just mark as posted
            $reading->update(['status' => TankReading::STATUS_POSTED]);
            return $reading->fresh();
        }

        return DB::transaction(function () use ($reading) {
            $company = app(CurrentCompany::class)->get();
            $item = Item::find($reading->item_id);

            // Get accounts
            $inventoryAccount = Account::where('company_id', $company->id)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->where(function ($q) {
                    $q->where('subtype', 'inventory')
                      ->orWhere('code', '1200');
                })
                ->first();

            $varianceAccount = $reading->variance_type === TankReading::VARIANCE_LOSS
                ? Account::where('company_id', $company->id)
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->where(function ($q) {
                        $q->where('code', '6300')
                          ->orWhere('name', 'like', '%Shrinkage%');
                    })
                    ->first()
                : Account::where('company_id', $company->id)
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->where(function ($q) {
                        $q->where('code', '4900')
                          ->orWhere('name', 'like', '%Variance Gain%');
                    })
                    ->first();

            if (!$inventoryAccount || !$varianceAccount) {
                throw new \RuntimeException('Required accounts not found. Ensure fuel_station COA is set up.');
            }

            // Calculate amount using avg_cost
            $amount = round(abs($reading->variance_liters) * ($item->avg_cost ?? 0), 2);

            if ($amount <= 0) {
                // No monetary impact, just mark as posted
                $reading->update(['status' => TankReading::STATUS_POSTED]);
                return $reading->fresh();
            }

            // Build GL entries
            $entries = [];
            $description = sprintf(
                'Fuel %s: %.2f liters of %s (%s)',
                $reading->variance_type === TankReading::VARIANCE_LOSS ? 'shrinkage' : 'gain',
                abs($reading->variance_liters),
                $item->name ?? 'Unknown',
                $reading->variance_reason ?? 'unspecified'
            );

            if ($reading->variance_type === TankReading::VARIANCE_LOSS) {
                // Loss: Dr Shrinkage Expense, Cr Inventory
                $entries[] = [
                    'account_id' => $varianceAccount->id,
                    'type' => 'debit',
                    'amount' => $amount,
                    'description' => 'Fuel shrinkage loss',
                ];
                $entries[] = [
                    'account_id' => $inventoryAccount->id,
                    'type' => 'credit',
                    'amount' => $amount,
                    'description' => 'Inventory reduction',
                ];
            } else {
                // Gain: Dr Inventory, Cr Variance Gain
                $entries[] = [
                    'account_id' => $inventoryAccount->id,
                    'type' => 'debit',
                    'amount' => $amount,
                    'description' => 'Inventory increase',
                ];
                $entries[] = [
                    'account_id' => $varianceAccount->id,
                    'type' => 'credit',
                    'amount' => $amount,
                    'description' => 'Fuel variance gain',
                ];
            }

            // Post using GlPostingService
            $transaction = $this->glPostingService->postBalancedTransaction([
                'company_id' => $company->id,
                'transaction_number' => 'VAR-' . strtoupper(substr($reading->id, 0, 8)),
                'transaction_type' => 'fuel_variance',
                'date' => $reading->reading_date,
                'currency' => strtoupper($company->base_currency ?? 'PKR'),
                'base_currency' => strtoupper($company->base_currency ?? 'PKR'),
                'description' => $description,
                'reference_type' => 'fuel.tank_readings',
                'reference_id' => $reading->id,
            ], $entries);

            // Update reading with transaction reference
            $reading->update([
                'status' => TankReading::STATUS_POSTED,
                'journal_entry_id' => $transaction->id, // Store transaction ID
            ]);

            return $reading->fresh();
        });
    }

    /**
     * Create a new tank reading with calculated system liters.
     */
    public function create(array $data): TankReading
    {
        $company = app(CurrentCompany::class)->get();

        // Get tank's linked item
        $tank = \App\Modules\Inventory\Models\Warehouse::find($data['tank_id']);
        if (!$tank || !$tank->linked_item_id) {
            throw new \InvalidArgumentException('Tank must be linked to a fuel item.');
        }

        $itemId = $tank->linked_item_id;
        $readingDate = $data['reading_date'] ?? now();

        // Calculate system expected liters
        $systemLiters = $this->calculateSystemLiters($data['tank_id'], $itemId, $readingDate);
        $dipLiters = (float) $data['dip_measurement_liters'];

        // Calculate variance
        $varianceLiters = round($dipLiters - $systemLiters, 2);
        $varianceType = $varianceLiters < 0
            ? TankReading::VARIANCE_LOSS
            : ($varianceLiters > 0 ? TankReading::VARIANCE_GAIN : TankReading::VARIANCE_NONE);

        return TankReading::create([
            'company_id' => $company->id,
            'tank_id' => $data['tank_id'],
            'item_id' => $itemId,
            'reading_date' => $readingDate,
            'reading_type' => $data['reading_type'] ?? 'spot_check',
            'dip_measurement_liters' => $dipLiters,
            'system_calculated_liters' => $systemLiters,
            'variance_liters' => $varianceLiters,
            'variance_type' => $varianceType,
            'variance_reason' => $data['variance_reason'] ?? null,
            'status' => TankReading::STATUS_DRAFT,
            'recorded_by_user_id' => auth()->id(),
            'notes' => $data['notes'] ?? null,
        ]);
    }
}
