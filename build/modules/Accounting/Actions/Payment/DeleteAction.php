<?php

namespace App\Modules\Accounting\Actions\Payment;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\PaymentAllocation;
use Illuminate\Support\Facades\DB;

class DeleteAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|string|uuid',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::PAYMENT_DELETE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        return DB::transaction(function () use ($params, $company) {
            // Get the payment
            $payment = Payment::where('id', $params['id'])
                ->where('company_id', $company->id)
                ->firstOrFail();

            $paymentNumber = $payment->payment_number;

            // Get all allocations to reverse them
            $allocations = $payment->paymentAllocations()->with('invoice')->get();

            foreach ($allocations as $allocation) {
                $invoice = $allocation->invoice;

                // Reverse the allocation on the invoice
                $newBalance = $invoice->balance + $allocation->amount_allocated;
                $newPaidAmount = $invoice->paid_amount - $allocation->amount_allocated;

                $newStatus = $newBalance >= $invoice->total_amount
                    ? 'sent'
                    : ($newPaidAmount > 0 ? 'partial' : 'sent');

                $invoice->update([
                    'status' => $newStatus,
                    'paid_amount' => $newPaidAmount,
                    'balance' => $newBalance,
                    'paid_at' => null,
                ]);
            }

            // Delete payment allocations first
            PaymentAllocation::where('payment_id', $payment->id)->delete();

            // Delete the payment
            $payment->delete();

            return [
                'message' => "Payment {$paymentNumber} deleted and invoice allocations reversed",
                'data' => [
                    'id' => $payment->id,
                    'number' => $paymentNumber,
                ],
                'redirect' => "/{$company->slug}/payments",
            ];
        });
    }
}