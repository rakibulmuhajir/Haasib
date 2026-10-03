<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\Accounting\Services\PostingService;
use App\Services\AccountingWriteTransaction;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Lower of cost or new purchase rate, at month end, for tank fuels.
 *
 * The fuel left in the tanks on a month's last day is valued at what it cost
 * (FuelCostService), unless the purchase rate in force on the 1st of the next month is lower:
 * then the stock is written down to that rate and the loss lands in the month that held it.
 * Never written up. This is month-end only -- per-rate-change revaluation was removed on
 * purpose (RateChangeService), rates change near-daily.
 *
 * One journal per (month, fuel): DR the fuel's cost-of-fuel account, CR its inventory account,
 * dated the month's last day, plus a quantity-0 'revaluation' stock movement of -amount so the
 * cost walk starts the next month at the new rate. sync() is idempotent: it works out what
 * should exist and, if the live journal differs, reverses it on its own date and posts the
 * right one.
 */
class MonthEndStockValuationService
{
    public const TYPE = 'fuel_stock_writedown'; // transaction_type is varchar(30)

    public function __construct(
        private readonly FuelCostService $costs,
        private readonly GlPostingService $posting,
    ) {}

    /**
     * @return array<int,array<string,mixed>> per fuel: item_id, item, quantity, cost, rate, writedown, existing, action
     */
    public function sync(string $companyId, string $month): array
    {
        $this->costs->forget();
        $lastDay = Carbon::parse($month.'-01')->endOfMonth()->toDateString();
        $nextFirst = Carbon::parse($lastDay)->addDay()->toDateString();

        $closed = DB::table('acct.transactions')
            ->where('company_id', $companyId)
            ->where('transaction_type', 'fuel_daily_close')
            ->whereIn('status', ['posted', 'locked'])
            ->whereNull('deleted_at')->whereNull('reversed_by_id')
            ->whereDate('transaction_date', $lastDay)
            ->exists();

        $existing = $this->liveWritedowns($companyId, $month)->keyBy(fn ($t) => $t->metadata['item_id'] ?? '');

        $itemIds = DB::table('inv.warehouses')
            ->where('company_id', $companyId)->where('warehouse_type', 'tank')
            ->whereNotNull('linked_item_id')->whereNull('deleted_at')
            ->distinct()->pluck('linked_item_id')
            ->merge($existing->keys())->filter()->unique()->values();

        $items = DB::table('inv.items')->where('company_id', $companyId)->whereIn('id', $itemIds)
            ->get(['id', 'name', 'expense_account_id', 'asset_account_id']);

        $results = [];
        foreach ($items as $item) {
            $results[] = $this->syncItem($companyId, $month, $lastDay, $nextFirst, $item, $closed, $existing->get($item->id));
        }

        $this->costs->forget();

        return $results;
    }

    /**
     * For a trigger inside someone else's transaction (a close, a rate change): the sync runs in
     * a savepoint, so a failure -- a closed period, a fuel with no accounts -- is logged and
     * rolled back on its own instead of blocking the close or the rate. Concurrency retries
     * (40001 / 40P01) still propagate so the owner of the outer transaction can retry it.
     */
    public function syncWithin(string $companyId, string $month): void
    {
        try {
            DB::transaction(fn () => $this->sync($companyId, $month));
        } catch (\Throwable $e) {
            $cause = $e;
            while ($cause->getPrevious() && ! ($cause instanceof QueryException)) {
                $cause = $cause->getPrevious();
            }
            $state = $cause instanceof QueryException ? ($cause->errorInfo[0] ?? (string) $cause->getCode()) : '';
            if (in_array($state, ['40001', '40P01'], true)) {
                throw $e;
            }
            $this->costs->forget();
            Log::error('fuel month-end stock valuation failed', ['company_id' => $companyId, 'month' => $month, 'error' => $e->getMessage()]);
        }
    }

    /** After a rate change effective on $date: the month before it (the 1st decides it) and its own month. */
    public function syncForRateDate(string $companyId, string $date): void
    {
        $day = Carbon::parse($date);
        $before = $day->copy()->subDay()->format('Y-m');
        $this->syncWithin($companyId, $before);
        if ($day->format('Y-m') !== $before) {
            $this->syncWithin($companyId, $day->format('Y-m'));
        }
    }

    /** After a close for $date posts (or re-posts) or is reopened: only a month's last day matters. */
    public function syncForCloseDate(string $companyId, string $date): void
    {
        $day = Carbon::parse($date);
        if ($day->toDateString() === $day->copy()->endOfMonth()->toDateString()) {
            $this->syncWithin($companyId, $day->format('Y-m'));
        }
    }

