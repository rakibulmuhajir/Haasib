<?php

namespace App\Modules\Accounting\Actions\Bill;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\Transaction;

class DeleteAction implements PaletteAction
{
    public function rules(): array
    {
        return ['id' => 'required|string'];
    }

    public function permission(): ?string
    {
        return Permissions::BILL_DELETE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $bill = Bill::where('company_id', $company->id)->findOrFail($params['id']);

        if (($bill->paid_amount ?? 0) > 0) {
            throw new \InvalidArgumentException('Cannot delete bill with payments');
        }

        $postedTx = null;
        if ($bill->transaction_id) {
            $postedTx = Transaction::where('company_id', $company->id)
                ->where('id', $bill->transaction_id)
                ->whereNull('deleted_at')
                ->first();
        }

        if (! $postedTx) {
            $postedTx = Transaction::where('company_id', $company->id)
                ->where('reference_type', 'acct.bills')
                ->where('reference_id', $bill->id)
                ->whereNull('reversal_of_id')
                ->whereNull('deleted_at')
                ->orderByDesc('created_at')
                ->first();
        }

        if ($postedTx) {
            throw new \InvalidArgumentException('Cannot delete a posted bill. Void it to reverse the GL entry.');
        }

        $bill->delete();

        return ['message' => "Bill {$bill->bill_number} deleted"];
    }
}
