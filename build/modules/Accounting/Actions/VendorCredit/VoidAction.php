<?php

namespace App\Modules\Accounting\Actions\VendorCredit;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\VendorCredit;
use App\Modules\Accounting\Models\VendorCreditApplication;
use App\Modules\Accounting\Services\PostingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VoidAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|string',
            'cancellation_reason' => 'required|string|max:255',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::VENDOR_CREDIT_VOID;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $credit = VendorCredit::where('company_id', $company->id)->findOrFail($params['id']);

        if ($credit->status === 'void') {
            throw new \InvalidArgumentException('Already void');
        }

        return DB::transaction(function () use ($company, $credit, $params) {
            $transaction = null;
            if ($credit->transaction_id) {
                $transaction = Transaction::where('company_id', $company->id)
                    ->where('id', $credit->transaction_id)
                    ->whereNull('deleted_at')
                    ->first();
            }

            if (! $transaction) {
                $transaction = Transaction::where('company_id', $company->id)
                    ->where('reference_type', 'acct.vendor_credits')
                    ->where('reference_id', $credit->id)
                    ->whereNull('reversal_of_id')
                    ->whereNull('deleted_at')
                    ->orderByDesc('created_at')
                    ->first();

                if ($transaction && ! $credit->transaction_id) {
                    $credit->transaction_id = $transaction->id;
                    $credit->save();
                }
            }

            if ($transaction) {
                app(PostingService::class)->reverseTransaction($transaction, $params['cancellation_reason'] ?? null);
            }

            $applications = VendorCreditApplication::where('vendor_credit_id', $credit->id)->get();
            foreach ($applications as $app) {
                $bill = Bill::where('company_id', $company->id)->find($app->bill_id);
                if ($bill) {
                    $newPaidAmount = max(0, round((float) $bill->paid_amount - (float) $app->amount_applied, 6));
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
                    $bill->save();
                }
                $app->delete();
            }

            $credit->status = 'void';
            $credit->voided_at = now();
            $credit->cancellation_reason = $params['cancellation_reason'];
            $credit->updated_by_user_id = Auth::id();
            $credit->save();

            return ['message' => "Vendor credit {$credit->credit_number} voided"];
        });
    }
}
