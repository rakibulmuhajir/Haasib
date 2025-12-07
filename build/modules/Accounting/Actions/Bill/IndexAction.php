<?php

namespace App\Modules\Accounting\Actions\Bill;

use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Bill;
use App\Support\PaletteFormatter;
use Illuminate\Support\Str;

class IndexAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'vendor_id' => 'nullable|string',
            'status' => 'nullable|string',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'limit' => 'nullable|integer|min:1|max:100',
        ];
    }

    public function permission(): ?string
    {
        return null;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $limit = $params['limit'] ?? 50;

        $query = Bill::with('vendor')
            ->where('company_id', $company->id)
            ->orderByDesc('bill_date');

        if (!empty($params['vendor_id'])) {
            $query->where('vendor_id', $params['vendor_id']);
        }

        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        if (!empty($params['from_date'])) {
            $query->where('bill_date', '>=', $params['from_date']);
        }
        if (!empty($params['to_date'])) {
            $query->where('bill_date', '<=', $params['to_date']);
        }

        if (!empty($params['search'])) {
            $term = $params['search'];
            $query->where(function ($q) use ($term) {
                $q->where('bill_number', 'ilike', "%{$term}%")
                    ->orWhere('vendor_invoice_number', 'ilike', "%{$term}%");
            });
        }

        $bills = $query->limit($limit)->get();

        return [
            'data' => PaletteFormatter::table(
                headers: ['Bill #', 'Vendor', 'Date', 'Due', 'Total', 'Paid', 'Balance', 'Status'],
                rows: $bills->map(fn ($b) => [
                    $b->bill_number,
                    Str::limit($b->vendor?->name ?? '', 24),
                    $b->bill_date?->format('Y-m-d'),
                    $b->due_date?->format('Y-m-d'),
                    PaletteFormatter::money($b->total_amount, $b->currency),
                    PaletteFormatter::money($b->paid_amount, $b->currency),
                    PaletteFormatter::money($b->balance, $b->currency),
                    $b->status,
                ])->toArray(),
                footer: $bills->count() . ' bills',
                rowIds: $bills->pluck('id')->toArray()
            ),
        ];
    }
}
