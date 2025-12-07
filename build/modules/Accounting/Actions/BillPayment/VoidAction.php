<?php

namespace App\Modules\Accounting\Actions\BillPayment;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Models\BillPaymentAllocation;
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

        return DB::transaction(function () use ($payment) {
            $allocations = BillPaymentAllocation::where('bill_payment_id', $payment->id)->get();

            foreach ($allocations as $allocation) {
                $bill = Bill::where('id', $allocation->bill_id)->first();
                if ($bill) {
                    $bill->paid_amount -= $allocation->amount_allocated;
                    $bill->balance = $bill->total_amount - $bill->paid_amount;
                    $bill->status = $bill->balance <= 0 ? 'paid' : 'partial';
                    $bill->save();
                }
                $allocation->delete();
            }

            $payment->delete();

            return ['message' => "Payment {$payment->payment_number} voided"];
        });
    }
}
