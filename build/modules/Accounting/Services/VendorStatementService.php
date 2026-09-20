<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Models\VendorCredit;

/**
 * A supplier's running balance — the payables mirror of CustomerStatementService.
 *
 * Built from the canonical AP-facing records (bills, bill payments, vendor credits)
 * rather than from journal_entries: the same reasoning as the customer statement, since
 * a consolidated journal carries no per-supplier dimension.
 *
 * Presented the way a payable actually behaves rather than borrowing the receivable's
 * sign convention: a bill is a CREDIT that raises what the company owes, a payment and a
 * vendor credit are DEBITS that reduce it, and the running balance is what is still
 * payable. A positive closing balance means money is owed to the supplier.
 */
class VendorStatementService
{
    public function statement(Vendor $vendor): array
    {
        $rows = [];

        Bill::where('company_id', $vendor->company_id)
            ->where('vendor_id', $vendor->id)
            ->whereNotIn('status', ['draft', 'void', 'cancelled'])
            ->get()
            ->each(function (Bill $bill) use (&$rows) {
                $rows[] = [
                    'date' => optional($bill->bill_date)->toDateString(),
                    'type' => 'bill',
                    'reference' => $bill->bill_number,
                    'description' => 'Bill ' . $bill->bill_number
                        . ($bill->vendor_invoice_number ? " ({$bill->vendor_invoice_number})" : ''),
                    'debit' => 0.0,
                    'credit' => (float) $bill->total_amount,
                    'source_id' => $bill->id,
                    'link' => "bills/{$bill->id}",
                    'created_at' => optional($bill->created_at)->toISOString(),
                ];
            });

        BillPayment::where('company_id', $vendor->company_id)
            ->where('vendor_id', $vendor->id)
            ->get()
            ->each(function (BillPayment $payment) use (&$rows) {
                $rows[] = [
                    'date' => optional($payment->payment_date)->toDateString(),
                    'type' => 'payment',
                    'reference' => $payment->payment_number,
                    'description' => 'Payment made' . ($payment->reference_number ? " ({$payment->reference_number})" : ''),
                    'debit' => (float) $payment->amount,
                    'credit' => 0.0,
                    'source_id' => $payment->id,
                    'link' => "bill-payments/{$payment->id}",
                    'created_at' => optional($payment->created_at)->toISOString(),
                ];
            });

        VendorCredit::where('company_id', $vendor->company_id)
            ->where('vendor_id', $vendor->id)
            ->whereNotIn('status', ['draft', 'void', 'cancelled'])
            ->get()
            ->each(function (VendorCredit $credit) use (&$rows) {
                $rows[] = [
                    'date' => optional($credit->credit_date)->toDateString(),
                    'type' => 'vendor_credit',
                    'reference' => $credit->credit_number,
                    'description' => 'Vendor credit ' . $credit->credit_number,
                    'debit' => (float) $credit->amount,
                    'credit' => 0.0,
                    'source_id' => $credit->id,
                    'link' => "vendor-credits/{$credit->id}",
                    'created_at' => optional($credit->created_at)->toISOString(),
                ];
            });

        usort($rows, fn ($a, $b) => [$a['date'], $a['created_at']] <=> [$b['date'], $b['created_at']]);

        $running = 0.0;
        $statement = [[
            'date' => null, 'type' => 'opening_balance', 'reference' => null,
            'description' => 'Opening balance', 'debit' => 0.0, 'credit' => 0.0,
            'source_id' => null, 'link' => null, 'balance' => 0.0,
        ]];
        foreach ($rows as $row) {
            $running = round($running + $row['credit'] - $row['debit'], 2);
            $row['balance'] = $running;
            $statement[] = $row;
        }

        return [
            'rows' => $statement,
            'closing_balance' => $running,
        ];
    }
}
