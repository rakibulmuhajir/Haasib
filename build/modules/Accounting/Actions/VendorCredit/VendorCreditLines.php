<?php

namespace App\Modules\Accounting\Actions\VendorCredit;

use App\Modules\Accounting\Models\VendorCredit;
use App\Modules\Accounting\Models\VendorCreditItem;
use Illuminate\Support\Facades\Auth;

/** Writes a credit's lines; shared by create and update so they total the same way. */
class VendorCreditLines
{
    public static function replace(VendorCredit $credit, ?array $items): void
    {
        $credit->items()->forceDelete();

        foreach (array_values($items ?? []) as $index => $item) {
            if (empty($item['description']) || ! isset($item['quantity']) || ! isset($item['unit_price'])) {
                continue;
            }

            $lineTotal = round(($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0), 6);
            $taxAmount = round($lineTotal * (($item['tax_rate'] ?? 0) / 100), 6);
            $discountAmount = round($lineTotal * (($item['discount_rate'] ?? 0) / 100), 6);

            VendorCreditItem::create([
                'company_id' => $credit->company_id,
                'vendor_credit_id' => $credit->id,
                'line_number' => $index + 1,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'tax_rate' => $item['tax_rate'] ?? 0,
                'discount_rate' => $item['discount_rate'] ?? 0,
                'line_total' => $lineTotal,
                'tax_amount' => $taxAmount,
                'total' => $lineTotal + $taxAmount - $discountAmount,
                'expense_account_id' => $item['expense_account_id'] ?? null,
                'created_by_user_id' => Auth::id(),
            ]);
        }
    }
}
