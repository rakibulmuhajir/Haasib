<?php

namespace App\Modules\Accounting\Actions\Vendor;

use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Vendor;
use App\Support\PaletteFormatter;
use Illuminate\Support\Facades\DB;

class IndexAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:100',
            'inactive' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:100',
        ];
    }

    public function permission(): ?string
    {
        return null; // Any authenticated user can list, as with customers.
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $limit = $params['limit'] ?? 50;

        $query = Vendor::where('company_id', $company->id)->orderBy('name');

        if (empty($params['inactive'])) {
            $query->where('is_active', true);
        }

        if (! empty($params['search'])) {
            $term = $params['search'];
            $query->where(function ($q) use ($term) {
                $q->where('name', 'ilike', "%{$term}%")
                    ->orWhere('email', 'ilike', "%{$term}%")
                    ->orWhere('phone', 'ilike', "%{$term}%")
                    ->orWhere('vendor_number', 'ilike', "%{$term}%");
            });
        }

        $vendors = $query->limit($limit)->get();

        $outstanding = DB::table('acct.bills')
            ->whereIn('vendor_id', $vendors->pluck('id'))
            ->whereNotIn('status', ['paid', 'void', 'cancelled'])
            ->where('balance', '>', 0)
            ->selectRaw('vendor_id, SUM(balance) as total')
            ->groupBy('vendor_id')
            ->pluck('total', 'vendor_id');

        return [
            'data' => PaletteFormatter::table(
                headers: ['Name', 'Email', 'Phone', 'Owed', 'Status'],
                rows: $vendors->map(fn ($v) => [
                    $v->name,
                    $v->email ?? '{secondary}—{/}',
                    $v->phone ?? '{secondary}—{/}',
                    $this->formatBalance((float) ($outstanding[$v->id] ?? 0), $v->base_currency ?? 'USD'),
                    ($v->is_active ?? true) ? '{success}● Active{/}' : '{secondary}○ Inactive{/}',
                ])->toArray(),
                footer: $vendors->count().' vendors',
                rowIds: $vendors->pluck('id')->toArray()
            ),
        ];
    }

    private function formatBalance(float $amount, string $currency): string
    {
        if ($amount <= 0) {
            return '{secondary}'.PaletteFormatter::money(0, $currency).'{/}';
        }

        return '{warning}'.PaletteFormatter::money($amount, $currency).'{/}';
    }
}
