<?php

namespace App\Modules\Accounting\Actions\VendorCredit;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\VendorCredit;
use App\Modules\Accounting\Models\VendorCreditApplication;
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

        return DB::transaction(function () use ($credit, $params) {
            $applications = VendorCreditApplication::where('vendor_credit_id', $credit->id)->get();
            foreach ($applications as $app) {
                $bill = Bill::find($app->bill_id);
                if ($bill) {
                    $bill->paid_amount -= $app->amount_applied;
                    $bill->balance = $bill->total_amount - $bill->paid_amount;
                    $bill->status = $bill->balance <= 0 ? 'paid' : 'partial';
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
