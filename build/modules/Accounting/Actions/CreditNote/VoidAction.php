<?php

namespace App\Modules\Accounting\Actions\CreditNote;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\CreditNote;
use App\Modules\Accounting\Models\CreditNoteApplication;
use App\Support\PaletteFormatter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VoidAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|string|max:255',
            'reason' => 'nullable|string|max:500',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::CREDIT_NOTE_VOID;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        $credit = $this->resolveCredit($params['id'], $company->id);

        if ($credit->status === 'void') {
            throw new \Exception('Credit note is already void');
        }

        $applications = CreditNoteApplication::where('credit_note_id', $credit->id)->count();
        if ($applications > 0) {
            throw new \Exception('Cannot void a credit note that has applications. Remove applications first.');
        }

        return DB::transaction(function () use ($credit, $params) {
            $credit->update([
                'status' => 'void',
                'voided_at' => now(),
                'notes' => ($credit->notes ?? '') .
                    (!empty($params['reason']) ? "\n\nVoid reason: {$params['reason']}" : ''),
            ]);

            return [
                'message' => "Credit note {$credit->credit_note_number} voided",
                'data' => [
                    'id' => $credit->id,
                    'number' => $credit->credit_note_number,
                ],
            ];
        });
    }

    private function resolveCredit(string $identifier, string $companyId): CreditNote
    {
        if (Str::isUuid($identifier)) {
            $credit = CreditNote::where('id', $identifier)
                ->where('company_id', $companyId)
                ->first();
            if ($credit) return $credit;
        }

        $credit = CreditNote::where('company_id', $companyId)
            ->where('credit_note_number', $identifier)
            ->first();
        if ($credit) return $credit;

        $credit = CreditNote::where('company_id', $companyId)
            ->where('credit_note_number', 'like', "%{$identifier}")
            ->first();
        if ($credit) return $credit;

        throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Credit note not found: {$identifier}");
    }
}
