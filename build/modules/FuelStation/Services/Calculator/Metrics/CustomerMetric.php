<?php

namespace App\Modules\FuelStation\Services\Calculator\Metrics;

use App\Modules\Accounting\Models\Customer;
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
        parent::__construct($key, $label, 'Customers', 'Rs', ['customer', 'customer_category'], $field === 'closing');
    }

    public function evaluate(CalculatorContext $c, array $collection, string $from, string $to): MetricResult
    {
        $id = (string) ($collection['id'] ?? '');
        if (($collection['type'] ?? '') === 'customer_category') {
            return $this->evaluateCategory($c, $id, $from, $to);
        }
        $summary = app(CustomerPeriodSummaryService::class)->run($c->companyId, $id, $from, $to, $c->slug);

        return new MetricResult(
            (float) $summary['money'][$this->field],
            'Rs',
            "/{$c->slug}/fuel/credit-customers/{$id}?from={$from}&to={$to}",
        );
    }

    /** Every customer in the category, each from their own Summary, added up. */
    private function evaluateCategory(CalculatorContext $c, string $categoryId, string $from, string $to): MetricResult
    {
        $href = "/{$c->slug}/fuel/credit-customers?category_id={$categoryId}";
        $ids = Customer::where('company_id', $c->companyId)->where('category_id', $categoryId)->pluck('id');
        $sum = 0.0;
        foreach ($ids as $id) {
            $summary = app(CustomerPeriodSummaryService::class)->run($c->companyId, (string) $id, $from, $to, $c->slug);
            $sum += (float) $summary['money'][$this->field];
        }

        return new MetricResult(round($sum, 2), 'Rs', $href, $ids->isEmpty() ? 'No customers in this category.' : null);
    }
}
