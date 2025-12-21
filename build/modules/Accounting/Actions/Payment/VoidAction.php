<?php

namespace App\Modules\Accounting\Actions\Payment;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\PaymentAllocation;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\PostingService;
use App\Support\PaletteFormatter;
use Illuminate\Support\Facades\DB;

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
            ->where('id', $params['id'])
            ->firstOrFail();

        if ($payment->trashed()) {
            throw new \Exception("Payment is already voided");
        }

        $allocations = PaymentAllocation::where('payment_id', $payment->id)->get();
        if ($allocations->isEmpty()) {
            throw new \Exception("Payment has no allocations to void");
        }

        return DB::transaction(function () use ($params, $payment, $allocations) {
            $totalVoided = 0;

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
                app(PostingService::class)->reverseTransaction($transaction, $params['reason'] ?? null);
            }

            foreach ($allocations as $allocation) {
                $invoice = $allocation->invoice;
                if ($invoice) {
                    $newPaid = max(0, $invoice->paid_amount - $allocation->amount_allocated);
                    $newBalance = $invoice->balance + $allocation->amount_allocated;

                    $newStatus = $newBalance <= 0
                        ? 'paid'
                        : ($newPaid > 0 ? 'partial' : 'sent');

                    $invoice->update([
                        'paid_amount' => $newPaid,
                        'balance' => $newBalance,
                        'status' => $newStatus,
                        'paid_at' => $newStatus === 'paid' ? now() : null,
                    ]);
                }

                $totalVoided += $allocation->amount_allocated;
                $allocation->delete();
            }

            $payment->delete();

            return [
                'message' => "Payment voided: " .
                    PaletteFormatter::money($totalVoided, $payment->currency),
                'data' => [
                    'id' => $payment->id,
                    'amount_voided' => PaletteFormatter::money($totalVoided, $payment->currency),
                ],
            ];
        });
    }
}
