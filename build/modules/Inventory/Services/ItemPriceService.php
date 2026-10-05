<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\FuelStation\Services\DailyCloseLockService;
use App\Modules\FuelStation\Services\LubricantCostService;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemPrice;
use App\Modules\Inventory\Models\ItemPriceChange;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Price history for ordinary products.
 *
 * The price on a day is the latest live entry dated on or before it; one entry per product per
 * date, so saving a date again replaces it. Entries in a month whose fuel daily closes are locked
 * are settled history and cannot be added, changed or deleted. Fuels are not handled here: their
 * history is fuel.rate_changes, which the timeline only displays.
 *
 * items.selling_price / cost_price stay what the rest of the app reads; every change re-points
 * them at the entry in force today.
 */
class ItemPriceService
{
    public function __construct(private readonly DailyCloseLockService $locks) {}

    /** Create the entry for a date, or replace the one already there. */
    public function save(string $companyId, string $itemId, string $date, float $salePrice, ?float $purchasePrice, ?string $notes, ?string $userId): ItemPrice
    {
        $item = Item::where('company_id', $companyId)->findOrFail($itemId);
        if ($item->fuel_category) {
            throw ValidationException::withMessages(['item_id' => 'Fuel prices are set under Fuel prices.']);
        }
        $date = Carbon::parse($date)->toDateString();
        $this->assertOpen($companyId, $date);

        return DB::transaction(function () use ($companyId, $item, $date, $salePrice, $purchasePrice, $notes, $userId) {
            $entry = ItemPrice::where('company_id', $companyId)->where('item_id', $item->id)
                ->whereDate('effective_date', $date)->lockForUpdate()->first();
            $old = $entry ? [(float) $entry->sale_price, $entry->purchase_price === null ? null : (float) $entry->purchase_price] : [null, null];

            if ($entry) {
                $entry->update([
                    'sale_price' => $salePrice, 'purchase_price' => $purchasePrice,
                    'notes' => $notes, 'updated_by_user_id' => $userId,
                ]);
                $action = 'updated';
            } else {
                $entry = ItemPrice::create([
                    'company_id' => $companyId, 'item_id' => $item->id, 'effective_date' => $date,
                    'sale_price' => $salePrice, 'purchase_price' => $purchasePrice, 'notes' => $notes,
                    'created_by_user_id' => $userId, 'updated_by_user_id' => $userId,
                ]);
                $action = 'created';
            }

            $this->log($companyId, $item->id, $date, $action, $old, [$salePrice, $purchasePrice], $userId, $notes);
            $this->syncItem($item);

            return $entry;
        });
    }

    public function delete(string $companyId, string $priceId, ?string $userId): void
    {
        $entry = ItemPrice::where('company_id', $companyId)->findOrFail($priceId);
        $date = $entry->effective_date->toDateString();
        $this->assertOpen($companyId, $date);

        DB::transaction(function () use ($companyId, $entry, $date, $userId) {
            $old = [(float) $entry->sale_price, $entry->purchase_price === null ? null : (float) $entry->purchase_price];
            $entry->update(['updated_by_user_id' => $userId]);
            $entry->delete();
            $this->log($companyId, $entry->item_id, $date, 'deleted', $old, [null, null], $userId, $entry->notes);
            $item = Item::where('company_id', $companyId)->find($entry->item_id);
            if ($item) {
                $this->syncItem($item);
            }
        });
    }

    /** Point items.selling_price (and cost_price when the entry gives one) at the entry in force today. */
    public function syncItem(Item $item, ?Carbon $today = null): void
    {
        $inForce = $this->inForce($item->company_id, $item->id, ($today ?? now())->toDateString());
        if (! $inForce) {
            return;
        }

        $values = ['selling_price' => $inForce->sale_price];
        if ($inForce->purchase_price !== null) {
            $values['cost_price'] = $inForce->purchase_price;
        }
        $item->update($values);
    }

    public function inForce(string $companyId, string $itemId, string $date): ?ItemPrice
    {
        return ItemPrice::where('company_id', $companyId)->where('item_id', $itemId)
            ->whereDate('effective_date', '<=', $date)
            ->orderByDesc('effective_date')->first();
    }

