<?php

namespace App\Modules\FuelStation\Services\Calculator\Metrics;

use App\Modules\FuelStation\Models\RateChange;
use App\Modules\FuelStation\Services\Calculator\CalculatorContext;
use App\Modules\FuelStation\Services\Calculator\MetricEvaluator;
use App\Modules\FuelStation\Services\Calculator\MetricResult;
use App\Modules\Inventory\Services\ItemPriceService;

/** The sale or purchase rate in force on a day: a fuel's latest rate change, an ordinary product's price history. */
class RateMetric extends MetricEvaluator
{
    public function __construct(string $key, string $label, private readonly bool $sale)
    {
        parent::__construct($key, $label, 'Rates', 'Rs/L', ['product'], true);
    }

    public function unitFor(CalculatorContext $c, array $collection): string
    {
        return 'Rs/'.$c->quantityUnit($c->item((string) ($collection['id'] ?? '')));
    }

    public function evaluate(CalculatorContext $c, array $collection, string $from, string $to): MetricResult
    {
        $id = (string) ($collection['id'] ?? '');
        $item = $c->item($id);
        $unit = $this->unitFor($c, $collection);
        if (! $item) {
            return new MetricResult(null, $unit, null, 'Product not found.');
        }

        if ($item->fuel_category) {
            $rate = RateChange::getRateForDate($c->companyId, $id, $to);
            $value = $rate ? (float) ($this->sale ? $rate->sale_rate : $rate->purchase_rate) : null;
            $href = "/{$c->slug}/fuel/rates";
        } else {
            $price = app(ItemPriceService::class)->inForce($c->companyId, $id, $to);
            $value = $price ? (float) ($this->sale ? $price->sale_price : $price->purchase_price) : null;
            $href = "/{$c->slug}/items/{$id}";
        }

        return $value === null
            ? new MetricResult(null, $unit, $href, 'No rate set on that day.')
            : new MetricResult($value, $unit, $href);
    }
}
