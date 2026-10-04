<?php

namespace App\Modules\FuelStation\Services\Calculator\Metrics;

use App\Modules\FuelStation\Services\Calculator\CalculatorContext;
use App\Modules\FuelStation\Services\Calculator\MetricEvaluator;
use App\Modules\FuelStation\Services\Calculator\MetricResult;
use App\Modules\FuelStation\Services\CustomerPeriodSummaryService;

/** What a customer bought and paid over a range, and what they owed on a day: the customer page's Summary. */
class CustomerMetric extends MetricEvaluator
{
    /** @param string $field One of: bought, paid, closing (owed on a day). */
    public function __construct(string $key, string $label, private readonly string $field)
    {
        parent::__construct($key, $label, 'Customers', 'Rs', ['customer'], $field === 'closing');
    }

    public function evaluate(CalculatorContext $c, array $collection, string $from, string $to): MetricResult
    {
        $id = (string) ($collection['id'] ?? '');
        $summary = app(CustomerPeriodSummaryService::class)->run($c->companyId, $id, $from, $to, $c->slug);

        return new MetricResult(
            (float) $summary['money'][$this->field],
            'Rs',
            "/{$c->slug}/fuel/credit-customers/{$id}?from={$from}&to={$to}",
        );
    }
}
