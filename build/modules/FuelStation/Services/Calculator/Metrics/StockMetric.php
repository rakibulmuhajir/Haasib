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
        parent::__construct($key, $label, 'Stock and purchases', $unit, ['product', 'category'], $takesDay);
    }

    public function unitFor(CalculatorContext $c, array $collection): string
    {
        if (! in_array($this->field, ['received', 'closing'], true)) {
            return $this->unit;
        }

        return ($collection['type'] ?? '') === 'category'
            ? $c->quantityUnitOf($c->categoryItems((string) ($collection['id'] ?? '')))
            : $c->quantityUnit($c->item((string) ($collection['id'] ?? '')));
    }

    public function evaluate(CalculatorContext $c, array $collection, string $from, string $to): MetricResult
    {
        $id = (string) ($collection['id'] ?? '');
        $unit = $this->unitFor($c, $collection);
        if (($collection['type'] ?? '') === 'category') {
            return $this->evaluateCategory($c, $id, $unit, $from, $to);
        }
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

    /** The same figure for each product in the category, added up. */
    private function evaluateCategory(CalculatorContext $c, string $categoryId, string $unit, string $from, string $to): MetricResult
    {
        $ids = array_map(fn ($i) => $i->id, $c->categoryItems($categoryId));
        $href = "/{$c->slug}/fuel/reports/stock-statement?category={$categoryId}&start_date={$from}&end_date={$to}";
        if ($ids === []) {
            return new MetricResult(null, $unit, $href, 'No products in this category.');
        }

        $sum = 0.0;
        $known = 0;
        foreach ($ids as $id) {
            $value = $c->stock($id, $from, $to)['totals'][$this->field] ?? null;
            if ($value !== null) {
                $sum += (float) $value;
                $known++;
            }
        }

        if ($known === 0) {
            return in_array($this->field, ['purchase_amount', 'received'], true)
                ? new MetricResult(0.0, $unit, $href)
                : new MetricResult(null, $unit, $href, $this->field === 'closing' ? 'No stock count for that day.' : 'No stock value for that day.');
        }

        return new MetricResult($sum, $unit, $href, $known < count($ids) ? 'Some products have no figure for that day.' : null);
    }
}
