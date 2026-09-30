<?php

namespace App\Modules\Accounting\Actions\Correction;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Services\BillCorrectionService;
use Illuminate\Validation\ValidationException;

/** Moves a bill payment to the supplier who was really paid. See BillCorrectionService. */
class BillPaymentSupplierAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'bill_payment_id' => 'required|uuid',
            'vendor_id' => 'required|uuid',
            'apply_oldest_first' => 'nullable|boolean',
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
        $result = app(BillCorrectionService::class)->billPaymentChangeSupplier($record, $params['vendor_id'], $params['reason'], (bool) ($params['apply_oldest_first'] ?? true));

        return [
            'message' => "Corrected ({$result['number']})",
            'data' => $result,
        ];
    }
}
