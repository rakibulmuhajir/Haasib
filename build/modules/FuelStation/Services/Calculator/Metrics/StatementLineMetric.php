<?php

namespace App\Modules\FuelStation\Services\Calculator\Metrics;

use App\Modules\FuelStation\Services\Calculator\CalculatorContext;
use App\Modules\FuelStation\Services\Calculator\MetricEvaluator;
use App\Modules\FuelStation\Services\Calculator\MetricResult;

/** A line of the station's profit statement (ProfitStatementService): net profit, expenses, salaries, other income. */
class StatementLineMetric extends MetricEvaluator
{
    public function __construct(string $key, string $label, private readonly string $line)
    {
        parent::__construct($key, $label, 'Profit and costs', 'Rs', ['none']);
    }

    public function evaluate(CalculatorContext $c, array $collection, string $from, string $to): MetricResult
    {
        $statement = $c->profitStatement($from, $to);
        $line = collect($statement['lines'])->firstWhere('key', $this->line);

        return new MetricResult((float) ($line['amount'] ?? 0), 'Rs', $c->profitLink($from, $to));
    }
}
