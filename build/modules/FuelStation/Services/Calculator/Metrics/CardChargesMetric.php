<?php

namespace App\Modules\FuelStation\Services\Calculator\Metrics;

use App\Modules\FuelStation\Services\Calculator\CalculatorContext;
use App\Modules\FuelStation\Services\Calculator\MetricEvaluator;
use App\Modules\FuelStation\Services\Calculator\MetricResult;
use App\Modules\FuelStation\Services\DailyCloseService;

/** Card charges: what the POS / bank charges account (the one a close posts card fees to) took in the range. */
class CardChargesMetric extends MetricEvaluator
{
    public function __construct()
    {
        parent::__construct('card_charges', 'Card charges', 'Cards', 'Rs', ['none']);
    }

    public function evaluate(CalculatorContext $c, array $collection, string $from, string $to): MetricResult
    {
        try {
            $account = $c->account(app(DailyCloseService::class)->cardChargesAccountId($c->companyId));
        } catch (\RuntimeException) {
            $account = null;
        }
        if (! $account) {
            return new MetricResult(null, 'Rs', null, 'No POS / bank charges account.');
        }

        return new MetricResult($c->accountMovement($account, $from, $to), 'Rs', $c->expenseStatementLink($account->id, $from, $to));
    }
}
