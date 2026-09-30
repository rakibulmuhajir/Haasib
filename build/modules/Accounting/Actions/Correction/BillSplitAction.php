<?php

namespace App\Modules\Accounting\Actions\Correction;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Services\BillCorrectionService;
use Illuminate\Validation\ValidationException;

/** Splits a bill between suppliers; the first share keeps the bill. See BillCorrectionService. */
class BillSplitAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'bill_id' => 'required|uuid',
            'shares' => 'required|array|min:2',
            'shares.*.vendor_id' => 'required|uuid',
            'shares.*.amount' => 'required|numeric|min:0.01',
            'unapply_payments' => 'nullable|boolean',
            'reason' => 'required|string|min:3|max:500',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::BILL_UPDATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $record = Bill::where('company_id', $company->id)->find($params['bill_id']);
        if (! $record) {
            throw ValidationException::withMessages(['bill_id' => 'Not found in this company.']);
        }
        $result = app(BillCorrectionService::class)->billSplit($record, $params['shares'], $params['reason'], (bool) ($params['unapply_payments'] ?? false));

        return [
            'message' => "Corrected ({$result['number']})",
            'data' => $result,
        ];
    }
}
