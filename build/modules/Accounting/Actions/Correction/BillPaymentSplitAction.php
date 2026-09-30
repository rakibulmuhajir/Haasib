<?php

namespace App\Modules\Accounting\Actions\Correction;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Services\BillCorrectionService;
use Illuminate\Validation\ValidationException;

/** Splits a supplier payment between the suppliers it paid. See BillCorrectionService. */
class BillPaymentSplitAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'bill_payment_id' => 'required|uuid',
            'shares' => 'required|array|min:2',
            'shares.*.vendor_id' => 'required|uuid',
            'shares.*.amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|min:3|max:500',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::BILL_PAY;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $record = BillPayment::where('company_id', $company->id)->find($params['bill_payment_id']);
        if (! $record) {
            throw ValidationException::withMessages(['bill_payment_id' => 'Not found in this company.']);
        }
        $result = app(BillCorrectionService::class)->billPaymentSplit($record, $params['shares'], $params['reason']);

        return [
            'message' => "Corrected ({$result['number']})",
            'data' => $result,
        ];
    }
}
