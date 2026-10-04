<?php

namespace App\Modules\FuelStation\Services\Calculator\Metrics;

use App\Modules\Accounting\Models\Account;
use App\Modules\FuelStation\Services\Calculator\CalculatorContext;
use App\Modules\FuelStation\Services\Calculator\MetricEvaluator;
use App\Modules\FuelStation\Services\Calculator\MetricResult;

/** Cash short / over: the station's cash over/short account over the range (a shortage is a cost, so plus). */
class CashShortOverMetric extends MetricEvaluator
{
    public function __construct()
    {
        parent::__construct('cash_short_over', 'Cash short (over is minus)', 'Profit and costs', 'Rs', ['none']);
    }

    public function evaluate(CalculatorContext $c, array $collection, string $from, string $to): MetricResult
    {
        // Resolved as the close resolves it: the station setting, else account 6180.
        $id = $c->settings()?->cash_over_short_account_id
            ?? Account::where('company_id', $c->companyId)->whereNull('deleted_at')->where('is_active', true)->where('code', '6180')->value('id');
        $account = $id ? $c->account((string) $id) : null;
        if (! $account) {
            return new MetricResult(null, 'Rs', null, 'No cash over/short account.');
        }

        return new MetricResult($c->accountMovement($account, $from, $to), 'Rs', $c->expenseStatementLink($account->id, $from, $to));
    }
}
