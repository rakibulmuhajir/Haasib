<?php

namespace App\Modules\Accounting\Actions\VendorCredit;

use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\VendorCredit;
use App\Support\PaletteFormatter;

class IndexAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'vendor_id' => 'nullable|string',
            'status' => 'nullable|string',
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

        $query = VendorCredit::with('vendor')
            ->where('company_id', $company->id)
            ->orderByDesc('credit_date');

        if (!empty($params['vendor_id'])) {
            $query->where('vendor_id', $params['vendor_id']);
        }
        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        $credits = $query->limit($limit)->get();

        return [
            'data' => PaletteFormatter::table(
                headers: ['Credit #', 'Vendor', 'Date', 'Amount', 'Reason', 'Status'],
                rows: $credits->map(fn ($c) => [
                    $c->credit_number,
                    $c->vendor?->name,
                    $c->credit_date?->format('Y-m-d'),
                    PaletteFormatter::money($c->amount, $c->currency),
                    $c->reason,
                    $c->status,
                ])->toArray(),
                footer: $credits->count() . ' credits',
                rowIds: $credits->pluck('id')->toArray()
            ),
        ];
    }
}
