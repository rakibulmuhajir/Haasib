<?php

namespace App\Modules\Accounting\Actions\Invoice;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\CustomerUnit;
use App\Modules\Accounting\Models\Invoice;
use Illuminate\Validation\ValidationException;

/**
 * Which of the customer's vehicles (units) an invoice was for. A label only -- no money moves --
 * so it can be set or corrected on any invoice, a posted daily close's included (the close's
 * invoice guard allows unit_id for that reason).
 */
class SetUnitAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|uuid',
            'unit_id' => 'nullable|uuid',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::INVOICE_UPDATE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $invoice = Invoice::where('company_id', $company->id)->findOrFail($params['id']);

        $unitId = $params['unit_id'] ?? null;
        if ($unitId !== null && ! CustomerUnit::where('company_id', $company->id)->where('customer_id', $invoice->customer_id)->whereKey($unitId)->exists()) {
            throw ValidationException::withMessages(['unit_id' => 'Choose one of this customer\'s vehicles.']);
        }

        $invoice->forceFill(['unit_id' => $unitId])->save();

        return ['message' => 'Vehicle saved', 'data' => ['id' => $invoice->id, 'unit_id' => $unitId]];
    }
}
