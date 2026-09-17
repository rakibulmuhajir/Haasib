<?php

namespace App\Modules\Accounting\Actions\Bill;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillLineItem;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
            'line_items.*.unit_price' => 'required|numeric|min:0',
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

        if (in_array($bill->status, ['paid', 'void', 'cancelled'], true)) {
            throw new \InvalidArgumentException('Bill cannot be updated in current status');
        }

        if (!empty($params['line_items']) && ($bill->paid_amount > 0 || $bill->lineItems()->where('quantity_received', '>', 0)->exists())) {
            throw new \InvalidArgumentException('Paid or received bill lines cannot be replaced. Use a credit note or stock adjustment to preserve the original payment and receipt history.');
        }

        return \App\Services\AccountingWriteTransaction::run(function () use ($bill, $params) {
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

                $journalRelevantChanged = $this->lineItemsChanged($bill, $normalizedLines);

                $totals = collect($normalizedLines)->map(function ($item) {
                    $lineTotal = round(($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0), 6);
                    $taxAmount = round($lineTotal * (($item['tax_rate'] ?? 0) / 100), 6);
                    $discountAmount = round($lineTotal * (($item['discount_rate'] ?? 0) / 100), 6);
                    $total = $lineTotal + $taxAmount - $discountAmount;
                    return ['line_total' => $lineTotal, 'tax_amount' => $taxAmount, 'discount_amount' => $discountAmount, 'total' => $total, 'source' => $item];
                });

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

                $bill->subtotal = $totals->sum('line_total');
                $bill->tax_amount = $totals->sum('tax_amount');
                $bill->discount_amount = $totals->sum('discount_amount');
                $bill->total_amount = $totals->sum('total');
                $bill->balance = $bill->total_amount - $bill->paid_amount;
                $bill->base_amount = round($bill->total_amount * ($bill->exchange_rate ?? 1), 2);
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
            ->get(['line_number', 'quantity', 'unit_price', 'tax_rate', 'discount_rate', 'expense_account_id'])
            ->map(fn ($li) => [
                'line_number' => (int) $li->line_number,
                'quantity' => round((float) $li->quantity, 6),
                'unit_price' => round((float) $li->unit_price, 6),
                'tax_rate' => round((float) $li->tax_rate, 4),
                'discount_rate' => round((float) $li->discount_rate, 4),
                'expense_account_id' => $li->expense_account_id,
            ])->values()->all();

        $newLines = collect($normalizedLines)
            ->map(fn ($item, $idx) => [
                'line_number' => (int) ($item['line_number'] ?? ($idx + 1)),
                'quantity' => round((float) ($item['quantity'] ?? 0), 6),
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

        if (empty($line['expense_account_id'])) {
            $line['expense_account_id'] = $item->asset_account_id ?: $item->expense_account_id;
        }

        return $line;
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
}
