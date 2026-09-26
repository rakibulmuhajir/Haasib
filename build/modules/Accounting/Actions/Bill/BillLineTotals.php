<?php

namespace App\Modules\Accounting\Actions\Bill;

/**
 * A vendor's invoice is priced to 3-4 decimal places per unit far more often
 * than the 2-decimal unit_price this form used to accept -- rounding, say,
 * 676543.21 / 2000 down to 338.27 before storing understates the bill by
 * hundreds of rupees over a large enough quantity. So a line may instead
 * submit the total it was actually billed (line_total, 2 decimals, the exact
 * figure on the supplier's invoice) and have unit_price derived from it:
 * unit_price = line_total / quantity, kept at full precision, while
 * line_total itself is stored exactly as given rather than recomputed from
 * quantity * unit_price (that recomputation is what would lose the cents
 * back again).
 *
 * When line_total is absent, the line behaves exactly as before: line_total
 * is quantity * unit_price.
 *
 * A line_total submitted against a zero/blank quantity has nothing to derive
 * unit_price from -- StoreBillRequest's quantity rule (min:0.01) already
 * refuses that line before this class ever sees it, so no separate guard is
 * needed here.
 *
 * Shared by CreateAction and UpdateAction (including the paid-bill edit
 * path) so the two can never drift.
 */
class BillLineTotals
{
    /**
     * @param  array<string, mixed>  $line
     * @return array{line_total: float, tax_amount: float, discount_amount: float, total: float, source: array<string, mixed>}
     */
    public static function compute(array $line): array
    {
        $quantity = (float) ($line['quantity'] ?? 0);
        $givenLineTotal = $line['line_total'] ?? null;

        if ($givenLineTotal !== null && $givenLineTotal !== '' && $quantity > 0) {
            $lineTotal = round((float) $givenLineTotal, 2);
            $line['unit_price'] = round($lineTotal / $quantity, 6);
        } else {
            $lineTotal = round($quantity * (float) ($line['unit_price'] ?? 0), 6);
        }

        $taxAmount = round($lineTotal * (($line['tax_rate'] ?? 0) / 100), 6);
        $discountAmount = round($lineTotal * (($line['discount_rate'] ?? 0) / 100), 6);
        $total = $lineTotal + $taxAmount - $discountAmount;

        return [
            'line_total' => $lineTotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'total' => $total,
            'source' => $line,
        ];
    }
}
