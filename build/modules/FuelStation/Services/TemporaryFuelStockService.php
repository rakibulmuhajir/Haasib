<?php

namespace App\Modules\FuelStation\Services;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;

/** Fuel held outside meter tanks, valued only from this location's saved movements. */
class TemporaryFuelStockService
{
    public const CODE = 'TEMP-FUEL';

    public function location(Company $company, User $user): Warehouse
    {
        Company::whereKey($company->id)->lockForUpdate()->firstOrFail();

        $warehouse = Warehouse::firstOrCreate(['company_id' => $company->id, 'code' => self::CODE], [
            'name' => 'Temporary fuel storage', 'warehouse_type' => 'standard', 'is_active' => true,
            'is_primary' => false, 'created_by_user_id' => $user->id,
        ]);
        if (! $warehouse->is_active || $warehouse->warehouse_type !== 'standard') {
            throw new \InvalidArgumentException('Temporary fuel storage must be an active standard storage location.');
        }

        return $warehouse;
    }

    public function balance(string $companyId, string $itemId, ?string $date = null): array
    {
        $query = StockMovement::where('company_id', $companyId)->where('item_id', $itemId)
            ->whereIn('warehouse_id', Warehouse::where('company_id', $companyId)->where('code', self::CODE)->select('id'));
        if ($date) {
            $query->whereDate('movement_date', '<=', $date);
        }
        $row = $query->selectRaw('COALESCE(SUM(quantity),0) as quantity, COALESCE(SUM(total_cost),0) as value')->first();

        return ['quantity' => round((float) $row->quantity, 3), 'value' => round((float) $row->value, 2)];
    }

    public function assertDate(string $companyId, string $itemId, string $date): void
    {
        $later = StockMovement::where('company_id', $companyId)->where('item_id', $itemId)
            ->whereIn('warehouse_id', Warehouse::where('company_id', $companyId)->where('code', self::CODE)->select('id'))
            ->whereDate('movement_date', '>', $date)->exists();
        if ($later) {
            throw new \InvalidArgumentException('Temporary fuel has later movements. Use its latest movement date or a later date.');
        }
    }

    public function issue(Company $company, Item $item, Invoice $invoice, float $quantity, string $date, User $user): void
    {
        $warehouse = $this->location($company, $user);
        if (! $item->fuel_category || ! $item->track_inventory || ! $warehouse->is_active || $warehouse->warehouse_type !== 'standard') {
            throw new \InvalidArgumentException('Select a tracked fuel product and an active temporary storage location.');
        }
        $this->assertDate($company->id, $item->id, $date);
        $balance = $this->balance($company->id, $item->id, $date);
        $quantity = round($quantity, 3);
        if ($quantity <= 0 || $quantity > $balance['quantity'] + 0.0005) {
            throw new \InvalidArgumentException('Not enough temporary fuel on this date. Available: '.$balance['quantity'].' L.');
        }
        if (! $item->asset_account_id || ! $item->expense_account_id) {
            throw new \InvalidArgumentException('Set the product inventory and cost-of-sales accounts before selling temporary fuel.');
        }
        $cost = $quantity === $balance['quantity'] ? $balance['value'] : round($balance['value'] * $quantity / $balance['quantity'], 2);
        if ($cost <= 0) {
            throw new \InvalidArgumentException('Temporary fuel needs a recorded positive purchase cost.');
        }
        $transaction = app(GlPostingService::class)->postBalancedTransaction([
            'company_id' => $company->id, 'transaction_type' => 'fuel_temporary_cogs', 'date' => $date,
            'currency' => $company->base_currency, 'description' => 'Temporary fuel sold · '.$invoice->invoice_number,
            'reference_type' => 'acct.invoices', 'reference_id' => $invoice->id,
        ], [
            ['account_id' => $item->expense_account_id, 'type' => 'debit', 'amount' => $cost],
            ['account_id' => $item->asset_account_id, 'type' => 'credit', 'amount' => $cost],
        ]);
        StockMovement::create([
            'company_id' => $company->id, 'warehouse_id' => $warehouse->id, 'item_id' => $item->id,
            'movement_date' => $date, 'movement_type' => 'sale', 'quantity' => -$quantity,
            'unit_cost' => round($cost / $quantity, 6), 'total_cost' => -$cost,
            'gl_transaction_id' => $transaction->id, 'reference_type' => 'acct.invoices', 'reference_id' => $invoice->id,
            'created_by_user_id' => $user->id,
        ]);
    }

    public function reverse(Invoice $invoice, string $date, ?string $actorId = null): void
    {
        $movements = StockMovement::where('company_id', $invoice->company_id)->where('reference_type', 'acct.invoices')
            ->where('reference_id', $invoice->id)->where('movement_type', 'sale')
            ->whereIn('warehouse_id', Warehouse::where('company_id', $invoice->company_id)->where('code', self::CODE)->select('id'))->get();
        foreach ($movements as $movement) {
            if (StockMovement::where('company_id', $invoice->company_id)->where('related_movement_id', $movement->id)->exists()) {
                continue;
            }
            Warehouse::whereKey($movement->warehouse_id)->lockForUpdate()->firstOrFail();
            $this->assertDate($invoice->company_id, $movement->item_id, $date);
            $transaction = app(PostingService::class)->reverseTransaction(
                Transaction::where('company_id', $invoice->company_id)->findOrFail($movement->gl_transaction_id),
                'Temporary fuel sale cancelled', $date);
            StockMovement::create([
                'company_id' => $movement->company_id, 'warehouse_id' => $movement->warehouse_id, 'item_id' => $movement->item_id,
                'movement_date' => $date, 'movement_type' => 'return_in', 'quantity' => abs((float) $movement->quantity),
                'unit_cost' => $movement->unit_cost, 'total_cost' => abs((float) $movement->total_cost),
                'gl_transaction_id' => $transaction->id, 'reference_type' => 'acct.invoices', 'reference_id' => $invoice->id,
                'related_movement_id' => $movement->id, 'created_by_user_id' => $actorId,
            ]);
        }
    }
}
