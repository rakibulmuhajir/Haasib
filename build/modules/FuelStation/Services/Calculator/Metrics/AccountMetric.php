<?php

namespace App\Modules\FuelStation\Services\Calculator\Metrics;

use App\Modules\FuelStation\Services\Calculator\CalculatorContext;
use App\Modules\FuelStation\Services\Calculator\MetricEvaluator;
use App\Modules\FuelStation\Services\Calculator\MetricResult;

/** What one chosen expense or income account moved over the range: its statement's closing less opening. */
class AccountMetric extends MetricEvaluator
{
    public const EXPENSE_TYPES = ['expense', 'cogs', 'other_expense'];

    public const INCOME_TYPES = ['revenue', 'other_income'];

    public function __construct(string $key, string $label, private readonly bool $income)
    {
        parent::__construct($key, $label, 'Profit and costs', 'Rs', ['account']);
    }

    public function evaluate(CalculatorContext $c, array $collection, string $from, string $to): MetricResult
    {
        $account = $c->account((string) ($collection['id'] ?? ''));
        if (! $account || ! in_array($account->type, $this->income ? self::INCOME_TYPES : self::EXPENSE_TYPES, true)) {
            return new MetricResult(null, 'Rs', null, 'Account not found.');
        }

        return new MetricResult(
            $c->accountMovement($account, $from, $to),
            'Rs',
            $this->income ? "/{$c->slug}/accounts/{$account->id}" : $c->expenseStatementLink($account->id, $from, $to),
        );
    }
}
