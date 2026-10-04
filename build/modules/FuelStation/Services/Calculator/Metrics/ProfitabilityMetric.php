<?php

namespace App\Modules\FuelStation\Services\Calculator\Metrics;

use App\Modules\FuelStation\Services\Calculator\CalculatorContext;
use App\Modules\FuelStation\Services\Calculator\MetricEvaluator;
use App\Modules\FuelStation\Services\Calculator\MetricResult;

/**
 * Sales, litres sold, cost of sales, gross profit and book profit: a column of the Fuel Profit
 * report (ProductProfitabilityReportService), for one product or summed over the fuels / all products.
 */
class ProfitabilityMetric extends MetricEvaluator
{
    /** @param string $field The report column: revenue, quantity, cogs, gross_profit or book_profit. */
    public function __construct(string $key, string $label, string $unit, private readonly string $field)
    {
        parent::__construct($key, $label, 'Sales and profit', $unit, ['product', 'all_fuels', 'all_products']);
    }

    public function unitFor(CalculatorContext $c, array $collection): string
    {
        if ($this->field !== 'quantity') {
            return $this->unit;
        }

        return match ($collection['type']) {
            'all_fuels' => 'L',
            'all_products' => 'qty',
            default => $c->quantityUnit($c->item((string) ($collection['id'] ?? ''))),
        };
    }

    public function evaluate(CalculatorContext $c, array $collection, string $from, string $to): MetricResult
    {
        $report = $c->profitability($from, $to);
        $unit = $this->unitFor($c, $collection);
        $rows = collect($report['productRows']);
        $type = $collection['type'];
        $href = "/{$c->slug}/fuel/reports/product-profitability?start_date={$from}&end_date={$to}";

        if ($type === 'product' || $type === 'fuel') {
            $key = $c->productKey((string) ($collection['id'] ?? ''));
            if ($key === null) {
                return new MetricResult(null, $unit, null, 'Product not found.');
            }
            $row = $rows->firstWhere('key', $key);
            $href .= '&product='.urlencode($key);
            if (! $row) {
                return $this->field === 'book_profit'
                    ? new MetricResult(null, $unit, $href, 'No sales in this period.')
                    : new MetricResult(0.0, $unit, $href);
            }
            $value = $row[$this->field] ?? null;

            return $value === null
                ? new MetricResult(null, $unit, $href, 'No book profit for this product (it has no tank of its own).')
                : new MetricResult((float) $value, $unit, $href);
        }

        if ($type === 'all_fuels') {
            $fuels = $c->fuelKeys();
            $rows = $rows->filter(fn (array $r) => isset($fuels[$r['key']]));
        }
        if ($this->field === 'book_profit') {
            $book = $rows->pluck('book_profit')->filter(fn ($v) => $v !== null);

            return $book->isEmpty()
                ? new MetricResult(null, $unit, $href, 'No book profit in this period.')
                : new MetricResult(round((float) $book->sum(), 2), $unit, $href);
        }

        return new MetricResult((float) $rows->sum($this->field), $unit, $href);
    }
}
