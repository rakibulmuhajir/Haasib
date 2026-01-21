<?php

namespace App\Modules\FuelStation\Services;

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\Inventory\Models\Item;
use App\Services\CurrentCompany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for handling OGRA rate changes with stock revaluation.
 *
 * When OGRA announces new rates:
 * 1. Create a rate change record
 * 2. Calculate revaluation amount for existing stock
 * 3. Update Item.avg_cost to the new purchase rate
 * 4. Post a GL entry for the revaluation gain/loss
 *
 * Journal Entry for Revaluation:
 * - If new rate > old avg_cost (rate increase):
 *   DR Inventory (increase asset)
 *   CR Inventory Revaluation Gain (other income)
 *
 * - If new rate < old avg_cost (rate decrease):
 *   DR Inventory Revaluation Loss (expense)
 *   CR Inventory (decrease asset)
 */
class RateChangeService
{
    public function __construct(
        private readonly GlPostingService $glPostingService,
    ) {}

    /**
     * Create a rate change with stock revaluation.
     *
     * @param array $data Validated rate change data
     * @return RateChange
     */
    public function createWithRevaluation(array $data): RateChange
    {
        $company = app(CurrentCompany::class)->get();

        return DB::transaction(function () use ($data, $company) {
            // Get the fuel item
            $item = Item::where('company_id', $company->id)
                ->where('id', $data['item_id'])
                ->whereNotNull('fuel_category')
                ->firstOrFail();

            // Get previous rate for margin impact calculation
            $previousRate = RateChange::getCurrentRate($company->id, $data['item_id']);
            $previousAvgCost = (float) ($item->avg_cost ?? 0);

            // Get current stock quantity from item or use provided value
            $stockQuantity = $data['stock_quantity_at_change'] ?? (float) ($item->current_stock ?? 0);

            // Calculate margin impact (informational)
            $marginImpact = null;
            if ($previousRate && $stockQuantity > 0) {
                $oldMargin = (float) $previousRate->sale_rate - (float) $previousRate->purchase_rate;
                $newMargin = (float) $data['sale_rate'] - (float) $data['purchase_rate'];
                $marginImpact = round(($newMargin - $oldMargin) * $stockQuantity, 2);
            }

            // Calculate revaluation amount
            $newPurchaseRate = (float) $data['purchase_rate'];
            $revaluationAmount = 0;

            if ($stockQuantity > 0 && $previousAvgCost > 0) {
                // Revaluation = (new rate - old avg cost) * stock quantity
                $revaluationAmount = round(($newPurchaseRate - $previousAvgCost) * $stockQuantity, 2);
            }

            // Create the rate change record
            $rateChange = RateChange::create([
                'company_id' => $company->id,
                'item_id' => $data['item_id'],
                'effective_date' => $data['effective_date'],
                'purchase_rate' => $data['purchase_rate'],
                'sale_rate' => $data['sale_rate'],
                'stock_quantity_at_change' => $stockQuantity > 0 ? $stockQuantity : null,
                'margin_impact' => $marginImpact,
                'revaluation_amount' => $revaluationAmount != 0 ? $revaluationAmount : null,
                'previous_avg_cost' => $previousAvgCost > 0 ? $previousAvgCost : null,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => auth()->id(),
            ]);

            // Only post GL entry and update avg_cost if there's actual stock to revalue
            if ($stockQuantity > 0 && abs($revaluationAmount) > 0.01) {
                // Post the revaluation GL entry
                $transaction = $this->postRevaluationEntry(
                    $company,
                    $item,
                    $rateChange,
                    $revaluationAmount,
                    $stockQuantity,
                    $previousAvgCost,
                    $newPurchaseRate
                );

                // Update rate change with journal entry reference
                $rateChange->update(['journal_entry_id' => $transaction->id]);

                // Update Item.avg_cost to the new purchase rate
                $item->update(['avg_cost' => $newPurchaseRate]);

                Log::info('Fuel stock revaluation posted', [
                    'company_id' => $company->id,
                    'item_id' => $item->id,
                    'item_name' => $item->name,
                    'previous_avg_cost' => $previousAvgCost,
                    'new_avg_cost' => $newPurchaseRate,
                    'stock_quantity' => $stockQuantity,
                    'revaluation_amount' => $revaluationAmount,
                    'transaction_id' => $transaction->id,
                ]);
            } elseif ($stockQuantity <= 0) {
                // No stock to revalue, but still update avg_cost for future purchases
                $item->update(['avg_cost' => $newPurchaseRate]);

                Log::info('Fuel rate changed (no stock to revalue)', [
                    'company_id' => $company->id,
                    'item_id' => $item->id,
                    'new_avg_cost' => $newPurchaseRate,
                ]);
            }

            return $rateChange;
        });
    }

