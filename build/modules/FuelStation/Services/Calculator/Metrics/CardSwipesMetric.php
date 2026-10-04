<?php

namespace App\Modules\FuelStation\Services\Calculator\Metrics;

use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Services\Calculator\CalculatorContext;
use App\Modules\FuelStation\Services\Calculator\MetricEvaluator;
use App\Modules\FuelStation\Services\Calculator\MetricResult;
use Carbon\Carbon;

/**
 * Card swipes: what the posted daily closes recorded as received on a payment channel
 * (metadata payment_receipts), or -- for all -- the card total each close stores as card_swipes.
 */
class CardSwipesMetric extends MetricEvaluator
{
    public function __construct()
    {
        parent::__construct('card_swipes', 'Card swipes', 'Cards', 'Rs', ['none', 'channel']);
    }

    public function evaluate(CalculatorContext $c, array $collection, string $from, string $to): MetricResult
    {
        $channel = ($collection['type'] ?? '') === 'channel' ? (string) ($collection['id'] ?? '') : null;

        $closes = Transaction::where('company_id', $c->companyId)
            ->where('transaction_type', 'fuel_daily_close')
            ->where('status', 'posted')
            ->whereNull('deleted_at')
            ->whereNull('reversed_by_id')
            ->whereBetween('transaction_date', [$from, $to])
            ->get(['id', 'metadata']);

        $total = 0.0;
        foreach ($closes as $close) {
            $metadata = is_array($close->metadata) ? $close->metadata : [];
            $total += $channel === null
                ? (float) ($metadata['card_swipes'] ?? 0)
                : (float) (($metadata['payment_receipts'] ?? [])[$channel] ?? 0);
        }

        $month = Carbon::parse($from)->format('Y-m');

        return new MetricResult(round($total, 2), 'Rs', "/{$c->slug}/fuel/daily-close/month?month={$month}");
    }
}
