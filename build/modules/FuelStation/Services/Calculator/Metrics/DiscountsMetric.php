<?php

namespace App\Modules\FuelStation\Services\Calculator\Metrics;

use App\Modules\FuelStation\Services\Calculator\CalculatorContext;
use App\Modules\FuelStation\Services\Calculator\MetricEvaluator;
use App\Modules\FuelStation\Services\Calculator\MetricResult;

/** Discounts given: the "Discounts given" row under Sales in the profit statement, as a positive amount. */
class DiscountsMetric extends MetricEvaluator
{
    public function __construct()
    {
        parent::__construct('discounts', 'Discounts given', 'Sales and profit', 'Rs', ['none']);
    }

    public function evaluate(CalculatorContext $c, array $collection, string $from, string $to): MetricResult
    {
        $sales = collect($c->profitStatement($from, $to)['lines'])->firstWhere('key', 'sales');
        $row = collect($sales['details'] ?? [])->firstWhere('name', 'Discounts given');

        // The statement shows it as a negative row under Sales; as a figure to use it is a positive amount.
        return new MetricResult($row ? round(-(float) $row['amount'], 2) : 0.0, 'Rs', $c->profitLink($from, $to));
    }
}
