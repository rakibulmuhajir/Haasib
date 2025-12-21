<?php

namespace App\Modules\Accounting\Actions\Payment;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\PaymentAllocation;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\PostingService;
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

            $transaction = null;
            if ($payment->transaction_id) {
                $transaction = Transaction::where('company_id', $payment->company_id)
                    ->where('id', $payment->transaction_id)
                    ->whereNull('deleted_at')
                    ->first();
            }

            if (! $transaction) {
                $transaction = Transaction::where('company_id', $payment->company_id)
                    ->where('reference_type', 'acct.payments')
                    ->where('reference_id', $payment->id)
                    ->whereNull('reversal_of_id')
                    ->whereNull('deleted_at')
                    ->orderByDesc('created_at')
                    ->first();

                if ($transaction && ! $payment->transaction_id) {
                    $payment->transaction_id = $transaction->id;
                    $payment->save();
                }
            }

            if ($transaction) {
                app(PostingService::class)->reverseTransaction($transaction, 'Deleted');
            }

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
