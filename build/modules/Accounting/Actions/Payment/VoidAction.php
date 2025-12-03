<?php

namespace App\Modules\Accounting\Actions\Payment;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\PaymentAllocation;
use App\Support\PaletteFormatter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class VoidAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|string|max:255',
            'reason' => 'nullable|string|max:500',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::PAYMENT_VOID;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        $payment = Payment::where('company_id', $company->id)
            ->where('paymentable_type', Invoice::class)
            ->where('id', $params['id'])
            ->firstOrFail();

        if ($payment->is_voided) {
            throw new \Exception("Payment is already voided");
        }

        // Get the invoice and payment allocation
        $allocation = PaymentAllocation::where('payment_id', $payment->id)
            ->where('is_voided', false)
            ->firstOrFail();

        $invoice = Invoice::find($allocation->invoice_id);

        return DB::transaction(function () use ($params, $payment, $allocation, $invoice) {
            // Void the payment allocation
            $allocation->update([
                'is_voided' => true,
                'voided_at' => now(),
                'voided_reason' => $params['reason'] ?? null,
            ]);

            // Void the payment
            $payment->update([
                'is_voided' => true,
                'voided_at' => now(),
                'voided_reason' => $params['reason'] ?? null,
            ]);

            // Update invoice amounts
            $newAmountPaid = $invoice->total_amount - $invoice->balance_due - $allocation->allocated_amount;
            $newBalanceDue = $invoice->total_amount - $newAmountPaid;

            // Determine new status
            $newStatus = 'sent'; // Reset to sent when payment is voided
            $newPaymentStatus = 'unpaid';

            if ($newBalanceDue <= 0) {
                $newStatus = 'paid';
                $newPaymentStatus = 'paid';
            } elseif ($newAmountPaid > 0) {
                $newStatus = 'posted';
                $newPaymentStatus = 'partially_paid';
            }

            // Update invoice
            $invoice->update([
                'payment_status' => $newPaymentStatus,
                'balance_due' => $newBalanceDue,
                'paid_at' => $newPaymentStatus === 'paid' ? now() : null,
            ]);

            return [
                'message' => "Payment voided: " .
                    PaletteFormatter::money($allocation->allocated_amount, $payment->currency) .
                    " on {$invoice->invoice_number}. New balance: " .
                    PaletteFormatter::money($newBalanceDue, $invoice->currency),
                'data' => [
                    'id' => $payment->id,
                    'invoice' => $invoice->invoice_number,
                    'amount_voided' => PaletteFormatter::money($allocation->allocated_amount, $payment->currency),
                    'new_balance' => PaletteFormatter::money($newBalanceDue, $invoice->currency),
                ],
            ];
        });
    }
}
