<?php

namespace App\Modules\Accounting\Actions\Invoice;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Invoice;

/**
 * The invoice's reference -- the slip number off the paper receipt. A label only, like the vehicle
 * (SetUnitAction), so it can be written or corrected on any invoice, a posted daily close's included.
 */
class SetReferenceAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|uuid',
            'reference' => 'nullable|string|max:100',
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

        $reference = trim((string) ($params['reference'] ?? ''));
        $invoice->forceFill(['reference' => $reference === '' ? null : $reference])->save();

        return ['message' => 'Reference saved', 'data' => ['id' => $invoice->id, 'reference' => $invoice->reference]];
    }
}