    /**
     * One date-sorted (newest first) timeline: sale prices (or a fuel's rate changes), purchase
     * bills and month lubricant cost corrections.
     *
     * @return array<int,array<string,mixed>>
     */
    public function timeline(Item $item, string $companySlug, ?Carbon $today = null): array
    {
        $companyId = $item->company_id;
        $today = ($today ?? now())->toDateString();
        $lockedMonths = array_flip($this->locks->lockedMonths($companyId));
        $rows = [];

        if ($item->fuel_category) {
            $rates = RateChange::where('company_id', $companyId)->where('item_id', $item->id)->get();
            $current = $rates->filter(fn ($r) => $r->effective_date->toDateString() <= $today)->sortByDesc('effective_date')->first();
            foreach ($rates as $rate) {
                $rows[] = [
                    'id' => 'rate-'.$rate->id, 'date' => $rate->effective_date->toDateString(), 'kind' => 'fuel_rate',
                    'label' => 'Fuel rate', 'price' => (float) $rate->sale_rate, 'quantity' => null,
                    'detail' => 'Purchase '.number_format((float) $rate->purchase_rate, 2),
                    'source_label' => 'Fuel prices', 'source_url' => "/{$companySlug}/fuel/rates",
                    'in_force' => $current !== null && $current->id === $rate->id, 'editable' => false,
                    'price_id' => null, 'purchase_price' => (float) $rate->purchase_rate, 'notes' => $rate->notes,
                ];
            }
        } else {
            $entries = ItemPrice::where('company_id', $companyId)->where('item_id', $item->id)->get();
            $current = $this->inForce($companyId, $item->id, $today);
            foreach ($entries as $entry) {
                $date = $entry->effective_date->toDateString();
                $rows[] = [
                    'id' => 'price-'.$entry->id, 'date' => $date, 'kind' => 'sale_price',
                    'label' => 'Sale price', 'price' => (float) $entry->sale_price, 'quantity' => null,
                    'detail' => $entry->purchase_price !== null ? 'Purchase ref '.number_format((float) $entry->purchase_price, 2) : null,
                    'source_label' => null, 'source_url' => null,
                    'in_force' => $current !== null && $current->id === $entry->id,
                    'editable' => ! isset($lockedMonths[substr($date, 0, 7)]),
                    'price_id' => $entry->id,
                    'purchase_price' => $entry->purchase_price === null ? null : (float) $entry->purchase_price,
                    'notes' => $entry->notes,
                ];
            }
        }

        $bills = DB::table('acct.bill_line_items as li')
            ->join('acct.bills as b', 'b.id', '=', 'li.bill_id')
            ->where('b.company_id', $companyId)->where('li.item_id', $item->id)
            ->whereNull('b.deleted_at')->whereNull('li.deleted_at')
            ->whereNotIn('b.status', ['draft', 'void', 'cancelled'])
            ->get(['li.id as line_id', 'b.id', 'b.bill_number', 'b.bill_date', 'li.quantity', 'li.unit_price']);
        foreach ($bills as $bill) {
            $rows[] = [
                'id' => 'bill-'.$bill->line_id, 'date' => Carbon::parse($bill->bill_date)->toDateString(),
                'kind' => 'purchase_bill', 'label' => 'Purchase (bill)', 'price' => (float) $bill->unit_price,
                'quantity' => (float) $bill->quantity, 'detail' => null,
                'source_label' => $bill->bill_number, 'source_url' => "/{$companySlug}/bills/{$bill->id}",
                'in_force' => false, 'editable' => false, 'price_id' => null, 'purchase_price' => null, 'notes' => null,
            ];
        }

        $corrections = Transaction::where('company_id', $companyId)
            ->where('transaction_type', LubricantCostService::TYPE)
            ->whereIn('status', ['posted', 'locked'])
            ->whereNull('deleted_at')->whereNull('reversed_by_id')->whereNull('reversal_of_id')
            ->get(['id', 'transaction_date', 'metadata']);
        foreach ($corrections as $correction) {
            $metadata = is_array($correction->metadata) ? $correction->metadata : [];
            foreach ($metadata['lines'] ?? [] as $line) {
                if (($line['item_id'] ?? null) !== $item->id) {
                    continue;
                }
                $rows[] = [
                    'id' => 'correction-'.$correction->id, 'date' => Carbon::parse($correction->transaction_date)->toDateString(),
                    'kind' => 'month_correction', 'label' => 'Month correction', 'price' => (float) ($line['unit_cost'] ?? 0),
                    'quantity' => (float) ($line['quantity'] ?? 0),
                    'detail' => isset($metadata['month']) ? Carbon::createFromFormat('Y-m-d', $metadata['month'].'-01')->format('M Y') : null,
                    'source_label' => null, 'source_url' => null,
                    'in_force' => false, 'editable' => false, 'price_id' => null, 'purchase_price' => null, 'notes' => null,
                ];
            }
        }

        $order = ['sale_price' => 0, 'fuel_rate' => 0, 'purchase_bill' => 1, 'month_correction' => 2];
        usort($rows, fn ($a, $b) => [$b['date'], $order[$a['kind']]] <=> [$a['date'], $order[$b['kind']]]);

        return $rows;
    }