    /**
     * Post the revaluation GL entry.
     */
    private function postRevaluationEntry(
        Company $company,
        Item $item,
        RateChange $rateChange,
        float $revaluationAmount,
        float $stockQuantity,
        float $previousAvgCost,
        float $newPurchaseRate
    ) {
        // Find required accounts
        $inventoryAccount = $this->findInventoryAccount($company->id);
        $revaluationAccount = $this->findRevaluationAccount($company->id, $revaluationAmount > 0);

        if (!$inventoryAccount) {
            throw new \RuntimeException('Inventory account not found. Ensure fuel_station COA is set up.');
        }

        if (!$revaluationAccount) {
            throw new \RuntimeException('Inventory revaluation account not found. Ensure fuel_station COA is set up.');
        }

        $absAmount = abs($revaluationAmount);
        $isGain = $revaluationAmount > 0;

        $description = sprintf(
            'OGRA rate change revaluation: %s - %.2f liters @ %s %s → %s %s (%s)',
            $item->name,
            $stockQuantity,
            $company->base_currency,
            number_format($previousAvgCost, 2),
            $company->base_currency,
            number_format($newPurchaseRate, 2),
            $isGain ? 'gain' : 'loss'
        );

        $entries = [];

        if ($isGain) {
            // Rate increased: DR Inventory, CR Revaluation Gain
            $entries[] = [
                'account_id' => $inventoryAccount->id,
                'type' => 'debit',
                'amount' => $absAmount,
                'description' => 'Inventory revaluation increase',
            ];
            $entries[] = [
                'account_id' => $revaluationAccount->id,
                'type' => 'credit',
                'amount' => $absAmount,
                'description' => 'OGRA rate increase gain',
            ];
        } else {
            // Rate decreased: DR Revaluation Loss, CR Inventory
            $entries[] = [
                'account_id' => $revaluationAccount->id,
                'type' => 'debit',
                'amount' => $absAmount,
                'description' => 'OGRA rate decrease loss',
            ];
            $entries[] = [
                'account_id' => $inventoryAccount->id,
                'type' => 'credit',
                'amount' => $absAmount,
                'description' => 'Inventory revaluation decrease',
            ];
        }

        return $this->glPostingService->postBalancedTransaction([
            'company_id' => $company->id,
            'transaction_number' => 'REVAL-' . strtoupper(substr($rateChange->id, 0, 8)),
            'transaction_type' => 'inventory_revaluation',
            'date' => $rateChange->effective_date,
            'currency' => strtoupper($company->base_currency ?? 'PKR'),
            'base_currency' => strtoupper($company->base_currency ?? 'PKR'),
            'description' => $description,
            'reference_type' => 'fuel.rate_changes',
            'reference_id' => $rateChange->id,
        ], $entries);
    }

    /**
     * Find the inventory account for fuel items.
     */
    private function findInventoryAccount(string $companyId): ?Account
    {
        return Account::where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->where('subtype', 'inventory')
                  ->orWhere('code', '1200');
            })
            ->first();
    }

    /**
     * Find the revaluation account (gain or loss).
     *
     * For gains: Other Income account (code 4900 or name like 'Revaluation Gain')
     * For losses: Expense account (code 6310 or name like 'Revaluation Loss')
     */
    private function findRevaluationAccount(string $companyId, bool $isGain): ?Account
    {
        if ($isGain) {
            // Look for gain account
            return Account::where('company_id', $companyId)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->where(function ($q) {
                    $q->where('code', '4900')
                      ->orWhere('code', '4910')
                      ->orWhere('name', 'like', '%Revaluation Gain%')
                      ->orWhere('name', 'like', '%Variance Gain%');
                })
                ->first();
        }

        // Look for loss account
        return Account::where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->where('code', '6300')
                  ->orWhere('code', '6310')
                  ->orWhere('name', 'like', '%Revaluation Loss%')
                  ->orWhere('name', 'like', '%Shrinkage%');
            })
            ->first();
    }

    /**
     * Get current stock for a fuel item across all tanks.
     */
    public function getCurrentStock(string $companyId, string $itemId): float
    {
        $item = Item::where('company_id', $companyId)
            ->where('id', $itemId)
            ->first();

        return (float) ($item?->current_stock ?? 0);
    }
}
