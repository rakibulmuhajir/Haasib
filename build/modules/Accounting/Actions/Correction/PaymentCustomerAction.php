<?php

namespace App\Modules\Accounting\Actions\Correction;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Services\CorrectionService;
use Illuminate\Validation\ValidationException;

/** Moves a payment to the customer who really paid it. See CorrectionService. */
class PaymentCustomerAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'payment_id' => 'required|uuid',
            'customer_id' => 'required|uuid',
            'apply_oldest_first' => 'nullable|boolean',
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
        $result = app(CorrectionService::class)->paymentChangeCustomer($record, $params['customer_id'], $params['reason'], (bool) ($params['apply_oldest_first'] ?? true));

        return [
            'message' => "Corrected ({$result['number']})",
            'data' => $result,
        ];
    }
}