    /**
     * The change log for a product, newest first: when, who, and what in a short sentence.
     *
     * @return array<int,array<string,mixed>>
     */
    public function changes(Item $item): array
    {
        $fmt = fn ($v) => $v === null ? null : rtrim(rtrim(number_format((float) $v, 2), '0'), '.');

        return ItemPriceChange::where('company_id', $item->company_id)->where('item_id', $item->id)
            ->with('changedBy:id,name')
            ->orderByDesc('changed_at')->orderByDesc('id')->get()
            ->map(function (ItemPriceChange $c) use ($fmt) {
                $from = $c->effective_date->format('j M');
                $oldSale = $fmt($c->old_sale_price);
                $newSale = $fmt($c->new_sale_price);
                $text = match ($c->action) {
                    'created' => "Added price from {$from}: {$newSale}",
                    'deleted' => "Removed price from {$from} ({$oldSale})",
                    default => "Changed price from {$from}: {$oldSale} → {$newSale}",
                };
                $oldPurchase = $fmt($c->old_purchase_price);
                $newPurchase = $fmt($c->new_purchase_price);
                $purchase = null;
                if ($oldPurchase !== $newPurchase) {
                    $purchase = 'Purchase ref: '.($oldPurchase ?? 'none').' → '.($newPurchase ?? 'none');
                }

                return [
                    'id' => $c->id,
                    'changed_at' => $c->changed_at?->toISOString(),
                    'user' => $c->changedBy?->name,
                    'action' => $c->action,
                    'effective_date' => $c->effective_date->toDateString(),
                    'text' => $text,
                    'purchase' => $purchase,
                    'old_sale_price' => $c->old_sale_price === null ? null : (float) $c->old_sale_price,
                    'new_sale_price' => $c->new_sale_price === null ? null : (float) $c->new_sale_price,
                    'old_purchase_price' => $c->old_purchase_price === null ? null : (float) $c->old_purchase_price,
                    'new_purchase_price' => $c->new_purchase_price === null ? null : (float) $c->new_purchase_price,
                    'note' => $c->notes,
                ];
            })->values()->all();
    }

    private function assertOpen(string $companyId, string $date): void
    {
        if ($this->locks->isDateInLockedMonth($companyId, $date)) {
            throw ValidationException::withMessages([
                'effective_date' => Carbon::parse($date)->format('F Y').' is locked.',
            ]);
        }
    }

    /**
     * @param  array{0:?float,1:?float}  $old
     * @param  array{0:?float,1:?float}  $new
     */
    private function log(string $companyId, string $itemId, string $date, string $action, array $old, array $new, ?string $userId, ?string $notes = null): void
    {
        ItemPriceChange::create([
            'company_id' => $companyId, 'item_id' => $itemId, 'effective_date' => $date, 'action' => $action,
            'old_sale_price' => $old[0], 'new_sale_price' => $new[0],
            'old_purchase_price' => $old[1], 'new_purchase_price' => $new[1],
            'changed_by_user_id' => $userId, 'changed_at' => now(), 'notes' => $notes,
        ]);
    }
}
