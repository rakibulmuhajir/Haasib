<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\OpeningStockService;
use App\Services\AccountingWriteTransaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cost of the other sales (lubricant packs, shop items) that a month's closes posted without one.
 *
 * Closes posted before the cost went into the close itself booked the revenue and nothing else:
 * no cost of sales, and the packs never left stock. A posted close is immutable, so for such a
 * month ONE correction transaction (type fuel_lubricant_cost, dated the month's last day) books
 * DR the item's cost-of-sales account / CR its stock account at the purchase price the caller
 * names, plus one 'sale' stock movement per item for the month's quantity. Running it again
 * reverses the live correction on its own date, removes its movements and posts the new one, so
 * it is safe to repeat. Closes posted (or re-posted by Edit day) after the change carry their
 * own cost and are not counted, so re-running after an edit shrinks or removes the correction.
 */
class LubricantCostService
{
    public const TYPE = 'fuel_lubricant_cost'; // transaction_type is varchar(30)

    public function __construct(private readonly GlPostingService $posting) {}

    /**
     * What the month's closes sold without a recorded cost, per item.
     *
     * @return array<string,array{item_id:string,name:string,quantity:float,amount:float,tank:bool,expense_account_id:?string,asset_account_id:?string}>
     */
    public function uncostedSales(string $companyId, string $month): array
    {
        $start = Carbon::parse($month.'-01')->toDateString();
        $end = Carbon::parse($month.'-01')->endOfMonth()->toDateString();

        $closes = Transaction::where('company_id', $companyId)
            ->where('transaction_type', 'fuel_daily_close')
            ->whereIn('status', ['posted', 'locked'])
            ->whereNull('deleted_at')->whereNull('reversed_by_id')
            ->whereBetween('transaction_date', [$start, $end])
            ->get(['id', 'metadata']);

        $sold = [];
        foreach ($closes as $close) {
            $metadata = is_array($close->metadata) ? $close->metadata : [];
            foreach ($metadata['other_sales_details'] ?? [] as $line) {
                $itemId = (string) ($line['item_id'] ?? '');
                $quantity = (float) ($line['quantity'] ?? 0);
                if ($itemId === '' || $quantity <= 0 || (float) ($line['cost'] ?? 0) > 0) {
                    continue;
                }
                $sold[$itemId]['quantity'] = ($sold[$itemId]['quantity'] ?? 0) + $quantity;
                $sold[$itemId]['amount'] = ($sold[$itemId]['amount'] ?? 0) + (float) ($line['amount'] ?? 0);
                $sold[$itemId]['fallback_name'] ??= (string) ($line['item_name'] ?? '');
            }
        }
        if (! $sold) {
            return [];
        }

        $tankItemIds = DB::table('inv.warehouses')->where('company_id', $companyId)->whereNotNull('linked_item_id')
            ->whereNull('deleted_at')->pluck('linked_item_id')->all();
        $items = DB::table('inv.items')->where('company_id', $companyId)->whereIn('id', array_keys($sold))
            ->get(['id', 'name', 'expense_account_id', 'asset_account_id'])->keyBy('id');

        $rows = [];
        foreach ($sold as $itemId => $row) {
            $item = $items->get($itemId);
            $rows[$itemId] = [
                'item_id' => $itemId,
                'name' => $item->name ?? ($row['fallback_name'] ?: $itemId),
                'quantity' => round($row['quantity'], 3),
                'amount' => round($row['amount'], 2),
                'tank' => in_array($itemId, $tankItemIds, true),
                'expense_account_id' => $item->expense_account_id ?? null,
                'asset_account_id' => $item->asset_account_id ?? null,
            ];
        }

        return $rows;
    }

    /**
     * Works out (and, when $apply, posts) the month's correction.
     *
     * @param array<string,float|int|string> $prices unit purchase price keyed by item id, sku or name
     * @return array{month:string,lines:array<int,array<string,mixed>>,missing:array<int,string>,posted:?string,reversed:?string}
     */
    public function correct(string $companyId, string $month, array $prices, bool $apply, bool $lenient = false): array
    {
        $uncosted = $this->uncostedSales($companyId, $month);
        $items = DB::table('inv.items')->where('company_id', $companyId)->whereNull('deleted_at')->get(['id', 'sku', 'name']);

        $priceFor = function (string $itemId) use ($prices, $items): ?float {
            $item = $items->firstWhere('id', $itemId);
            $candidates = array_filter([$itemId, $item->sku ?? null, $item->name ?? null]);
            foreach ($prices as $key => $price) {
                foreach ($candidates as $candidate) {
                    if (mb_strtolower(trim((string) $key)) === mb_strtolower(trim((string) $candidate))) {
                        return (float) $price;
                    }
                }
            }

            return null;
        };

        $lines = [];
        $missing = [];
        foreach ($uncosted as $itemId => $row) {
            $price = $priceFor($itemId);
            // An open item sold from a tank is costed by the fuel walk; only a named price costs it here.
            if ($row['tank'] && $price === null) {
                continue;
            }
            if ($price === null || $price <= 0) {
                $missing[] = $row['name'];
                continue;
            }
            if (! $row['expense_account_id'] || ! $row['asset_account_id']) {
                $missing[] = $row['name'].' (no cost of sales or stock account on the product)';
                continue;
            }
            $lines[] = $row + [
                'unit_cost' => round($price, 4),
                'cost' => round($row['quantity'] * $price, 2),
                'profit' => round($row['amount'] - round($row['quantity'] * $price, 2), 2),
            ];
        }

        $result = ['month' => $month, 'lines' => $lines, 'missing' => $missing, 'posted' => null, 'reversed' => null];
        if ($missing && $lenient) {
            // Automatic re-sync: keep what can be priced, never block the close.
            Log::warning('lubricant cost correction: items sold without a stored price are left uncosted', ['month' => $month, 'items' => $missing]);
            $result['missing'] = [];
        } elseif ($missing) {
            return $result;
        }
        if (! $apply) {
            return $result;
        }
        $live = $this->liveCorrections($companyId, $month);
        $signature = fn (array $rows) => collect($rows)->map(fn ($l) => [$l['item_id'], round((float) $l['quantity'], 3), round((float) $l['unit_cost'], 4)])->sortBy(0)->values()->all();
        if ($live->count() === 1 && $signature($live->first()->metadata['lines'] ?? []) === $signature($lines)) {
            return $result; // already what it should be
        }

        AccountingWriteTransaction::run(function () use ($companyId, $month, $lines, &$result) {
            foreach ($this->liveCorrections($companyId, $month) as $existing) {
                $this->undo($existing);
                $result['reversed'] = $existing->transaction_number;
            }
            if ($lines) {
                $result['posted'] = $this->post($companyId, $month, $lines)->transaction_number;
            }
        });

        return $result;
    }

    /**
     * After a close for $date posts, re-posts or is reopened: a month that already has a lubricant
     * correction is re-synced, pricing each item at the unit cost the live correction stored. A
     * close that now carries its own cost drops out of the correction; with nothing left
     * uncosted the correction is simply reversed. Runs in a savepoint: a failure is logged and
     * rolled back on its own, concurrency retries (40001 / 40P01) still propagate.
     */
    public function syncWithin(string $companyId, string $date): void
    {
        $month = Carbon::parse($date)->format('Y-m');
        try {
            DB::transaction(function () use ($companyId, $month) {
                $live = $this->liveCorrections($companyId, $month);
                if ($live->isEmpty()) {
                    return;
                }
                $prices = [];
                foreach ($live as $correction) {
                    foreach ($correction->metadata['lines'] ?? [] as $line) {
                        $prices[$line['item_id']] = (float) $line['unit_cost'];
                    }
                }
                $this->correct($companyId, $month, $prices, true, true);
            });
        } catch (\Throwable $e) {
            $cause = $e;
            while ($cause->getPrevious() && ! ($cause instanceof QueryException)) {
                $cause = $cause->getPrevious();
            }
            $state = $cause instanceof QueryException ? ($cause->errorInfo[0] ?? (string) $cause->getCode()) : '';
            if (in_array($state, ['40001', '40P01'], true)) {
                throw $e;
            }
            Log::error('lubricant cost correction re-sync failed', ['company_id' => $companyId, 'month' => $month, 'error' => $e->getMessage()]);
        }
    }

    /** @return Collection<int,Transaction> */
    public function liveCorrections(string $companyId, string $month): Collection
    {
        return Transaction::where('company_id', $companyId)
            ->where('transaction_type', self::TYPE)
            ->whereIn('status', ['posted', 'locked'])
            ->whereNull('deleted_at')->whereNull('reversed_by_id')->whereNull('reversal_of_id')
            ->whereRaw("metadata->>'month' = ?", [$month])
            ->get();
    }

    /**
     * Unit cost per item for each month that has a live correction: [month => [item_id => unit_cost]].
     *
     * @return array<string,array<string,float>>
     */
    public function unitCostsByMonth(string $companyId): array
    {
        $costs = [];
        $rows = Transaction::where('company_id', $companyId)
            ->where('transaction_type', self::TYPE)
            ->whereIn('status', ['posted', 'locked'])
            ->whereNull('deleted_at')->whereNull('reversed_by_id')->whereNull('reversal_of_id')
            ->get(['id', 'metadata']);
        foreach ($rows as $row) {
            $metadata = is_array($row->metadata) ? $row->metadata : [];
            foreach ($metadata['lines'] ?? [] as $line) {
                if (isset($metadata['month'], $line['item_id'])) {
                    $costs[$metadata['month']][$line['item_id']] = (float) ($line['unit_cost'] ?? 0);
                }
            }
        }

        return $costs;
    }

    private function undo(Transaction $existing): void
    {
        app(PostingService::class)->reverseTransaction($existing, 'Lubricant cost corrected', $existing->transaction_date);
        foreach (StockMovement::where('company_id', $existing->company_id)->where('gl_transaction_id', $existing->id)->get() as $movement) {
            // The insert trigger only fires on INSERT, so deleting a movement puts its quantity back by hand.
            StockLevel::where('company_id', $existing->company_id)
                ->where('warehouse_id', $movement->warehouse_id)
                ->where('item_id', $movement->item_id)
                ->decrement('quantity', (float) $movement->quantity);
            $movement->delete();
        }
    }

    /** @param array<int,array<string,mixed>> $lines */
    private function post(string $companyId, string $month, array $lines): Transaction
    {
        $lastDay = Carbon::parse($month.'-01')->endOfMonth()->toDateString();
        $currency = DB::table('auth.companies')->where('id', $companyId)->value('base_currency') ?: 'PKR';

        $debits = $credits = [];
        foreach ($lines as $line) {
            $debits[$line['expense_account_id']] = ($debits[$line['expense_account_id']] ?? 0) + $line['cost'];
            $credits[$line['asset_account_id']] = ($credits[$line['asset_account_id']] ?? 0) + $line['cost'];
        }
        $text = "Lubricant cost of sales - {$month}";
        $entries = [];
        foreach ($debits as $accountId => $amount) {
            $entries[] = ['account_id' => $accountId, 'type' => 'debit', 'amount' => round($amount, 2), 'description' => $text];
        }
        foreach ($credits as $accountId => $amount) {
            $entries[] = ['account_id' => $accountId, 'type' => 'credit', 'amount' => round($amount, 2), 'description' => $text];
        }

        $transaction = $this->posting->postBalancedTransaction([
            'company_id' => $companyId,
            'transaction_type' => self::TYPE,
            'date' => $lastDay,
            'currency' => $currency,
            'base_currency' => $currency,
            'description' => $text,
            'reference_type' => 'fuel.lubricant_cost',
            'metadata' => [
                'month' => $month,
                'lines' => array_map(fn ($l) => [
                    'item_id' => $l['item_id'], 'name' => $l['name'], 'quantity' => $l['quantity'],
                    'unit_cost' => $l['unit_cost'], 'cost' => $l['cost'],
                ], $lines),
            ],
        ], $entries);

        $fallbackWarehouseId = null;
        foreach ($lines as $line) {
            // Open items sold from a tank were counted by the dip: no movement for them.
            if ($line['tank']) {
                continue;
            }
            $warehouseId = StockLevel::where('company_id', $companyId)->where('item_id', $line['item_id'])
                ->orderByDesc('quantity')->value('warehouse_id');
            if (! $warehouseId) {
                $fallbackWarehouseId ??= app(OpeningStockService::class)->resolveStandardWarehouse($companyId)->id;
                $warehouseId = $fallbackWarehouseId;
            }
            StockMovement::create([
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'item_id' => $line['item_id'],
                'movement_date' => $lastDay,
                'movement_type' => 'sale',
                'quantity' => -$line['quantity'],
                'unit_cost' => $line['unit_cost'],
                'total_cost' => $line['cost'],
                'gl_transaction_id' => $transaction->id,
                'reference_type' => 'fuel.lubricant_cost',
                'reference_id' => $transaction->id,
                'reason' => 'Other sales '.$month.' - '.$line['name'],
            ]);
        }

        return $transaction;
    }
}
