<?php

namespace App\Modules\Accounting\Actions\CreditNote;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\CreditNote;
use App\Modules\Accounting\Models\CreditNoteItem;
use Illuminate\Support\Facades\DB;

class DeleteAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|string|uuid',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::CREDIT_NOTE_DELETE;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        return DB::transaction(function () use ($params, $company) {
            // Get the credit note
            $creditNote = CreditNote::where('id', $params['id'])
                ->where('company_id', $company->id)
                ->firstOrFail();

            // Prevent deletion of applied or voided credit notes
            if (in_array($creditNote->status, ['applied', 'void'])) {
                throw new \Exception('Cannot delete ' . $creditNote->status . ' credit note.');
            }

            $creditNoteNumber = $creditNote->credit_note_number;

            // Delete line items first
            CreditNoteItem::where('credit_note_id', $creditNote->id)->delete();

            // Delete the credit note
            $creditNote->delete();

            return [
                'message' => "Credit note {$creditNoteNumber} deleted",
                'data' => [
                    'id' => $creditNote->id,
                    'number' => $creditNoteNumber,
                ],
                'redirect' => "/{$company->slug}/credit-notes",
            ];
        });
    }
}