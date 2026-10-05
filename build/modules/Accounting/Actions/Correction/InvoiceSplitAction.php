<?php

namespace App\Modules\Accounting\Actions\Correction;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Services\CorrectionService;
use Illuminate\Validation\ValidationException;

/** Splits an invoice between customers, or by vehicle for the same customer. See CorrectionService. */
class InvoiceSplitAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'invoice_id' => 'required|uuid',
            'shares' => 'required|array|min:2',
            'shares.*.customer_id' => 'required|uuid',
            'shares.*.amount' => 'required|numeric|min:0.01',
            'shares.*.unit_id' => 'nullable|uuid',
            'shares.*.quantity' => 'nullable|numeric|min:0.0001',
            'shares.*.reference' => 'nullable|string|max:100',
            'unapply_payments' => 'nullable|boolean',
            'reason' => 'required|string|min:3|max:500',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::INVOICE_UPDATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $record = Invoice::where('company_id', $company->id)->find($params['invoice_id']);
        if (! $record) {
            throw ValidationException::withMessages(['invoice_id' => 'Not found in this company.']);
        }
        $result = app(CorrectionService::class)->invoiceSplit($record, $params['shares'], $params['reason'], (bool) ($params['unapply_payments'] ?? false));

        return [
            'message' => "Corrected ({$result['number']})",
            'data' => $result,
        ];
    }
}
