<?php

namespace App\Modules\Accounting\Actions\Bill;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\GlPostingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReceiveAction implements PaletteAction
{
    public function rules(): array
    {
        return ['id' => 'required|string'];
    }

    public function permission(): ?string
    {
        return Permissions::BILL_UPDATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $bill = Bill::where('company_id', $company->id)->findOrFail($params['id']);

        if ($bill->status === 'void' || $bill->status === 'cancelled') {
            throw new \InvalidArgumentException('Cannot receive a void/cancelled bill');
        }

        DB::transaction(function () use ($bill) {
            $bill->status = 'received';
            $bill->received_at = now();
            $bill->updated_by_user_id = Auth::id();
            $bill->save();

            if (!$bill->transaction_id) {
                $existing = Transaction::where('company_id', $bill->company_id)
                    ->where('reference_type', 'acct.bills')
                    ->where('reference_id', $bill->id)
                    ->whereNull('reversal_of_id')
                    ->whereNull('deleted_at')
                    ->orderByDesc('created_at')
                    ->first();

                if ($existing) {
                    $bill->transaction_id = $existing->id;
                    $bill->save();
                } else {
                    $posting = app(GlPostingService::class)->postBill($bill);
                    $bill->transaction_id = $posting->id;
                    $bill->save();
                }
            }

        });

        return ['message' => "Bill {$bill->bill_number} marked received"];
    }
}
