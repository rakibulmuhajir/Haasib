<?php

namespace App\Modules\Accounting\Actions\BillPayment;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Models\BillPaymentAllocation;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\PostingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VoidAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|string',
            'reason' => 'nullable|string',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::BILL_PAY;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $payment = BillPayment::where('company_id', $company->id)->findOrFail($params['id']);

        return DB::transaction(function () use ($payment, $params, $company) {
            $transaction = null;
            if ($payment->transaction_id) {
                $transaction = Transaction::where('company_id', $company->id)
                    ->where('id', $payment->transaction_id)
                    ->whereNull('deleted_at')
                    ->first();
            }

            if (! $transaction) {
                $transaction = Transaction::where('company_id', $company->id)
                    ->where('reference_type', 'acct.bill_payments')
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

            $allocations = BillPaymentAllocation::where('company_id', $company->id)
                ->where('bill_payment_id', $payment->id)
                ->get();

            foreach ($allocations as $allocation) {
                $bill = Bill::where('company_id', $company->id)
                    ->where('id', $allocation->bill_id)
                    ->first();
                if ($bill) {
                    $newPaidAmount = max(0, round((float) $bill->paid_amount - (float) $allocation->amount_allocated, 6));
                    $newBalance = max(0, round((float) $bill->total_amount - $newPaidAmount, 6));

                    if ($newBalance <= 0.000001) {
                        $newStatus = 'paid';
                    } elseif ($newPaidAmount > 0.000001) {
                        $newStatus = 'partial';
                    } else {
                        $newStatus = $bill->received_at ? 'received' : 'draft';
                    }

                    $bill->paid_amount = $newPaidAmount;
                    $bill->balance = $newBalance;
                    $bill->status = $newStatus;
                    $bill->paid_at = $newStatus === 'paid' ? ($bill->paid_at ?? now()) : null;
                    $bill->updated_by_user_id = Auth::id();
                    $bill->save();
                }
                $allocation->delete();
            }

            $payment->delete();

            return ['message' => "Payment {$payment->payment_number} voided"];
        });
    }
}
