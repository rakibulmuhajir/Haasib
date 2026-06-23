<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\FuelStation\Models\TankReading;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockReceiptLine;
use App\Modules\Inventory\Models\Warehouse;
use Carbon\Carbon;

class StockVarianceReportService
{
    /**
     * @return array{
     *   filters: array<string,string>,
     *   totals: array<string,float|int>,
     *   physicalRows: array<int,array<string,mixed>>,
     *   claimRows: array<int,array<string,mixed>>,
     *   tanks: array<int,array{id:string,name:string}>,
     *   products: array<int,array{id:string,name:string}>
     * }
     */
    public function run(
        string $companyId,
        string $startDate,
        string $endDate,
        string $tankId = 'all',
        string $productId = 'all',
        string $varianceType = 'all',
        string $claimStatus = 'all',
    ): array {
        $physicalRows = $this->physicalRows($companyId, $startDate, $endDate, $tankId, $productId, $varianceType);
        $claimRows = $this->claimRows($companyId, $startDate, $endDate, $tankId, $productId, $claimStatus);

        return [
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'tank_id' => $tankId,
                'product_id' => $productId,
                'variance_type' => $varianceType,
                'claim_status' => $claimStatus,
            ],
            'totals' => $this->totals($physicalRows, $claimRows),
            'physicalRows' => $physicalRows,
            'claimRows' => $claimRows,
            'tanks' => $this->locations($companyId),
            'products' => $this->products($companyId),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function physicalRows(string $companyId, string $startDate, string $endDate, string $tankId, string $productId, string $varianceType): array
    {
        $query = TankReading::where('company_id', $companyId)
            ->with(['tank:id,name,code,capacity,linked_item_id', 'item:id,name,sku,avg_cost,cost_price'])
            ->whereBetween('reading_date', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ])
            ->where('variance_type', '!=', TankReading::VARIANCE_NONE)
            ->orderByDesc('reading_date');

        if ($tankId !== 'all') {
            $query->where('tank_id', $tankId);
        }

        if ($productId !== 'all') {
            $query->where('item_id', $productId);
        }

        if ($varianceType !== 'all') {
            $query->where('variance_type', $varianceType);
        }

        return $query->get()->map(function (TankReading $reading) {
            $unitCost = (float) ($reading->item?->avg_cost ?: $reading->item?->cost_price ?: 0);
            $varianceLiters = (float) $reading->variance_liters;
            $value = round(abs($varianceLiters) * $unitCost, 2);

            return [
                'id' => $reading->id,
                'date' => $reading->reading_date?->toDateString(),
                'date_label' => $reading->reading_date?->format('d M Y'),
                'time_label' => $reading->reading_date?->format('h:i a'),
                'tank_id' => $reading->tank_id,
                'tank_name' => $reading->tank?->name ?? 'Tank',
                'product_id' => $reading->item_id,
                'product_name' => $reading->item?->name ?? 'Product',
                'reading_type' => $reading->reading_type,
                'status' => $reading->status,
                'dip_liters' => (float) $reading->dip_measurement_liters,
                'expected_liters' => (float) $reading->system_calculated_liters,
                'variance_liters' => $varianceLiters,
                'variance_type' => $reading->variance_type,
                'variance_reason' => $reading->variance_reason,
                'unit_cost' => $unitCost,
                'value' => $value,
                'journal_entry_id' => $reading->journal_entry_id,
                'notes' => $reading->notes,
            ];
        })->values()->all();
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function claimRows(string $companyId, string $startDate, string $endDate, string $tankId, string $productId, string $claimStatus): array
    {
        $query = StockReceiptLine::where('company_id', $companyId)
            ->with([
                'receipt:id,bill_id,receipt_date,variance_transaction_id',
                'receipt.bill:id,bill_number,vendor_id',
                'receipt.bill.vendor:id,name',
                'item:id,name,sku',
                'warehouse:id,name,code',
                'claimReceivedAccount:id,code,name',
                'claimReceivedTransaction:id,transaction_number',
            ])
            ->whereHas('receipt', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('receipt_date', [$startDate, $endDate]);
            })
            ->whereNotNull('variance_treatment')
            ->where('variance_quantity', '<', 0)
            ->orderByDesc('created_at');

        if ($tankId !== 'all') {
            $query->where('warehouse_id', $tankId);
        }

        if ($productId !== 'all') {
            $query->where('item_id', $productId);
        }

        if ($claimStatus !== 'all') {
            if ($claimStatus === 'final_loss') {
                $query->where('variance_treatment', 'final_loss');
            } else {
                $query->where('variance_treatment', 'supplier_claim')
                    ->where('claim_status', $claimStatus);
            }
        }

        return $query->get()->map(function (StockReceiptLine $line) {
            $receiptDate = $line->receipt?->receipt_date;
            $claimAmount = abs((float) $line->variance_cost);

            return [
                'id' => $line->id,
                'date' => $receiptDate?->toDateString(),
                'date_label' => $receiptDate?->format('d M Y'),
                'product_id' => $line->item_id,
                'product_name' => $line->item?->name ?? 'Product',
                'warehouse_id' => $line->warehouse_id,
                'warehouse_name' => $line->warehouse?->name ?? 'Warehouse',
                'vendor_name' => $line->receipt?->bill?->vendor?->name ?? 'Supplier',
                'bill_id' => $line->receipt?->bill_id,
                'bill_number' => $line->receipt?->bill?->bill_number,
                'expected_quantity' => (float) $line->expected_quantity,
                'received_quantity' => (float) $line->received_quantity,
                'variance_quantity' => (float) $line->variance_quantity,
                'unit_cost' => (float) $line->unit_cost,
                'claim_amount' => $claimAmount,
                'variance_reason' => $line->variance_reason,
                'variance_treatment' => $line->variance_treatment,
                'claim_status' => $line->claim_status,
                'claim_received_at' => $line->claim_received_at?->toDateString(),
                'claim_received_amount' => $line->claim_received_amount !== null ? (float) $line->claim_received_amount : null,
                'claim_received_account' => $line->claimReceivedAccount ? [
                    'id' => $line->claimReceivedAccount->id,
                    'code' => $line->claimReceivedAccount->code,
                    'name' => $line->claimReceivedAccount->name,
                ] : null,
                'claim_received_transaction_id' => $line->claim_received_transaction_id,
                'claim_received_transaction_number' => $line->claimReceivedTransaction?->transaction_number,
            ];
        })->values()->all();
    }

