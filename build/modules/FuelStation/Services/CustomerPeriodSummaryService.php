<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\Accounting\Models\CreditNote;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\InvoiceLineItem;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Services\CustomerStatementService;
use Illuminate\Support\Facades\DB;

/**
 * What one customer bought, paid and owes over a date range: per-product quantity, gross,
 * discount and net, then opening + bought - paid +/- other = closing.
 *
 * Opening and closing come from CustomerStatementService (the same figures the page's
 * Statement shows), never recomputed here, so the two can never disagree. "Other" is
 * whatever bought and paid do not explain (credit notes, corrections), derived as
 * closing - opening - bought + paid, so the block always adds up.
 */
class CustomerPeriodSummaryService
{
    public function __construct(private readonly CustomerStatementService $statements) {}

    public function run(string $companyId, string $customerId, string $from, string $to, string $slug): array
    {
        $customer = Customer::where('company_id', $companyId)->findOrFail($customerId);

        $invoices = Invoice::where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->whereNotIn('status', ['draft', 'void', 'cancelled'])
            ->whereBetween('invoice_date', [$from, $to])
            ->get(['id', 'discount_amount']);

        $lines = $invoices->isEmpty() ? collect() : InvoiceLineItem::where('company_id', $companyId)
            ->whereIn('invoice_id', $invoices->pluck('id'))
            ->orderBy('line_number')
            ->get(['invoice_id', 'item_id', 'quantity', 'line_total'])
            ->groupBy('invoice_id');

        // Per product bucket: item_id (or 'other') => [qty, gross, discount].
        $buckets = [];
        foreach ($invoices as $invoice) {
            $invoiceLines = $lines->get($invoice->id, collect());
            $invoiceGross = round((float) $invoiceLines->sum('line_total'), 2);
            $discountLeft = round((float) $invoice->discount_amount, 2);
            $count = $invoiceLines->count();

            foreach ($invoiceLines->values() as $i => $line) {
                $gross = round((float) $line->line_total, 2);
                // Pro rata by line_total; the last line takes the rounding remainder.
                $share = $i === $count - 1
                    ? $discountLeft
                    : ($invoiceGross > 0 ? round(round((float) $invoice->discount_amount, 2) * $gross / $invoiceGross, 2) : 0.0);
                $discountLeft = round($discountLeft - $share, 2);

                $key = $line->item_id ?: 'other';
                $buckets[$key] ??= ['qty' => 0.0, 'gross' => 0.0, 'discount' => 0.0];
                $buckets[$key]['qty'] += (float) $line->quantity;
                $buckets[$key]['gross'] += $gross;
                $buckets[$key]['discount'] += $share;
            }
        }

        $items = DB::table('inv.items')
            ->whereIn('id', array_values(array_filter(array_keys($buckets), fn ($k) => $k !== 'other')))
            ->get(['id', 'name', 'unit_of_measure'])
            ->keyBy('id');

        $invoicesLink = "/{$slug}/invoices?".http_build_query(['customer_id' => $customerId, 'from' => $from, 'to' => $to]);

        $products = [];
        foreach ($buckets as $key => $b) {
            $item = $key === 'other' ? null : $items->get($key);
            $products[] = [
                'item_id' => $key === 'other' ? null : $key,
                'name' => $item->name ?? 'Other',
                'unit' => $item->unit_of_measure ?? null,
                'quantity' => round($b['qty'], 2),
                'gross' => round($b['gross'], 2),
                'discount' => round($b['discount'], 2),
                'net' => round($b['gross'] - $b['discount'], 2),
                'link' => $invoicesLink,
            ];
        }
        usort($products, fn ($a, $b) => [$a['name'] === 'Other', $a['name']] <=> [$b['name'] === 'Other', $b['name']]);

        $totals = [
            'gross' => round(array_sum(array_column($products, 'gross')), 2),
            'discount' => round(array_sum(array_column($products, 'discount')), 2),
            'net' => round(array_sum(array_column($products, 'net')), 2),
        ];

        $statement = $this->statements->statement($customer, $from, $to);
        $opening = (float) $statement['opening_balance'];
        $closing = (float) $statement['closing_balance'];

        $payments = Payment::where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->whereBetween('payment_date', [$from, $to]);
        $paid = round((float) (clone $payments)->sum('amount'), 2);
        $paymentCount = (clone $payments)->count();

        $bought = $totals['net'];
        $other = round($closing - $opening - $bought + $paid, 2);

        return [
            'from' => $from,
            'to' => $to,
            'products' => $products,
            'totals' => $totals,
            'money' => [
                'opening' => $opening,
                'bought' => $bought,
                'paid' => $paid,
                'payment_count' => $paymentCount,
                'payments_link' => "/{$slug}/payments?".http_build_query(['customer_id' => $customerId, 'from' => $from, 'to' => $to]),
                'other' => abs($other) < 0.005 ? 0.0 : $other,
                'closing' => $closing,
            ],
        ];
    }
}
