<?php

namespace App\Modules\Accounting\Actions\CreditNote;

use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\CreditNote;
use App\Support\PaletteFormatter;

/**
 * config/command-bus.php has mapped credit_note.list since the palette was
 * built, but the class was never written -- so the one command that lists
 * credit notes answered with a 500 wherever it was reached. The other four
 * credit-note commands exist; this was the gap.
 */
class IndexAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:100',
            'status' => 'nullable|string|max:30',
            'limit' => 'nullable|integer|min:1|max:100',
        ];
    }

    public function permission(): ?string
    {
        return null; // Listing matches customer.list and vendor.list.
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $limit = $params['limit'] ?? 50;

        $query = CreditNote::where('company_id', $company->id)
            ->with('customer')
            ->orderByDesc('credit_date');

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        if (! empty($params['search'])) {
            $term = $params['search'];
            $query->where(function ($q) use ($term) {
                $q->where('credit_note_number', 'ilike', "%{$term}%")
                    ->orWhere('reason', 'ilike', "%{$term}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'ilike', "%{$term}%"));
            });
        }

        $creditNotes = $query->limit($limit)->get();

        return [
            'data' => PaletteFormatter::table(
                headers: ['Number', 'Customer', 'Date', 'Amount', 'Status'],
                rows: $creditNotes->map(fn ($note) => [
                    $note->credit_note_number ?? '{secondary}—{/}',
                    $note->customer?->name ?? '{secondary}—{/}',
                    $note->credit_date?->format('M j, Y') ?? '{secondary}—{/}',
                    PaletteFormatter::money(
                        (float) $note->amount,
                        $note->currency ?? $company->base_currency ?? 'USD'
                    ),
                    PaletteFormatter::status((string) $note->status),
                ])->toArray(),
                footer: $creditNotes->count().' credit notes',
                rowIds: $creditNotes->pluck('id')->toArray()
            ),
        ];
    }
}
