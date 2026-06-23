<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Models\TankReading;
use App\Modules\Inventory\Models\Item;
use Carbon\Carbon;

class ProductProfitabilityReportService
{
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
    public function run(string $companyId, string $startDate, string $endDate, string $groupBy = 'day', string $product = 'all'): array
    {
        $groupBy = in_array($groupBy, ['day', 'week', 'month'], true) ? $groupBy : 'day';
        $items = $this->items($companyId);
        $transactions = Transaction::where('company_id', $companyId)
            ->where('transaction_type', 'fuel_daily_close')
            ->where('status', 'posted')
            ->whereNull('deleted_at')
            ->whereNull('reversed_by_id')
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date')
            ->get(['id', 'transaction_number', 'transaction_date', 'metadata']);

        $products = [];
        $periods = [];
        $rateChangeRows = [];

        foreach ($transactions as $transaction) {
            $metadata = is_array($transaction->metadata) ? $transaction->metadata : [];
            $date = $transaction->transaction_date instanceof Carbon
                ? $transaction->transaction_date->copy()
                : Carbon::parse($transaction->transaction_date);

            foreach ($this->fuelSalesRows($metadata['fuel_sales'] ?? [], $items) as $row) {
                if ($product !== 'all' && $row['key'] !== $product) {
                    continue;
                }

                $this->addProductSale($products, $row);
                $this->addPeriodSale($periods, $date, $groupBy, $row, $transaction->id, $transaction->transaction_number);
            }

            foreach ($this->otherSalesRows($metadata['other_sales_details'] ?? [], $items) as $row) {
                if ($product !== 'all' && $row['key'] !== $product) {
                    continue;
                }

                $this->addProductSale($products, $row);
                $this->addPeriodSale($periods, $date, $groupBy, $row, $transaction->id, $transaction->transaction_number);
            }

            foreach (($metadata['rate_change_segments'] ?? []) as $segment) {
                if (!is_array($segment)) {
                    continue;
                }

                $key = $this->itemKey($segment['item_id'] ?? null, $items, $segment['item_name'] ?? null);
                if ($product !== 'all' && $key !== $product) {
                    continue;
                }

                $oldLiters = (float) ($segment['old_rate_liters'] ?? 0);
                $newLiters = (float) ($segment['new_rate_liters'] ?? 0);
                $fallbackLiters = (float) ($segment['fallback_liters'] ?? 0);
                $oldRate = (float) ($segment['old_rate'] ?? 0);
                $newRate = (float) ($segment['new_rate'] ?? 0);

                $rateChangeRows[] = [
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
            }
        }

        $this->addStockVariance($companyId, $startDate, $endDate, $product, $items, $products);

        $productRows = array_values($products);
        foreach ($productRows as &$row) {
            $this->finishProductRow($row);
        }
        unset($row);
        usort($productRows, fn (array $a, array $b) => $b['revenue'] <=> $a['revenue']);

        $periodRows = array_values($periods);
        foreach ($periodRows as &$row) {
            $this->finishPeriodRow($row);
        }
        unset($row);

        return [
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'group_by' => $groupBy,
                'product' => $product,
            ],
            'totals' => $this->totals($productRows),
            'productRows' => $productRows,
            'periodRows' => $periodRows,
            'rateChangeRows' => $rateChangeRows,
            'productOptions' => $this->productOptions($items),
        ];
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function items(string $companyId): array
    {
        return Item::where('company_id', $companyId)
            ->where('is_sellable', true)
            ->whereNull('deleted_at')
            ->get(['id', 'sku', 'name', 'fuel_category', 'avg_cost', 'cost_price', 'unit_of_measure'])
            ->mapWithKeys(function (Item $item) {
                $key = $this->itemKey($item->id, [], $item->name, $item->fuel_category);

                return [$key => [
                    'id' => $item->id,
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'fuel_category' => $item->fuel_category,
                    'avg_cost' => (float) ($item->avg_cost ?: $item->cost_price ?: 0),
                    'unit' => $item->unit_of_measure ?: 'L',
                ]];
            })
            ->all();
    }

    /**
     * @param mixed $fuelSales
     * @param array<string,array<string,mixed>> $items
     * @return array<int,array<string,mixed>>
     */
    private function fuelSalesRows(mixed $fuelSales, array $items): array
    {
        if (!is_array($fuelSales)) {
            return [];
        }

        $rows = [];
        foreach ($fuelSales as $key => $sale) {
            if (!is_array($sale)) {
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
     * @param mixed $otherSales
     * @param array<string,array<string,mixed>> $items
     * @return array<int,array<string,mixed>>
     */
    private function otherSalesRows(mixed $otherSales, array $items): array
    {
        if (!is_array($otherSales)) {
            return [];
        }

        $rows = [];
        foreach ($otherSales as $sale) {
            if (!is_array($sale)) {
                continue;
            }

            $key = $this->itemKey($sale['item_id'] ?? null, $items, $sale['item_name'] ?? null);
            $quantity = (float) ($sale['quantity'] ?? 0);
            $revenue = (float) ($sale['amount'] ?? 0);
            $unitCost = (float) ($items[$key]['avg_cost'] ?? 0);

            $rows[] = [
                'key' => $key,
                'name' => $this->productName($key, $items, $sale['item_name'] ?? null),
                'unit' => (string) ($items[$key]['unit'] ?? 'unit'),
                'quantity' => $quantity,
                'revenue' => $revenue,
                'cogs' => round($quantity * $unitCost, 2),
                'estimated_cogs' => true,
                'source' => 'other_sale',
            ];
        }

        return $rows;
    }

    /**
     * @param array<string,array<string,mixed>> $products
     * @param array<string,mixed> $row
     */
    private function addProductSale(array &$products, array $row): void
    {
        if (!isset($products[$row['key']])) {
            $products[$row['key']] = $this->emptyProductRow($row['key'], $row['name'], $row['unit']);
        }

        $products[$row['key']]['quantity'] += $row['quantity'];
        $products[$row['key']]['revenue'] += $row['revenue'];
        $products[$row['key']]['cogs'] += $row['cogs'];
        $products[$row['key']]['estimated_cogs'] = $products[$row['key']]['estimated_cogs'] || $row['estimated_cogs'];
    }

    /**
     * @param array<string,array<string,mixed>> $periods
     * @param array<string,mixed> $row
     */
    private function addPeriodSale(array &$periods, Carbon $date, string $groupBy, array $row, string $transactionId, string $transactionNumber): void
    {
        $periodKey = $this->periodKey($date, $groupBy);
        if (!isset($periods[$periodKey])) {
            $periods[$periodKey] = [
                'key' => $periodKey,
                'label' => $this->periodLabel($date, $groupBy),
                'quantity' => 0.0,
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

        $periods[$periodKey]['quantity'] += $row['quantity'];
        $periods[$periodKey]['revenue'] += $row['revenue'];
        $periods[$periodKey]['cogs'] += $row['cogs'];
        $periods[$periodKey]['daily_close_ids'][$transactionId] = $transactionId;
        $periods[$periodKey]['daily_close_numbers'][$transactionNumber] = $transactionNumber;
    }

    /**
     * @param array<string,array<string,mixed>> $products
     * @param array<string,array<string,mixed>> $items
     */
    private function addStockVariance(string $companyId, string $startDate, string $endDate, string $product, array $items, array &$products): void
    {
        $readings = TankReading::where('company_id', $companyId)
            ->whereBetween('reading_date', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ])
            ->where('variance_type', '!=', TankReading::VARIANCE_NONE)
            ->with('item:id,name,fuel_category,avg_cost,cost_price,unit_of_measure')
            ->get();

        foreach ($readings as $reading) {
            $key = $this->itemKey($reading->item_id, $items, $reading->item?->name, $reading->item?->fuel_category);
            if ($product !== 'all' && $key !== $product) {
                continue;
            }

            if (!isset($products[$key])) {
                $products[$key] = $this->emptyProductRow($key, $this->productName($key, $items, $reading->item?->name), 'L');
            }

            $variance = (float) $reading->variance_liters;
            $unitCost = (float) ($items[$key]['avg_cost'] ?? $reading->item?->avg_cost ?? $reading->item?->cost_price ?? 0);
            $value = round(abs($variance) * $unitCost, 2);

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
            'revenue' => 0.0,
            'cogs' => 0.0,
            'gross_profit' => 0.0,
            'gross_margin_percent' => 0.0,
            'avg_rate' => 0.0,
            'avg_cost' => 0.0,
            'margin_per_unit' => 0.0,
            'estimated_cogs' => false,
            'stock_loss_quantity' => 0.0,
            'stock_loss_value' => 0.0,
            'stock_gain_quantity' => 0.0,
            'stock_gain_value' => 0.0,
        ];
    }

    /**
     * @param array<string,mixed> $row
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
     * @param array<string,mixed> $row
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
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,float|int>
     */
    private function totals(array $rows): array
    {
        $totals = [
            'product_count' => count($rows),
            'quantity' => array_sum(array_column($rows, 'quantity')),
            'revenue' => array_sum(array_column($rows, 'revenue')),
            'cogs' => array_sum(array_column($rows, 'cogs')),
            'gross_profit' => array_sum(array_column($rows, 'gross_profit')),
            'stock_loss_quantity' => array_sum(array_column($rows, 'stock_loss_quantity')),
            'stock_loss_value' => array_sum(array_column($rows, 'stock_loss_value')),
            'stock_gain_quantity' => array_sum(array_column($rows, 'stock_gain_quantity')),
            'stock_gain_value' => array_sum(array_column($rows, 'stock_gain_value')),
        ];

        $totals['gross_margin_percent'] = $totals['revenue'] > 0 ? ($totals['gross_profit'] / $totals['revenue']) * 100 : 0;
        $totals['margin_per_unit'] = $totals['quantity'] > 0 ? ($totals['gross_profit'] / $totals['quantity']) : 0;

        return $totals;
    }

    /**
     * @param array<string,array<string,mixed>> $items
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
     * @param array<string,array<string,mixed>> $items
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
     * @param array<string,array<string,mixed>> $items
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
            'week' => 'Week of ' . $date->copy()->startOfWeek()->format('d M Y'),
            'month' => $date->format('F Y'),
            default => $date->format('d M Y'),
        };
    }
}
