<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\Accounting\Services\OpeningBalanceAccounts;
use App\Services\AccountingWriteTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the books' value of opening stock equal to the opening stock recorded in the tanks and
 * store: one journal, Dr each product's stock account / Cr Opening Balance Equity (3080).
 *
 * Opening stock was recorded as stock movements only (onboarding, product setup), so the litres
 * were in the tanks but never in the books: fuel stock on the Balance Sheet started at zero and
 * went negative as the first days' sales were costed out of it. Every place that records opening
 * stock calls sync() afterwards; it re-posts only when the figures changed.
 */
class OpeningStockLedgerService
{
    public const REFERENCE = 'inv.opening_stock';

    /**
     * sync() for the screens that record opening stock: the stock is saved either way, and a
     * books problem (no open period for the opening date, say) is logged rather than undoing it.
     * Running it again later -- any save of opening stock -- posts what is missing.
     */
    public function syncQuietly(string $companyId, ?string $userId = null): void
    {
        try {
            $this->sync($companyId, $userId);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function sync(string $companyId, ?string $userId = null): ?Transaction
    {
        $moves = DB::table('inv.stock_movements as m')
            ->join('inv.items as i', 'i.id', '=', 'm.item_id')
            ->where('m.company_id', $companyId)
            ->where('m.movement_type', 'opening')
            ->where('m.total_cost', '>', 0)
            ->get(['m.id', 'm.movement_date', 'm.quantity', 'm.total_cost', 'i.name', 'i.asset_account_id']);

        $byAccount = [];
        foreach ($moves as $move) {
            if (! $move->asset_account_id) {
                continue; // no stock account to carry it; nothing the books can hold
            }
            $byAccount[$move->asset_account_id] ??= ['amount' => 0.0, 'names' => []];
            $byAccount[$move->asset_account_id]['amount'] += (float) $move->total_cost;
            $byAccount[$move->asset_account_id]['names'][$move->name] = true;
        }
        $wanted = collect($byAccount)->map(fn ($a) => round($a['amount'], 2))->filter(fn ($a) => $a > 0)->sortKeys()->all();
        $date = $moves->min('movement_date');

        $existing = Transaction::where('company_id', $companyId)
            ->where('reference_type', self::REFERENCE)
            ->whereNull('deleted_at')
            ->get();
        $current = JournalEntry::whereIn('transaction_id', $existing->pluck('id'))
            ->where('debit_amount', '>', 0)
            ->get()
            ->groupBy('account_id')
            ->map(fn ($lines) => round((float) $lines->sum('debit_amount'), 2))
            ->sortKeys()
            ->all();
        $sameDate = $existing->count() === 1 && $date && $existing->first()->transaction_date->toDateString() === substr((string) $date, 0, 10);
        if ($current == $wanted && ($sameDate || empty($wanted))) {
            return $existing->first();
        }

        return AccountingWriteTransaction::run(function () use ($companyId, $existing, $wanted, $byAccount, $date, $moves, $userId) {
            foreach ($existing as $old) {
                JournalEntry::where('transaction_id', $old->id)->delete();
                $old->delete();
            }
            if (empty($wanted)) {
                return null;
            }

            $lines = [];
            foreach ($wanted as $accountId => $amount) {
                $lines[] = ['account_id' => $accountId, 'type' => 'debit', 'amount' => $amount,
                    'description' => 'Opening stock - '.implode(', ', array_keys($byAccount[$accountId]['names']))];
            }
            $lines[] = ['account_id' => app(OpeningBalanceAccounts::class)->resolve($companyId)['equity'], 'type' => 'credit',
                'amount' => round(array_sum($wanted), 2), 'description' => 'Opening stock'];

            $day = substr((string) $date, 0, 10);
            $transaction = app(GlPostingService::class)->postBalancedTransaction([
                'company_id' => $companyId,
                'transaction_type' => 'opening_stock',
                'date' => $day,
                'currency' => DB::table('auth.companies')->where('id', $companyId)->value('base_currency') ?: 'PKR',
                'description' => "Opening stock as of {$day}",
                'reference_type' => self::REFERENCE,
                'reference_id' => null,
                'metadata' => ['stock_movement_ids' => $moves->pluck('id')->all()],
            ], $lines);
            DB::table('inv.stock_movements')->whereIn('id', $moves->pluck('id'))->update(['gl_transaction_id' => $transaction->id]);

            return $transaction;
        });
    }
}
