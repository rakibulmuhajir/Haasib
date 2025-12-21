<?php

namespace App\Modules\Accounting\Actions\Bill;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillLineItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Receive goods for a bill - separate from receiving the bill document.
 *
 * Supports:
 * - Full receipt: receive all items at once
 * - Partial receipt: receive specific quantities per line
 * - Multiple receipts: receive remaining quantities later
 */
class ReceiveGoodsAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|string',
            'warehouse_id' => 'nullable|uuid', // Default warehouse for all lines
            'lines' => 'nullable|array',
            'lines.*.line_id' => 'required_with:lines|uuid',
            'lines.*.quantity' => 'required_with:lines|numeric|min:0.01',
            'lines.*.warehouse_id' => 'nullable|uuid', // Override per line
        ];
    }

    public function permission(): ?string
    {
        return Permissions::BILL_UPDATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $bill = Bill::where('company_id', $company->id)
            ->with('lineItems')
            ->findOrFail($params['id']);

        if ($bill->status === 'void' || $bill->status === 'cancelled') {
            throw new \InvalidArgumentException('Cannot receive goods for a void/cancelled bill');
        }

        $defaultWarehouseId = $params['warehouse_id'] ?? null;
        $linesToReceive = $this->prepareLinesToReceive($bill, $params, $defaultWarehouseId);

        if (empty($linesToReceive)) {
            throw new \InvalidArgumentException('No items to receive or all items already fully received');
        }

        $receivedCount = 0;

        DB::transaction(function () use ($bill, $linesToReceive, &$receivedCount) {
            foreach ($linesToReceive as $lineData) {
                $this->receiveLineItem($lineData);
                $receivedCount++;
            }

            // Check if all line items are fully received
            $bill->refresh();
            $allReceived = $bill->lineItems->every(function ($line) {
                return $line->quantity_received >= $line->quantity;
            });

            if ($allReceived && ! $bill->goods_received_at) {
                $bill->goods_received_at = now();
                $bill->updated_by_user_id = Auth::id();
                $bill->save();
            }
        });

        $fullyReceived = $bill->goods_received_at !== null;

        return [
            'message' => $fullyReceived
                ? "All goods received for Bill #{$bill->bill_number}"
                : "Received {$receivedCount} line(s) for Bill #{$bill->bill_number}",
            'fully_received' => $fullyReceived,
        ];
    }

    /**
     * Prepare the list of line items to receive with quantities.
     */
    protected function prepareLinesToReceive(Bill $bill, array $params, ?string $defaultWarehouseId): array
    {
        $linesToReceive = [];

        // If specific lines provided, use those
        if (! empty($params['lines'])) {
            foreach ($params['lines'] as $lineInput) {
                $line = $bill->lineItems->firstWhere('id', $lineInput['line_id']);
                if (! $line) {
                    continue;
                }

                $remainingQty = $line->quantity - $line->quantity_received;
                $qtyToReceive = min($lineInput['quantity'], $remainingQty);

                if ($qtyToReceive <= 0) {
                    continue;
                }

                $linesToReceive[] = [
                    'line' => $line,
                    'quantity' => $qtyToReceive,
                    'warehouse_id' => $lineInput['warehouse_id'] ?? $line->warehouse_id ?? $defaultWarehouseId,
                    'bill' => $bill,
                ];
            }
        } else {
            // Full receipt - receive all remaining quantities
            foreach ($bill->lineItems as $line) {
                $remainingQty = $line->quantity - $line->quantity_received;

                if ($remainingQty <= 0) {
                    continue;
                }

                $linesToReceive[] = [
                    'line' => $line,
                    'quantity' => $remainingQty,
                    'warehouse_id' => $line->warehouse_id ?? $defaultWarehouseId,
                    'bill' => $bill,
                ];
            }
        }

        return $linesToReceive;
    }

    /**
     * Receive a single line item and create stock movement if applicable.
     */
    protected function receiveLineItem(array $lineData): void
    {
        /** @var BillLineItem $line */
        $line = $lineData['line'];
        $quantity = $lineData['quantity'];
        $warehouseId = $lineData['warehouse_id'];
        /** @var Bill $bill */
        $bill = $lineData['bill'];

        // Update the received quantity
        $line->quantity_received = $line->quantity_received + $quantity;
        $line->save();

        // Create stock movement if this is an inventory item
        if ($line->item_id && $warehouseId) {
            $this->createStockMovement($bill, $line, $quantity, $warehouseId);
        }
    }

    /**
     * Create stock movement for inventory item (if module enabled).
     */
    protected function createStockMovement(Bill $bill, BillLineItem $line, float $quantity, string $warehouseId): void
    {
        $company = $bill->company;
        if (! $company || ! $company->isModuleEnabled('inventory')) {
            return;
        }

        $serviceClass = 'App\\Modules\\Inventory\\Services\\InventoryService';
        if (! class_exists($serviceClass)) {
            return;
        }

        /** @var \App\Modules\Inventory\Services\InventoryService $inventoryService */
        $inventoryService = app($serviceClass);
        $inventoryService->receiveLineItem($bill, $line, $quantity, $warehouseId);
    }
}
