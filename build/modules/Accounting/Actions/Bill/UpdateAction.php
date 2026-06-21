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
        $company = CompanyContext::requireCompany();

        $bill = Bill::where('company_id', $company->id)->findOrFail($params['id']);
        if (in_array($bill->status, ['paid', 'void', 'cancelled'], true)) {
            throw new \InvalidArgumentException('Bill cannot be updated in current status');
        }

        return DB::transaction(function () use ($bill, $params) {
            $update = array_intersect_key($params, array_flip([
                'vendor_invoice_number',
                'due_date',
                'notes',
                'internal_notes',
            ]));

            if (!empty($params['line_items'])) {
                $normalizedLines = collect($params['line_items'])
                    ->map(fn ($item) => $this->withPurchaseDefaults($bill->company_id, $item))
                    ->all();

                $totals = collect($normalizedLines)->map(function ($item) {
                    $lineTotal = round(($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0), 6);
                    $taxAmount = round($lineTotal * (($item['tax_rate'] ?? 0) / 100), 6);
                    $discountAmount = round($lineTotal * (($item['discount_rate'] ?? 0) / 100), 6);
                    $total = $lineTotal + $taxAmount - $discountAmount;
                    return ['line_total' => $lineTotal, 'tax_amount' => $taxAmount, 'total' => $total, 'source' => $item];
                });

                $bill->lineItems()->delete();

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
                $bill->total_amount = $totals->sum('total');
                $bill->balance = $bill->total_amount - $bill->paid_amount;
                $bill->base_amount = round($bill->total_amount * ($bill->exchange_rate ?? 1), 2);
            }

            $bill->fill($update);
            $bill->updated_by_user_id = Auth::id();
            $bill->save();

            return [
                'message' => "Bill {$bill->bill_number} updated",
                'data' => ['id' => $bill->id],
            ];
        });
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
