<?php

namespace App\Modules\FuelStation\Services\Calculator\Metrics;

use App\Modules\FuelStation\Services\Calculator\CalculatorContext;
use App\Modules\FuelStation\Services\Calculator\MetricEvaluator;
use App\Modules\FuelStation\Services\Calculator\MetricResult;
use App\Modules\FuelStation\Services\DailyCloseService;

/**
 * Tank gain / loss over all fuels: the accounts a close posts tank variance to
 * (DailyCloseService::tankVarianceAccountIds), gains less losses -- so a loss is negative.
 */
class TankGainLossMetric extends MetricEvaluator
{
    public function __construct()
    {
        parent::__construct('tank_gain_loss', 'Tank gain (loss is minus)', 'Stock and purchases', 'Rs', ['none']);
    }

    public function evaluate(CalculatorContext $c, array $collection, string $from, string $to): MetricResult
    {
        $net = 0.0;
        foreach (app(DailyCloseService::class)->tankVarianceAccountIds($c->companyId) as $id) {
            $account = $c->account($id);
            if (! $account) {
                continue;
            }
            $moved = $c->accountMovement($account, $from, $to);
            $net += in_array($account->type, AccountMetric::EXPENSE_TYPES, true) ? -$moved : $moved;
        }

        return new MetricResult(round($net, 2), 'Rs', "/{$c->slug}/fuel/reports/stock-variance?start_date={$from}&end_date={$to}");
    }
}
