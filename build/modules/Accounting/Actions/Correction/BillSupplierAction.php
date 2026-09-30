<?php

namespace App\Modules\Accounting\Actions\Correction;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Services\BillCorrectionService;
use Illuminate\Validation\ValidationException;

/** Moves a bill to the supplier it really belongs to. See BillCorrectionService. */
class BillSupplierAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'bill_id' => 'required|uuid',
            'vendor_id' => 'required|uuid',
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
        $result = app(BillCorrectionService::class)->billChangeSupplier($record, $params['vendor_id'], $params['reason']);

        return [
            'message' => "Corrected ({$result['number']})",
            'data' => $result,
        ];
    }
}
