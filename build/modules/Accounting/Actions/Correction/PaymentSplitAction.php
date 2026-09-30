<?php

namespace App\Modules\Accounting\Actions\Correction;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Services\CorrectionService;
use Illuminate\Validation\ValidationException;

/** Splits a payment between the customers who paid it together. See CorrectionService. */
class PaymentSplitAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'payment_id' => 'required|uuid',
            'shares' => 'required|array|min:2',
            'shares.*.customer_id' => 'required|uuid',
            'shares.*.amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|min:3|max:500',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::PAYMENT_UPDATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $record = Payment::where('company_id', $company->id)->find($params['payment_id']);
        if (! $record) {
            throw ValidationException::withMessages(['payment_id' => 'Not found in this company.']);
        }
        $result = app(CorrectionService::class)->paymentSplit($record, $params['shares'], $params['reason']);

        return [
            'message' => "Corrected ({$result['number']})",
            'data' => $result,
        ];
    }
}
