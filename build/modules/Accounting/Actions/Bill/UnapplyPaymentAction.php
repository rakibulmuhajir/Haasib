<?php

namespace App\Modules\Accounting\Actions\Bill;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Models\BillPaymentAllocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Takes a payment off a bill: the bill is owed that much again and the money stays with the
 * payment, as credit with the supplier, to be applied to another bill (Apply advance). Nothing
 * is posted -- a supplier payment always debits payables, whichever bill it is applied to; an
 * advance is simply the part of a payment no bill holds (see BillCorrectionService).
 */
class UnapplyPaymentAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|uuid',
            'payment_id' => 'required|uuid',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::BILL_UPDATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        return \App\Services\AccountingWriteTransaction::run(function () use ($company, $params) {
            $bill = Bill::where('company_id', $company->id)->lockForUpdate()->findOrFail($params['id']);
            $payment = BillPayment::where('company_id', $company->id)->findOrFail($params['payment_id']);
            $allocations = BillPaymentAllocation::where('company_id', $company->id)
                ->where('bill_id', $bill->id)->where('bill_payment_id', $payment->id)->get();
            if ($allocations->isEmpty()) {
                throw ValidationException::withMessages(['payment_id' => "{$payment->payment_number} is not applied to {$bill->bill_number}."]);
            }

            $amount = round((float) $allocations->sum('amount_allocated'), 6);
            $paid = max(0.0, round((float) $bill->paid_amount - $amount, 6));
            $balance = round((float) $bill->total_amount - $paid, 6);
            DB::table('acct.bills')->where('id', $bill->id)->update([
                'paid_amount' => $paid,
                'balance' => $balance,
                // A posted bill never goes back to draft: unpaid again, it is simply received.
                'status' => $paid <= 0.000001 ? 'received' : ($balance <= 0.000001 ? 'paid' : 'partial'),
                'paid_at' => $balance <= 0.000001 ? $bill->paid_at : null,
                'updated_at' => now(),
            ]);
            BillPaymentAllocation::whereKey($allocations->pluck('id'))->delete();

            return [
                'message' => number_format($amount, 2)." of {$payment->payment_number} taken off {$bill->bill_number}; kept as credit",
                'data' => ['id' => $bill->id, 'amount' => round($amount, 2)],
            ];
        });
    }
}
