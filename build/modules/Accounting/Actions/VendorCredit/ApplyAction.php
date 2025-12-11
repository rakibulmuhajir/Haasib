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

class ApplyAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|string',
            'applications' => 'required|array|min:1',
            'applications.*.bill_id' => 'required|uuid',
            'applications.*.amount_applied' => 'required|numeric|min:0.01',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::VENDOR_CREDIT_APPLY;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $credit = VendorCredit::where('company_id', $company->id)->findOrFail($params['id']);

        $sum = collect($params['applications'])->sum('amount_applied');
        if ($sum - $credit->amount > 0.000001) {
            throw new \InvalidArgumentException('Applications exceed credit amount');
        }

        return DB::transaction(function () use ($credit, $company, $params) {
            foreach ($params['applications'] as $app) {
                $bill = Bill::where('company_id', $company->id)->findOrFail($app['bill_id']);

                VendorCreditApplication::create([
                    'company_id' => $company->id,
                    'vendor_credit_id' => $credit->id,
                    'bill_id' => $bill->id,
                    'amount_applied' => $app['amount_applied'],
                    'applied_at' => now(),
                    'user_id' => Auth::id(),
                    'bill_balance_before' => $bill->balance,
                    'bill_balance_after' => $bill->balance - $app['amount_applied'],
                ]);

                $bill->paid_amount += $app['amount_applied'];
                $bill->balance = $bill->total_amount - $bill->paid_amount;
                if ($bill->balance <= 0) {
                    $bill->status = 'paid';
                } else {
                    $bill->status = 'partial';
                }
                $bill->save();
            }

            if ($credit->amount - collect($params['applications'])->sum('amount_applied') <= 0.000001) {
                $credit->status = 'applied';
                $credit->updated_by_user_id = Auth::id();
                $credit->save();
            }

            return ['message' => "Vendor credit {$credit->credit_number} applied"];
        });
    }
}
