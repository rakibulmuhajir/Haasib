<?php

namespace App\Modules\Inventory\Services;

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\DefaultAccountProvisioner;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class StockAdjustmentService
{
    public function __construct(
        private readonly GlPostingService $postingService,
        private readonly DefaultAccountProvisioner $defaultAccountProvisioner,
    ) {}

    /**
     * @param array{
     *   warehouse_id:string,
     *   item_id:string,
     *   quantity:float|int|string,
     *   unit_cost?:float|int|string|null,
     *   reason?:string|null,
     *   notes?:string|null,
     *   movement_date?:string|null,
     * } $data
     */
    public function record(Company $company, array $data, ?string $userId): StockMovement
    {
        return DB::transaction(function () use ($company, $data, $userId) {
            $item = Item::query()
                ->where('company_id', $company->id)
                ->where('track_inventory', true)
                ->findOrFail($data['item_id']);

            Warehouse::query()
                ->where('company_id', $company->id)
                ->where('is_active', true)
                ->findOrFail($data['warehouse_id']);

            $quantity = round((float) $data['quantity'], 3);
            if (abs($quantity) < 0.001) {
                throw new \InvalidArgumentException('Adjustment quantity cannot be zero.');
            }

            $unitCost = $this->resolveUnitCost($item, $data['unit_cost'] ?? null);
            $totalCost = round(abs($quantity) * $unitCost, 2);
            if ($totalCost < 0.01) {
                throw new \InvalidArgumentException('Enter a value per unit so the stock adjustment can post to accounts.');
            }

            $movementType = $quantity > 0 ? 'adjustment_in' : 'adjustment_out';
            $movement = StockMovement::create([
                'company_id' => $company->id,
                'warehouse_id' => $data['warehouse_id'],
                'item_id' => $item->id,
                'movement_date' => $data['movement_date'] ?? now()->toDateString(),
                'movement_type' => $movementType,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $quantity > 0 ? $totalCost : -$totalCost,
                'reference_type' => 'inv.stock_adjustments',
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $userId,
            ]);

            $transaction = $this->postAdjustment($company->fresh(), $item, $movement, $totalCost);
            $movement->forceFill(['gl_transaction_id' => $transaction->id])->save();

            if ($quantity > 0) {
                $this->updateItemCost($item, $quantity, $unitCost);
            }

            return $movement->fresh();
        });
    }

    private function resolveUnitCost(Item $item, mixed $input): float
    {
        if ($input !== null && $input !== '') {
            return round((float) $input, 6);
        }

        $cost = (float) ($item->avg_cost ?: $item->cost_price ?: 0);

        return round($cost, 6);
    }

    private function postAdjustment(Company $company, Item $item, StockMovement $movement, float $amount): \App\Modules\Accounting\Models\Transaction
    {
        $inventoryAccountId = $this->resolveInventoryAccount($company, $item);
        $transitAccounts = $this->defaultAccountProvisioner->ensureTransitAccounts($company);

        $isIncrease = (float) $movement->quantity > 0;
        $offsetAccountId = $isIncrease
            ? $transitAccounts['transit_gain_account_id']
            : $transitAccounts['transit_loss_account_id'];

        $entries = $isIncrease
            ? [
                [
                    'account_id' => $inventoryAccountId,
                    'type' => 'debit',
                    'amount' => $amount,
                    'description' => "Stock increase: {$item->name}",
                ],
                [
                    'account_id' => $offsetAccountId,
                    'type' => 'credit',
                    'amount' => $amount,
                    'description' => "Stock gain: {$item->name}",
                ],
            ]
            : [
                [
                    'account_id' => $offsetAccountId,
                    'type' => 'debit',
                    'amount' => $amount,
                    'description' => "Stock loss: {$item->name}",
                ],
                [
                    'account_id' => $inventoryAccountId,
                    'type' => 'credit',
                    'amount' => $amount,
                    'description' => "Stock decrease: {$item->name}",
                ],
            ];

        return $this->postingService->postBalancedTransaction([
            'company_id' => $company->id,
            'transaction_type' => 'adjustment',
            'date' => $movement->movement_date,
            'currency' => $company->base_currency,
            'base_currency' => $company->base_currency,
            'description' => $isIncrease
                ? "Stock increase for {$item->name}"
                : "Stock decrease for {$item->name}",
            'reference_type' => 'inv.stock_movements',
            'reference_id' => $movement->id,
            'metadata' => [
                'movement_type' => $movement->movement_type,
                'item_id' => $item->id,
                'warehouse_id' => $movement->warehouse_id,
                'quantity' => (float) $movement->quantity,
                'unit_cost' => (float) $movement->unit_cost,
            ],
        ], $entries);
    }

    private function resolveInventoryAccount(Company $company, Item $item): string
    {
        if ($item->asset_account_id) {
            return $item->asset_account_id;
        }

        $accountId = Account::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->where('subtype', 'inventory')
            ->orderBy('code')
            ->value('id');

        if (! $accountId) {
            throw new \RuntimeException('Inventory account is required to post stock adjustment.');
        }

        return $accountId;
    }

    private function updateItemCost(Item $item, float $newQty, float $newUnitCost): void
    {
        $currentQty = max(0.0, (float) $item->stockLevels()->sum('quantity') - $newQty);
        $currentCost = (float) ($item->avg_cost ?: $item->cost_price ?: $newUnitCost);
        $totalQty = $currentQty + $newQty;

        if ($totalQty <= 0) {
            return;
        }

        $newAvgCost = (($currentQty * $currentCost) + ($newQty * $newUnitCost)) / $totalQty;
        $item->update(['cost_price' => round($newAvgCost, 6)]);
    }
}
