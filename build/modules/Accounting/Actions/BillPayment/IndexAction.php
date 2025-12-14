<?php

namespace App\Modules\Accounting\Actions\BillPayment;

use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\BillPayment;
use App\Support\PaletteFormatter;

class IndexAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'vendor_id' => 'nullable|string',
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

        $query = BillPayment::with('vendor')
            ->where('company_id', $company->id)
            ->orderByDesc('payment_date');

        if (!empty($params['vendor_id'])) {
            $query->where('vendor_id', $params['vendor_id']);
        }
        if (!empty($params['from_date'])) {
            $query->where('payment_date', '>=', $params['from_date']);
        }
        if (!empty($params['to_date'])) {
            $query->where('payment_date', '<=', $params['to_date']);
        }

        $payments = $query->limit($limit)->get();

        return [
            'data' => PaletteFormatter::table(
                headers: ['Payment #', 'Vendor', 'Date', 'Amount', 'Method', 'Reference'],
                rows: $payments->map(fn ($p) => [
                    $p->payment_number,
                    $p->vendor?->name,
                    $p->payment_date?->format('Y-m-d'),
                    PaletteFormatter::money($p->amount, $p->currency),
                    $p->payment_method,
                    $p->reference_number,
                ])->toArray(),
                footer: $payments->count() . ' payments',
                rowIds: $payments->pluck('id')->toArray()
            ),
        ];
    }
}