    /**
     * The write-downs in force, for a month or between two days.
     *
     * @return Collection<int,Transaction>
     */
    public function liveWritedowns(string $companyId, ?string $month = null, ?string $from = null, ?string $to = null): Collection
    {
        return Transaction::where('company_id', $companyId)
            ->where('transaction_type', self::TYPE)
            ->whereIn('status', ['posted', 'locked'])
            ->whereNull('deleted_at')->whereNull('reversed_by_id')->whereNull('reversal_of_id')
            ->when($month, fn ($q) => $q->whereRaw("metadata->>'month' = ?", [$month]))
            ->when($from, fn ($q) => $q->whereDate('transaction_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('transaction_date', '<=', $to))
            ->get()
            ->each(fn ($t) => $t->metadata = is_array($t->metadata) ? $t->metadata : []);
    }

    private function syncItem(string $companyId, string $month, string $lastDay, string $nextFirst, object $item, bool $closed, ?Transaction $existing): array
    {
        $quantity = $cost = 0.0;
        $rate = null;
        $desired = 0.0;

        if ($closed) {
            // The same dip the cost walk reads: what was in the tanks at the end of the day.
            $quantity = (float) DB::table('fuel.tank_readings')
                ->where('company_id', $companyId)->where('item_id', $item->id)
                ->whereDate('reading_date', $lastDay)->sum('dip_measurement_liters');
            $cost = $this->costs->costForDay($companyId, $item->id, $lastDay);
            $latest = DB::table('fuel.rate_changes')
                ->where('company_id', $companyId)->where('item_id', $item->id)
                ->whereDate('effective_date', '<=', $nextFirst)
                ->orderByDesc('effective_date')->first(['purchase_rate']);
            $rate = $latest ? (float) $latest->purchase_rate : null;
            if ($quantity > 0 && $cost > 0 && $rate !== null && $rate < $cost) {
                $desired = round($quantity * ($cost - $rate), 2);
            }
        }

        $have = $existing ? round((float) ($existing->metadata['amount'] ?? 0), 2) : 0.0;
        $row = ['item_id' => $item->id, 'item' => $item->name, 'quantity' => $quantity, 'cost' => $cost, 'rate' => $rate,
            'writedown' => $desired, 'existing' => $have, 'action' => 'none'];

        if ($existing && abs($have - $desired) < 0.005) {
            return $row;
        }
        if (! $existing && $desired <= 0) {
            return $row;
        }
        if ($desired > 0 && (! $item->expense_account_id || ! $item->asset_account_id)) {
            Log::warning('fuel month-end write-down skipped: item has no cost or inventory account', ['item_id' => $item->id, 'month' => $month]);
            $row['action'] = 'skipped (no cost or inventory account on the product)';

            return $row;
        }

        AccountingWriteTransaction::run(function () use ($companyId, $month, $lastDay, $item, $existing, $desired, $quantity, $cost, $rate, &$row) {
            if ($existing) {
                $this->undo($existing);
                $row['action'] = 'reversed';
            }
            if ($desired > 0) {
                $this->post($companyId, $month, $lastDay, $item, $desired, $quantity, $cost, (float) $rate);
                $row['action'] = $existing ? 'replaced' : 'posted';
            }
        });
        $this->costs->forget();

        return $row;
    }

    /** Reverses a write-down on its own date and takes its value change out of the cost walk. */
    private function undo(Transaction $existing): void
    {
        app(PostingService::class)->reverseTransaction($existing, 'Month-end stock valuation changed', $existing->transaction_date);
        DB::table('inv.stock_movements')
            ->where('company_id', $existing->company_id)
            ->where('movement_type', 'revaluation')
            ->where('gl_transaction_id', $existing->id)
            ->delete();
    }

    private function post(string $companyId, string $month, string $lastDay, object $item, float $amount, float $quantity, float $cost, float $rate): void
    {
        $currency = DB::table('auth.companies')->where('id', $companyId)->value('base_currency') ?: 'PKR';
        $litres = rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');
        $text = sprintf('%s stock at new rate: %s L x (%s - %s)', $item->name, $litres, number_format($cost, 2, '.', ''), number_format($rate, 2, '.', ''));

        $transaction = $this->posting->postBalancedTransaction([
            'company_id' => $companyId,
            'transaction_type' => self::TYPE,
            'date' => $lastDay,
            'currency' => $currency,
            'base_currency' => $currency,
            'description' => $text,
            'reference_type' => 'fuel.stock_writedown',
            'reference_id' => $item->id,
            'metadata' => [
                'month' => $month, 'item_id' => $item->id, 'quantity' => round($quantity, 3),
                'cost_rate' => round($cost, 4), 'new_rate' => round($rate, 2), 'amount' => $amount,
            ],
        ], [
            ['account_id' => $item->expense_account_id, 'type' => 'debit', 'amount' => $amount, 'description' => $text],
            ['account_id' => $item->asset_account_id, 'type' => 'credit', 'amount' => $amount, 'description' => $text],
        ]);

        $tankId = DB::table('fuel.tank_readings')
            ->where('company_id', $companyId)->where('item_id', $item->id)->whereDate('reading_date', $lastDay)
            ->orderByDesc('dip_measurement_liters')->value('tank_id')
            ?? DB::table('inv.warehouses')->where('company_id', $companyId)->where('warehouse_type', 'tank')
                ->where('linked_item_id', $item->id)->whereNull('deleted_at')->value('id');

        DB::table('inv.stock_movements')->insert([
            'id' => (string) Str::uuid(),
            'company_id' => $companyId,
            'warehouse_id' => $tankId,
            'item_id' => $item->id,
            'movement_date' => $lastDay,
            'movement_type' => 'revaluation',
            'quantity' => 0,
            'unit_cost' => null,
            'total_cost' => -$amount,
            'gl_transaction_id' => $transaction->id,
            'reference_type' => 'fuel.stock_writedown',
            'reference_id' => $transaction->id,
            'reason' => 'Month-end stock at new purchase rate',
            'created_at' => now(),
        ]);
    }
}
