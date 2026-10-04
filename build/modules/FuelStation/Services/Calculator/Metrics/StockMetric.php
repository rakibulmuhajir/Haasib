<?php

namespace App\Modules\FuelStation\Services\Calculator\Metrics;

use App\Modules\FuelStation\Services\Calculator\CalculatorContext;
use App\Modules\FuelStation\Services\Calculator\MetricEvaluator;
use App\Modules\FuelStation\Services\Calculator\MetricResult;

/**
 * Purchases (range) and stock on hand (on a day), read from the stock statement
 * (StockStatementService) -- the same totals its page shows for that product.
 */
class StockMetric extends MetricEvaluator
{
    /** @param string $field One of: purchase_amount, received, closing, closing_value. */
    public function __construct(string $key, string $label, string $unit, private readonly string $field, bool $takesDay = false)
    {
        parent::__construct($key, $label, 'Stock and purchases', $unit, ['product'], $takesDay);
    }

    public function unitFor(CalculatorContext $c, array $collection): string
    {
        return in_array($this->field, ['received', 'closing'], true)
            ? $c->quantityUnit($c->item((string) ($collection['id'] ?? '')))
            : $this->unit;
    }

    public function evaluate(CalculatorContext $c, array $collection, string $from, string $to): MetricResult
    {
        $id = (string) ($collection['id'] ?? '');
        $unit = $this->unitFor($c, $collection);
        if (! $c->item($id)) {
            return new MetricResult(null, $unit, null, 'Product not found.');
        }

        $href = "/{$c->slug}/fuel/reports/stock-statement?item={$id}&start_date={$from}&end_date={$to}";
        $value = $c->stock($id, $from, $to)['totals'][$this->field] ?? null;

        if ($value === null) {
            // Purchases are nothing when none were bought; a balance or its value with no figure is unknown.
            return in_array($this->field, ['purchase_amount', 'received'], true)
                ? new MetricResult(0.0, $unit, $href)
                : new MetricResult(null, $unit, $href, $this->field === 'closing' ? 'No stock count for that day.' : 'No stock value for that day.');
        }

        return new MetricResult((float) $value, $unit, $href);
    }
}
