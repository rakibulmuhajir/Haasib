<?php

namespace App\Modules\Accounting\Actions\Bill;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillLineItem;
use App\Modules\Accounting\Services\DocumentDateLock;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\InventoryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|string',
            'vendor_invoice_number' => 'nullable|string|max:100',
            'due_date' => 'nullable|date',
            'line_items' => 'nullable|array|min:1',
            'line_items.*.item_id' => 'nullable|uuid',
            'line_items.*.warehouse_id' => 'nullable|uuid',
            'line_items.*.description' => 'required|string|max:500',
            'line_items.*.quantity' => 'required|numeric|min:0.01',
            'line_items.*.direct_quantity' => 'nullable|numeric|min:0',
            'line_items.*.unit_price' => 'required|numeric|min:0',
            'line_items.*.line_total' => 'nullable|numeric|min:0|decimal:0,2',
            'line_items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'line_items.*.discount_rate' => 'nullable|numeric|min:0|max:100',
            'line_items.*.expense_account_id' => 'nullable|uuid',
            'notes' => 'nullable|string',
            'internal_notes' => 'nullable|string',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::BILL_UPDATE;
    }

    public function handle(array $params): array
    {
        return \App\Services\AccountingWriteTransaction::run(fn () => $this->execute($params));
    }

    private function execute(array $params): array
    {
        $company = CompanyContext::requireCompany();

        $bill = Bill::where('company_id', $company->id)->findOrFail($params['id']);
        app(\App\Modules\Accounting\Services\OpeningBalanceGuard::class)->assertMutable($company->id, 'bill', $bill->id);

        if (in_array($bill->status, ['void', 'cancelled'], true)) {
            throw new \InvalidArgumentException('Bill cannot be updated in current status');
        }

        // Paying a bill doesn't freeze it -- only its day being locked (by an
        // accounting period close or a module's own day lock, e.g. FuelStation's
        // daily close) does.
        app(DocumentDateLock::class)->assertOpen($company->id, $bill->bill_date->toDateString(), "Bill {$bill->bill_number}");

        // Stock already received freezes the line's quantity/item/warehouse -- but not its
        // price. A supplier's invoice arriving with a more precise rate than the one entered
        // at receipt time (e.g. a daily-close purchase rounded to two decimals) is common
        // enough to need a way in: when the only thing a resubmitted line changes is its
        // unit_price/line_total, allow it through as a revaluation instead of refusing
        // outright. Any other change on a received line -- quantity, item, warehouse, tax,
        // discount, account -- is still refused with the same message as before.
        $moneyOnlyLineEdit = false;
        if (!empty($params['line_items']) && $bill->lineItems()->where('quantity_received', '>', 0)->exists()) {
            $normalizedForCheck = collect($params['line_items'])
                ->map(fn ($item) => $this->withPurchaseDefaults($bill->company_id, $item))
                ->all();

            if (!$this->isMoneyOnlyLineEdit($bill, $normalizedForCheck)) {
                throw new \InvalidArgumentException('Stock on this bill has already been received. Record a stock adjustment instead.');
            }

            $moneyOnlyLineEdit = true;
        }

        return \App\Services\AccountingWriteTransaction::run(function () use ($bill, $params, $moneyOnlyLineEdit) {
            $update = array_intersect_key($params, array_flip([
                'vendor_invoice_number',
                'due_date',
                'notes',
                'internal_notes',
            ]));

            $journalRelevantChanged = false;

            if (!empty($params['line_items'])) {
                $normalizedLines = collect($params['line_items'])
                    ->map(fn ($item) => $this->withPurchaseDefaults($bill->company_id, $item))
                    ->all();

                $this->assertLineAccountsValid($normalizedLines);
                $this->assertDirectQuantityValid($normalizedLines);

                $totals = collect($normalizedLines)->map(fn ($item) => BillLineTotals::compute($item));

                // Compare against the derived unit_price (line_total / quantity when a
                // line_total was submitted), not the raw request value, so a line
                // resubmitted amount-driven is compared on the same figure that will
                // actually be stored.
                $journalRelevantChanged = $this->lineItemsChanged($bill, $totals->pluck('source')->all());

                if ((float) $bill->paid_amount > 0.000001 && $totals->sum('total') < (float) $bill->paid_amount - 0.000001) {
                    throw ValidationException::withMessages([
                        'line_items' => "The bill total can't go below the " . number_format((float) $bill->paid_amount, 2) . ' already paid. Record a vendor credit for the difference.',
                    ]);
                }

                if ($moneyOnlyLineEdit) {
                    // The lines themselves (item, warehouse, quantity, quantity_received)
                    // must survive untouched -- StockReceiptLine/StockMovement rows key off
                    // this line's id, and quantity_received is the very fact that made this
                    // the revaluation path. Only the money columns move.
                    $this->reviseLineItemsInPlace($bill, $totals);
                } else {
                    $bill->lineItems()->forceDelete();

                    foreach ($totals as $index => $line) {
                        $src = $line['source'];
                        BillLineItem::create([
                            'company_id' => $bill->company_id,
                            'bill_id' => $bill->id,
                            'line_number' => $index + 1,
                            'item_id' => $src['item_id'] ?? null,
                            'warehouse_id' => $src['warehouse_id'] ?? null,
                            'description' => $src['description'],
                            'quantity' => $src['quantity'],
                            'direct_quantity' => $src['direct_quantity'] ?? 0,
                            'unit_price' => $src['unit_price'],
                            'tax_rate' => $src['tax_rate'] ?? 0,
                            'discount_rate' => $src['discount_rate'] ?? 0,
                            'line_total' => $line['line_total'],
                            'tax_amount' => $line['tax_amount'],
                            'total' => $line['total'],
                            'expense_account_id' => $src['expense_account_id'] ?? null,
                            'created_by_user_id' => Auth::id(),
                        ]);
                    }
                }

                $bill->subtotal = $totals->sum('line_total');
                $bill->tax_amount = $totals->sum('tax_amount');
                $bill->discount_amount = $totals->sum('discount_amount');
                $bill->total_amount = $totals->sum('total');
                $bill->base_amount = round($bill->total_amount * ($bill->exchange_rate ?? 1), 2);

                // Same paid/partial bookkeeping BillPayment\CreateAction and
                // VoidAction use when money is applied against a bill -- a
                // total edited after payment must re-derive the same status,
                // not just the balance.
                $newBalance = max(0, round((float) $bill->total_amount - (float) $bill->paid_amount, 6));
                $bill->balance = $newBalance;
                if ((float) $bill->paid_amount > 0.000001) {
                    if ($newBalance <= 0.000001) {
                        $bill->status = 'paid';
                        $bill->paid_at = $bill->paid_at ?? now();
                    } else {
                        $bill->status = 'partial';
                        $bill->paid_at = null;
                    }
                }
            }

            $bill->fill($update);
            $bill->updated_by_user_id = Auth::id();
            $bill->save();

            if ($bill->transaction_id && $journalRelevantChanged) {
                $oldJournal = \App\Modules\Accounting\Models\Transaction::where('company_id', $bill->company_id)->findOrFail($bill->transaction_id);
                $posting = app(\App\Modules\Accounting\Services\PostingService::class);
                $posting->reverseTransaction($oldJournal, 'Document amended', $oldJournal->transaction_date);
                $newJournal = $posting->postBill($bill->fresh(), \App\Modules\Accounting\Models\Transaction::generateJournalNumber($bill->company_id));
                $bill->update(['transaction_id' => $newJournal->id]);
            }

            return [
                'message' => "Bill {$bill->bill_number} updated",
                'data' => ['id' => $bill->id],
            ];
        });
    }

    /**
     * Whether the submitted line items differ, in any field the posted
     * journal actually depends on (amount, tax, discount, expense account),
     * from what is currently stored. A vendor_invoice_number/due_date/notes
     * -only edit that happens to resubmit the unchanged lines must not
     * trigger a reversal + repost.
     *
     * @param  array<int, array<string, mixed>>  $normalizedLines
     */
    private function lineItemsChanged(Bill $bill, array $normalizedLines): bool
    {
        $oldLines = BillLineItem::where('bill_id', $bill->id)
            ->orderBy('line_number')
            ->get(['line_number', 'quantity', 'direct_quantity', 'unit_price', 'tax_rate', 'discount_rate', 'expense_account_id'])
            ->map(fn ($li) => [
                'line_number' => (int) $li->line_number,
                'quantity' => round((float) $li->quantity, 6),
                'direct_quantity' => round((float) $li->direct_quantity, 3),
                'unit_price' => round((float) $li->unit_price, 6),
                'tax_rate' => round((float) $li->tax_rate, 4),
                'discount_rate' => round((float) $li->discount_rate, 4),
                'expense_account_id' => $li->expense_account_id,
            ])->values()->all();

        $newLines = collect($normalizedLines)
            ->map(fn ($item, $idx) => [
                'line_number' => (int) ($item['line_number'] ?? ($idx + 1)),
                'quantity' => round((float) ($item['quantity'] ?? 0), 6),
                'direct_quantity' => round((float) ($item['direct_quantity'] ?? 0), 3),
                'unit_price' => round((float) ($item['unit_price'] ?? 0), 6),
                'tax_rate' => round((float) ($item['tax_rate'] ?? 0), 4),
                'discount_rate' => round((float) ($item['discount_rate'] ?? 0), 4),
                'expense_account_id' => $item['expense_account_id'] ?? null,
            ])
            ->sortBy('line_number')
            ->values()
            ->all();

        return $oldLines !== $newLines;
    }

    /**
     * True when every submitted line matches the stored one on everything except
     * unit_price/line_total: same count, same item/warehouse/quantity/direct_quantity,
     * same tax_rate/discount_rate/expense_account_id, in the same order. That is the one
     * shape a bill line with stock already received is still allowed to change to.
     *
     * @param  array<int, array<string, mixed>>  $normalizedLines
     */
    private function isMoneyOnlyLineEdit(Bill $bill, array $normalizedLines): bool
    {
        $stored = BillLineItem::where('bill_id', $bill->id)->orderBy('line_number')->get()->values();

        if ($stored->count() !== count($normalizedLines)) {
            return false;
        }

        $numEqual = fn ($a, $b, $decimals) => abs(round((float) $a, $decimals) - round((float) $b, $decimals)) < 0.0000001;

        foreach (array_values($normalizedLines) as $index => $line) {
            $old = $stored[$index];

            if ((string) ($line['item_id'] ?? '') !== (string) ($old->item_id ?? '')) return false;
            if ((string) ($line['warehouse_id'] ?? '') !== (string) ($old->warehouse_id ?? '')) return false;
            if (!$numEqual($line['quantity'] ?? 0, $old->quantity, 6)) return false;
            if (!$numEqual($line['direct_quantity'] ?? 0, $old->direct_quantity, 3)) return false;
            if (!$numEqual($line['tax_rate'] ?? 0, $old->tax_rate, 4)) return false;
            if (!$numEqual($line['discount_rate'] ?? 0, $old->discount_rate, 4)) return false;
            if ((string) ($line['expense_account_id'] ?? '') !== (string) ($old->expense_account_id ?? '')) return false;
        }

        return true;
    }

    /**
     * The money-only revaluation path: update each existing BillLineItem's price columns
     * in place (never delete/recreate -- that would orphan the StockReceiptLine/
     * StockMovement rows the received line already produced, and reset quantity_received).
     * A line whose unit_price actually changed and has been received also gets its stock
     * receipt/movement rows and the item's cost_price revalued.
     *
     * @param  \Illuminate\Support\Collection<int, array{line_total: float, tax_amount: float, discount_amount: float, total: float, source: array<string, mixed>}>  $totals
     */
    private function reviseLineItemsInPlace(Bill $bill, \Illuminate\Support\Collection $totals): void
    {
        $stored = BillLineItem::where('bill_id', $bill->id)->orderBy('line_number')->get()->values();

        foreach ($totals as $index => $line) {
            $lineModel = $stored[$index];
            $src = $line['source'];

            $oldUnitPrice = (float) $lineModel->unit_price;
            $newUnitPrice = (float) $src['unit_price'];

            $lineModel->unit_price = $src['unit_price'];
            $lineModel->line_total = $line['line_total'];
            $lineModel->tax_amount = $line['tax_amount'];
            $lineModel->total = $line['total'];
            $lineModel->updated_by_user_id = Auth::id();
            $lineModel->save();

            if ((float) $lineModel->quantity_received > 0 && abs($newUnitPrice - $oldUnitPrice) > 0.0000001) {
                app(InventoryService::class)->revalueReceivedLine($lineModel, $oldUnitPrice, $newUnitPrice);
            }
        }
    }

    private function withPurchaseDefaults(string $companyId, array $line): array
    {
        $itemId = $line['item_id'] ?? null;
        if (!$itemId) {
            return $line;
        }

        $item = Item::where('company_id', $companyId)
            ->where('is_active', true)
            ->find($itemId);

        if (!$item) {
            return $line;
        }

        if (empty($line['warehouse_id']) && $item->track_inventory) {
            $line['warehouse_id'] = $this->preferredWarehouseId($companyId, $item->id);
        }

        if ($item->track_inventory && $item->asset_account_id) {
            // A tracked item's purchase has to land in inventory -- whatever
            // account the request sent for this line is overridden.
            $line['expense_account_id'] = $item->asset_account_id;
        } elseif (empty($line['expense_account_id'])) {
            $line['expense_account_id'] = $item->asset_account_id ?: $item->expense_account_id;
        }

        return $line;
    }

    /**
     * A bill line has to post to inventory (via the item) or an expense
     * account -- never straight to cash/bank, which would double-count the
     * money movement the bill payment itself already posts.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function assertLineAccountsValid(array $lines): void
    {
        foreach ($lines as $index => $line) {
            $accountId = $line['expense_account_id'] ?? null;
            if (!$accountId) {
                continue;
            }

            $account = Account::find($accountId);
            if ($account && in_array($account->subtype, ['cash', 'bank'], true)) {
                throw ValidationException::withMessages([
                    "line_items.{$index}.expense_account_id" => "A bill line can't post to a cash or bank account. Pick the item or an expense account.",
                ]);
            }
        }
    }

    private function preferredWarehouseId(string $companyId, string $itemId): ?string
    {
        return Warehouse::where('company_id', $companyId)
            ->where('is_active', true)
            ->where('linked_item_id', $itemId)
            ->orderByRaw("case when warehouse_type = 'tank' then 0 else 1 end")
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->value('id')
            ?? StockLevel::where('company_id', $companyId)
                ->where('item_id', $itemId)
                ->where('quantity', '>', 0)
                ->orderByDesc('quantity')
                ->value('warehouse_id')
            ?? Warehouse::where('company_id', $companyId)
                ->where('is_active', true)
                ->orderByDesc('is_primary')
                ->orderBy('name')
                ->value('id');
    }

    /**
     * Litres sold straight to a customer can never exceed the line's own quantity.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function assertDirectQuantityValid(array $lines): void
    {
        foreach ($lines as $index => $line) {
            $direct = (float) ($line['direct_quantity'] ?? 0);
            if ($direct > (float) ($line['quantity'] ?? 0) + 0.0001) {
                throw ValidationException::withMessages([
                    "line_items.{$index}.direct_quantity" => "Can't be more than the line's quantity.",
                ]);
            }
        }
    }
}