    /**
     * @param array<int,array<string,mixed>> $physicalRows
     * @param array<int,array<string,mixed>> $claimRows
     * @return array<string,float|int>
     */
    private function totals(array $physicalRows, array $claimRows): array
    {
        $physicalLossLiters = abs(array_sum(array_map(
            fn (array $row) => $row['variance_type'] === TankReading::VARIANCE_LOSS ? (float) $row['variance_liters'] : 0,
            $physicalRows
        )));
        $physicalGainLiters = array_sum(array_map(
            fn (array $row) => $row['variance_type'] === TankReading::VARIANCE_GAIN ? (float) $row['variance_liters'] : 0,
            $physicalRows
        ));
        $physicalLossValue = array_sum(array_map(
            fn (array $row) => $row['variance_type'] === TankReading::VARIANCE_LOSS ? (float) $row['value'] : 0,
            $physicalRows
        ));
        $physicalGainValue = array_sum(array_map(
            fn (array $row) => $row['variance_type'] === TankReading::VARIANCE_GAIN ? (float) $row['value'] : 0,
            $physicalRows
        ));

        $pendingClaims = array_filter(
            $claimRows,
            fn (array $row) => $row['variance_treatment'] === 'supplier_claim' && $row['claim_status'] === 'pending'
        );

        return [
            'physical_count' => count($physicalRows),
            'physical_loss_liters' => $physicalLossLiters,
            'physical_gain_liters' => $physicalGainLiters,
            'physical_loss_value' => $physicalLossValue,
            'physical_gain_value' => $physicalGainValue,
            'claim_count' => count($claimRows),
            'pending_claim_count' => count($pendingClaims),
            'pending_claim_amount' => array_sum(array_map(fn (array $row) => (float) $row['claim_amount'], $pendingClaims)),
            'final_loss_amount' => array_sum(array_map(
                fn (array $row) => $row['variance_treatment'] === 'final_loss' ? (float) $row['claim_amount'] : 0,
                $claimRows
            )),
            'received_claim_amount' => array_sum(array_map(
                fn (array $row) => $row['claim_status'] === 'received' ? (float) ($row['claim_received_amount'] ?? $row['claim_amount']) : 0,
                $claimRows
            )),
        ];
    }

    /**
     * @return array<int,array{id:string,name:string}>
     */
    private function locations(string $companyId): array
    {
        return Warehouse::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Warehouse $warehouse) => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int,array{id:string,name:string}>
     */
    private function products(string $companyId): array
    {
        return Item::where('company_id', $companyId)
            ->where('track_inventory', true)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Item $item) => [
                'id' => $item->id,
                'name' => $item->name,
            ])
            ->values()
            ->all();
    }
}
