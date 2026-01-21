<?php

namespace App\Modules\Accounting\Actions\Bill;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillLineItem;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\Inventory\Models\StockReceipt;
use App\Modules\Inventory\Models\StockReceiptLine;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Receive goods for a bill - separate from receiving the bill document.
 *
 * Supports:
 * - Full receipt: receive all items at once
 * - Partial receipt: receive specific quantities per line
 * - Multiple receipts: receive remaining quantities later
 * - Variance tracking: expected vs received with GL posting
 */
class ReceiveGoodsAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|string',
            'receipt_date' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
            'warehouse_id' => 'nullable|uuid', // Default warehouse for all lines
            'lines' => 'nullable|array',
            'lines.*.line_id' => 'required_with:lines|uuid',
            'lines.*.quantity' => 'nullable|numeric|min:0.01',
            'lines.*.expected_quantity' => 'nullable|numeric|min:0.01',
            'lines.*.received_quantity' => 'nullable|numeric|min:0.01',
            'lines.*.variance_reason' => 'nullable|string|in:transit_loss,spillage,temperature_adjustment,measurement_error,other',
            'lines.*.warehouse_id' => 'nullable|uuid', // Override per line
            'lines.*.notes' => 'nullable|string|max:1000',
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
            ->with(['lineItems', 'lineItems.item'])
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
        $receiptDate = $params['receipt_date'] ?? now()->toDateString();
        $receiptNotes = $params['notes'] ?? null;

        DB::transaction(function () use ($bill, $linesToReceive, $receiptDate, $receiptNotes, &$receivedCount) {
            $receipt = StockReceipt::create([
                'company_id' => $bill->company_id,
                'bill_id' => $bill->id,
                'receipt_date' => $receiptDate,
                'notes' => $receiptNotes,
                'created_by_user_id' => Auth::id(),
            ]);

            $varianceDebits = [];
            $varianceCredits = [];

            foreach ($linesToReceive as $lineData) {
                $lineData['receipt_date'] = $receiptDate;
                $movement = $this->receiveLineItem($lineData);
                $receiptLine = $this->storeReceiptLine($receipt, $lineData, $movement);
                $this->accumulateVarianceEntries($bill, $lineData, $receiptLine, $varianceDebits, $varianceCredits);
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

            $varianceEntries = $this->buildVarianceEntries($varianceDebits, $varianceCredits);
            if (! empty($varianceEntries)) {
                $transaction = app(GlPostingService::class)->postBalancedTransaction([
                    'company_id' => $bill->company_id,
                    'transaction_type' => 'adjustment',
                    'date' => $receiptDate,
                    'currency' => $bill->currency,
                    'base_currency' => $bill->base_currency ?? $bill->currency,
                    'exchange_rate' => $bill->exchange_rate,
                    'description' => "Receipt variance for Bill {$bill->bill_number}",
                    'reference_type' => 'inv.stock_receipts',
                    'reference_id' => $receipt->id,
                    'metadata' => [
                        'bill_id' => $bill->id,
                        'bill_number' => $bill->bill_number,
                    ],
                ], $varianceEntries);

                $receipt->variance_transaction_id = $transaction->id;
                $receipt->save();
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

                if (! $line->item_id) {
                    continue;
                }

                if ($line->item && $line->item->track_inventory !== true) {
                    continue;
                }

                $remainingQty = $line->quantity - $line->quantity_received;
                if ($remainingQty <= 0) {
                    continue;
                }

                $expectedInput = $lineInput['expected_quantity'] ?? null;
                $receivedInput = $lineInput['received_quantity'] ?? ($lineInput['quantity'] ?? null);
                $expected = $expectedInput !== null ? (float) $expectedInput : null;
                $received = $receivedInput !== null ? (float) $receivedInput : null;

                if ($expected === null) {
                    $expected = $received !== null ? min($remainingQty, $received) : $remainingQty;
                }

                if ($received === null) {
                    $received = $expected;
                }

                if ($expected <= 0 || $received <= 0) {
                    continue;
                }

                if ($expected > $remainingQty + 0.0001) {
                    throw new \InvalidArgumentException('Expected quantity exceeds remaining quantity for a bill line.');
                }

                if ($received > $remainingQty + 0.0001) {
                    throw new \InvalidArgumentException('Received quantity exceeds remaining quantity for a bill line.');
                }

                $warehouseId = $lineInput['warehouse_id'] ?? $line->warehouse_id ?? $defaultWarehouseId;
                if (! $warehouseId) {
                    throw new \InvalidArgumentException('Warehouse is required to receive inventory items.');
                }

                $linesToReceive[] = [
                    'line' => $line,
                    'expected_quantity' => $expected,
                    'received_quantity' => $received,
                    'warehouse_id' => $warehouseId,
                    'bill' => $bill,
                    'variance_reason' => $lineInput['variance_reason'] ?? null,
                    'notes' => $lineInput['notes'] ?? null,
                ];
            }
        } else {
            // Full receipt - receive all remaining quantities
            foreach ($bill->lineItems as $line) {
                if (! $line->item_id) {
                    continue;
                }

                if ($line->item && $line->item->track_inventory !== true) {
                    continue;
                }

                $remainingQty = $line->quantity - $line->quantity_received;

                if ($remainingQty <= 0) {
                    continue;
                }

                $warehouseId = $line->warehouse_id ?? $defaultWarehouseId;
                if (! $warehouseId) {
                    throw new \InvalidArgumentException('Warehouse is required to receive inventory items.');
                }

                $linesToReceive[] = [
                    'line' => $line,
                    'expected_quantity' => $remainingQty,
                    'received_quantity' => $remainingQty,
                    'warehouse_id' => $warehouseId,
                    'bill' => $bill,
                    'variance_reason' => null,
                    'notes' => null,
                ];
            }
        }

        return $linesToReceive;
    }

    /**
     * Receive a single line item and create stock movement if applicable.
     */
    protected function receiveLineItem(array $lineData): ?StockMovement
    {
        /** @var BillLineItem $line */
        $line = $lineData['line'];
        $quantity = $lineData['received_quantity'];
        $warehouseId = $lineData['warehouse_id'];
        /** @var Bill $bill */
        $bill = $lineData['bill'];

        // Update the received quantity
        $line->quantity_received = $line->quantity_received + $quantity;
        $line->save();

        // Create stock movement if this is an inventory item
        if ($line->item_id && $warehouseId) {
            return $this->createStockMovement($bill, $line, $quantity, $warehouseId, $lineData['receipt_date'] ?? null);
        }

        return null;
    }

    /**
     * Create stock movement for inventory item (if module enabled).
     */
    protected function createStockMovement(Bill $bill, BillLineItem $line, float $quantity, string $warehouseId, ?string $movementDate = null): ?StockMovement
    {
        $company = $bill->company;
        if (! $company || ! $company->isModuleEnabled('inventory')) {
            return null;
        }

        $serviceClass = 'App\\Modules\\Inventory\\Services\\InventoryService';
        if (! class_exists($serviceClass)) {
            return null;
        }

        /** @var \App\Modules\Inventory\Services\InventoryService $inventoryService */
        $inventoryService = app($serviceClass);
        return $inventoryService->receiveLineItem($bill, $line, $quantity, $warehouseId, $movementDate);
    }

    protected function storeReceiptLine(StockReceipt $receipt, array $lineData, ?StockMovement $movement): StockReceiptLine
    {
        /** @var BillLineItem $line */
        $line = $lineData['line'];
        $expected = (float) $lineData['expected_quantity'];
        $received = (float) $lineData['received_quantity'];
        $varianceQty = round($received - $expected, 3);

        $unitCost = (float) $line->unit_price;
        $totalCost = round($received * $unitCost, 2);
        $varianceCost = round($varianceQty * $unitCost, 2);

        if (abs($varianceQty) > 0.0001 && empty($lineData['variance_reason'])) {
            throw new \InvalidArgumentException('Variance reason is required when received quantity differs from expected.');
        }

        return StockReceiptLine::create([
            'company_id' => $receipt->company_id,
            'stock_receipt_id' => $receipt->id,
            'bill_line_item_id' => $line->id,
            'item_id' => $line->item_id,
            'warehouse_id' => $lineData['warehouse_id'],
            'expected_quantity' => $expected,
            'received_quantity' => $received,
            'variance_quantity' => $varianceQty,
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
            'variance_cost' => $varianceCost,
            'variance_reason' => $lineData['variance_reason'],
            'stock_movement_id' => $movement?->id,
            'notes' => $lineData['notes'],
            'created_by_user_id' => Auth::id(),
        ]);
    }

    protected function accumulateVarianceEntries(Bill $bill, array $lineData, StockReceiptLine $receiptLine, array &$debits, array &$credits): void
    {
        $varianceCost = (float) $receiptLine->variance_cost;
        if (abs($varianceCost) < 0.01) {
            return;
        }

        $company = $bill->company;
        if (! $company) {
            return;
        }

        $inventoryAccountId = $lineData['line']->item?->asset_account_id
            ?? $lineData['line']->expense_account_id
            ?? $company->expense_account_id;

        if (! $inventoryAccountId) {
            throw new \RuntimeException('Inventory account is required to record receipt variance.');
        }

        if ($varianceCost < 0) {
            $lossAccountId = $company->transit_loss_account_id;
            if (! $lossAccountId) {
                throw new \RuntimeException('Transit loss account is required to record receipt variance.');
            }

            $amount = abs($varianceCost);
            $debits[$lossAccountId] = round(($debits[$lossAccountId] ?? 0) + $amount, 2);
            $credits[$inventoryAccountId] = round(($credits[$inventoryAccountId] ?? 0) + $amount, 2);
            return;
        }

        $gainAccountId = $company->transit_gain_account_id;
        if (! $gainAccountId) {
            throw new \RuntimeException('Transit gain account is required to record receipt variance.');
        }

        $debits[$inventoryAccountId] = round(($debits[$inventoryAccountId] ?? 0) + $varianceCost, 2);
        $credits[$gainAccountId] = round(($credits[$gainAccountId] ?? 0) + $varianceCost, 2);
    }

    /**
     * @param array<string, float> $debits
     * @param array<string, float> $credits
     * @return array<int, array{account_id:string,type:'debit'|'credit',amount:float,description?:string}>
     */
    protected function buildVarianceEntries(array $debits, array $credits): array
    {
        $entries = [];
        foreach ($debits as $accountId => $amount) {
            if ($amount <= 0) {
                continue;
            }
            $entries[] = [
                'account_id' => $accountId,
                'type' => 'debit',
                'amount' => $amount,
                'description' => 'Receipt variance',
            ];
        }

        foreach ($credits as $accountId => $amount) {
            if ($amount <= 0) {
                continue;
            }
            $entries[] = [
                'account_id' => $accountId,
                'type' => 'credit',
                'amount' => $amount,
                'description' => 'Receipt variance',
            ];
        }

        return $entries;
    }
}
