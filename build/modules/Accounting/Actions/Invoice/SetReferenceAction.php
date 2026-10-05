<?php

namespace App\Modules\Accounting\Actions\Invoice;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Invoice;

/**
 * The customer's slip on an invoice: its number (the reference) and its date (slip_date, when the
 * slip was logged on another day). Labels only, like the vehicle (SetUnitAction), so they can be
 * written or corrected on any invoice, a posted daily close's included. Only the fields sent change.
 */
class SetReferenceAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|uuid',
            'reference' => 'sometimes|nullable|string|max:100',
            'slip_date' => 'sometimes|nullable|date',
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

        $changes = [];
        if (array_key_exists('reference', $params)) {
            $reference = trim((string) ($params['reference'] ?? ''));
            $changes['reference'] = $reference === '' ? null : $reference;
        }
        if (array_key_exists('slip_date', $params)) {
            // The same day as the booking says nothing: kept empty.
            $date = $params['slip_date'] ? substr((string) $params['slip_date'], 0, 10) : null;
            $changes['slip_date'] = $date === $invoice->invoice_date?->toDateString() ? null : $date;
        }
        if ($changes) {
            $invoice->forceFill($changes)->save();
        }

        return ['message' => 'Slip saved', 'data' => ['id' => $invoice->id, 'reference' => $invoice->reference, 'slip_date' => $invoice->slip_date?->toDateString()]];
    }
}
