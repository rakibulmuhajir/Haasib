<?php

namespace App\Modules\Accounting\Actions\Customer;

use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Domain\Customers\Models\Customer;
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
        return null; // Any authenticated user can list
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $limit = $params['limit'] ?? 50;

        $query = Customer::where('company_id', $company->id)
            ->orderBy('name');

        // Filter by active status
        if (empty($params['inactive'])) {
            $query->where('status', 'active');
        }

        // Search filter
        if (!empty($params['search'])) {
            $term = $params['search'];
            $query->where(function ($q) use ($term) {
                $q->where('name', 'ilike', "%{$term}%")
                  ->orWhere('email', 'ilike', "%{$term}%")
                  ->orWhere('phone', 'ilike', "%{$term}%")
                  ->orWhere('customer_number', 'ilike', "%{$term}%");
            });
        }

        $customers = $query->limit($limit)->get();

        // Calculate outstanding balances
        $customerIds = $customers->pluck('id');

        // Get invoice totals for each customer
        $invoiceTotals = DB::table('acct.invoices')
            ->whereIn('customer_id', $customerIds)
            ->whereIn('status', ['draft', 'sent', 'posted', 'overdue'])
            ->where('balance_due', '>', 0)
            ->selectRaw('customer_id, SUM(balance_due) as total')
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id');

        return [
            'data' => PaletteFormatter::table(
                headers: ['Name', 'Email', 'Phone', 'Balance', 'Status'],
                rows: $customers->map(fn($c) => [
                    $c->name,
                    $c->email ?? '{secondary}—{/}',
                    $c->phone ?? '{secondary}—{/}',
                    $this->formatBalance($invoiceTotals[$c->id] ?? 0, $c->currency),
                    $c->status === 'active' ? '{success}● Active{/}' : '{secondary}○ Inactive{/}',
                ])->toArray(),
                footer: $customers->count() . ' customers'
            ),
        ];
    }

    private function formatBalance(float $amount, string $currency): string
    {
        if ($amount <= 0) {
            return '{secondary}$0.00{/}';
        }
        return '{warning}' . PaletteFormatter::money($amount, $currency) . '{/}';
    }
}