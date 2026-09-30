<?php

namespace App\Modules\Accounting\Actions\Correction;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Services\CorrectionService;
use Illuminate\Validation\ValidationException;

/** Moves an invoice to the customer it really belongs to. See CorrectionService. */
class InvoiceCustomerAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'invoice_id' => 'required|uuid',
            'customer_id' => 'required|uuid',
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
        $result = app(CorrectionService::class)->invoiceChangeCustomer($record, $params['customer_id'], $params['reason']);

        return [
            'message' => "Corrected ({$result['number']})",
            'data' => $result,
        ];
    }
}
