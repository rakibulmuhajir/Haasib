<?php

namespace App\Modules\Accounting\Actions\Bill;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillLineItem;
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
            'line_items.*.description' => 'required_with:line_items|string|max:500',
            'line_items.*.quantity' => 'required_with:line_items|numeric|min:0.01',
            'line_items.*.unit_price' => 'required_with:line_items|numeric|min:0',
            'line_items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'line_items.*.discount_rate' => 'nullable|numeric|min:0|max:100',
            'line_items.*.account_id' => 'nullable|uuid',
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
                $totals = collect($params['line_items'])->map(function ($item) {
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
                        'description' => $src['description'],
                        'quantity' => $src['quantity'],
                        'unit_price' => $src['unit_price'],
                        'tax_rate' => $src['tax_rate'] ?? 0,
                        'discount_rate' => $src['discount_rate'] ?? 0,
                        'line_total' => $line['line_total'],
                        'tax_amount' => $line['tax_amount'],
                        'total' => $line['total'],
                        'account_id' => $src['account_id'] ?? null,
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
}
