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

    /**
     * Totals for a whole bill: each line through compute(), then the OVERALL
     * discount taken off the subtotal that remains after line discounts.
     *
     *   subtotal         = sum of line_total (gross, before any discount; as before)
     *   line discounts   = sum of per-line discount_rate amounts (bills.discount_amount)
     *   overall discount = value (type 'amount') or (subtotal - line discounts) x value/100 ('percent')
     *   tax              = per line, rate x (line_total - the line's share of the overall
     *                      discount). The overall discount lowers tax; the per-line
     *                      discount_rate still does not, exactly as before.
     *   total            = subtotal - line discounts - overall discount + tax
     *
     * The overall discount is spread over the lines pro rata by line net
     * (line_total - line discount), 2 decimals, the last line taking the rounding.
     * Each line carries its overall_discount_share (also in 'source') and its total
     * already nets it, so sum(line total) = bill total.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{lines: array<int, array<string, mixed>>, subtotal: float, tax_amount: float, discount_amount: float, overall_discount_amount: float, total: float}
     */
    public static function computeAll(array $lines, ?string $type = null, float|int|string|null $value = 0): array
    {
        $value = (float) ($value ?? 0);
        $computed = array_map(fn ($line) => self::compute($line), array_values($lines));

        $subtotal = array_sum(array_column($computed, 'line_total'));
        $lineDiscounts = array_sum(array_column($computed, 'discount_amount'));
        $netBase = $subtotal - $lineDiscounts;

        $overall = 0.0;
        if ($type === 'amount') {
            $overall = round($value, 2);
        } elseif ($type === 'percent') {
            $overall = round($netBase * $value / 100, 2);
        }
        $overall = $netBase > 0 ? min($overall, round($netBase, 2)) : 0.0;

        $count = count($computed);
        $allocated = 0.0;
        foreach ($computed as $i => &$line) {
            if ($overall <= 0 || $netBase <= 0) {
                $share = 0.0;
            } elseif ($i === $count - 1) {
                $share = round($overall - $allocated, 2);
            } else {
                $share = round($overall * (($line['line_total'] - $line['discount_amount']) / $netBase), 2);
            }
            $allocated += $share;

            $taxRate = (float) ($line['source']['tax_rate'] ?? 0);
            $line['overall_discount_share'] = $share;
            $line['tax_amount'] = round(($line['line_total'] - $share) * ($taxRate / 100), 6);
            $line['total'] = $line['line_total'] + $line['tax_amount'] - $line['discount_amount'] - $share;
            $line['source']['overall_discount_share'] = $share;
        }
        unset($line);

        return [
            'lines' => $computed,
            'subtotal' => $subtotal,
            'tax_amount' => array_sum(array_column($computed, 'tax_amount')),
            'discount_amount' => $lineDiscounts,
            'overall_discount_amount' => $overall,
            'total' => array_sum(array_column($computed, 'total')),
        ];
    }

    /**
     * Rules shared by Create/Update: percent 0-100, amount no more than the
     * subtotal left after line discounts.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    public static function assertOverallDiscountValid(?string $type, float|int|string|null $value, array $lines): void
    {
        $value = (float) ($value ?? 0);
        if ($type === null || $value <= 0) {
            return;
        }
        if ($type === 'percent' && $value > 100) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'overall_discount_value' => "A percentage discount can't be more than 100.",
            ]);
        }
        if ($type === 'amount') {
            $computed = array_map(fn ($line) => self::compute($line), array_values($lines));
            $net = round(array_sum(array_column($computed, 'line_total')) - array_sum(array_column($computed, 'discount_amount')), 2);
            if ($value > $net + 0.0001) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'overall_discount_value' => "The discount can't be more than the subtotal ({$net}).",
                ]);
            }
        }
    }
}
