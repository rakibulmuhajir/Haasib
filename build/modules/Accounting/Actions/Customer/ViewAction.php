<?php

namespace App\Modules\Accounting\Actions\Customer;

use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
use App\Support\PaletteFormatter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ViewAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|string|max:255',
        ];
    }

    public function permission(): ?string
    {
        return null;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        $customer = $this->resolveCustomer($params['id'], $company->id);

        // Get invoice stats
        $invoiceStats = Invoice::where('customer_id', $customer->id)
            ->selectRaw("
                COUNT(*) as total_count,
                COUNT(CASE WHEN status IN ('draft', 'sent', 'viewed', 'partial', 'overdue') AND balance > 0 THEN 1 END) as unpaid_count,
                SUM(total_amount) as total_billed,
                SUM(balance) as total_outstanding
            ")
            ->first();

        // Get last invoice date
        $lastInvoice = Invoice::where('customer_id', $customer->id)
            ->orderBy('invoice_date', 'desc')
            ->first();

        // Get payment stats
        $paymentStats = DB::table('acct.payment_allocations as pa')
            ->join('acct.payments as p', 'pa.payment_id', '=', 'p.id')
            ->join('acct.invoices as i', 'pa.invoice_id', '=', 'i.id')
            ->where('i.customer_id', $customer->id)
            ->selectRaw('
                COUNT(*) as payment_count,
                SUM(pa.amount_allocated) as total_payments
            ')
            ->first();

        return [
            'data' => PaletteFormatter::table(
                headers: ['Field', 'Value'],
                rows: [
                    ['Customer Number', $customer->customer_number],
                    ['Name', $customer->name],
                    ['Email', $customer->email ?? '—'],
                    ['Phone', $customer->phone ?? '—'],
                    ['Currency', $customer->base_currency],
                    ['Status', $customer->is_active ? '{success}Active{/}' : '{secondary}Inactive{/}'],
                    ['', ''],  // Spacer
                    ['Invoice Statistics', ''],
                    ['Total Invoices', (string) ($invoiceStats->total_count ?? 0)],
                    ['Unpaid Invoices', (string) ($invoiceStats->unpaid_count ?? 0)],
                    ['Total Billed', PaletteFormatter::money($invoiceStats->total_billed ?? 0, $customer->base_currency)],
                    ['Outstanding', $this->formatBalance($invoiceStats->total_outstanding ?? 0, $customer->base_currency)],
                    ['Last Invoice', $lastInvoice ? $lastInvoice->invoice_date->format('M j, Y') : '—'],
                    ['', ''],  // Spacer
                    ['Payment Statistics', ''],
                    ['Total Payments', (string) ($paymentStats->payment_count ?? 0)],
                    ['Total Paid', PaletteFormatter::money($paymentStats->total_payments ?? 0, $customer->base_currency)],
                    ['', ''],  // Spacer
                    ['Credit Information', ''],
                    ['Credit Limit', $customer->credit_limit ? PaletteFormatter::money($customer->credit_limit, $customer->base_currency) : '—'],
                    ['Available Credit', $customer->getAvailableCredit() > 0 ?
                        PaletteFormatter::money($customer->getAvailableCredit(), $customer->base_currency) : '{secondary}—{/}'],
                    ['Risk Level', $this->formatRiskLevel($customer->getRiskLevel())],
                    ['', ''],  // Spacer
                    ['Contact Information', ''],
                    ['Address', $this->formatAddress($customer)],
                    ['Website', $customer->website ?? '—'],
                    ['Tax ID', $customer->tax_id ?? '—'],
                    ['', ''],  // Spacer
                    ['Internal', ''],
                    ['Created', $customer->created_at->format('M j, Y')],
                    ['Updated', $customer->updated_at->format('M j, Y')],
                ],
                footer: "Customer ID: {$customer->id}"
            ),
        ];
    }

    private function formatBalance(float $amount, string $currency): string
    {
        if ($amount <= 0) {
            return '{success}$0.00{/}';
        }
        return '{warning}' . PaletteFormatter::money($amount, $currency) . '{/}';
    }

    private function formatRiskLevel(string $level): string
    {
        return match ($level) {
            'high' => '{error}High Risk{/}',
            'medium' => '{warning}Medium Risk{/}',
            'low' => '{success}Low Risk{/}',
            default => '{secondary}' . ucfirst($level) . '{/}',
        };
    }

    private function formatAddress(Customer $customer): string
    {
        $parts = array_filter([
            $customer->address,
            $customer->city,
            $customer->state,
            $customer->postal_code,
            $customer->country,
        ]);

        return empty($parts) ? '—' : implode(', ', $parts);
    }

    private function resolveCustomer(string $identifier, string $companyId): Customer
    {
        // Try UUID
        if (Str::isUuid($identifier)) {
            $customer = Customer::where('id', $identifier)
                ->where('company_id', $companyId)
                ->first();
            if ($customer) return $customer;
        }

        // Try exact customer number
        $customer = Customer::where('company_id', $companyId)
            ->where('customer_number', $identifier)
            ->first();
        if ($customer) return $customer;

        // Try exact email
        $customer = Customer::where('company_id', $companyId)
            ->where('email', $identifier)
            ->first();
        if ($customer) return $customer;

        // Try exact name (case-insensitive)
        $customer = Customer::where('company_id', $companyId)
            ->whereRaw('LOWER(name) = ?', [strtolower($identifier)])
            ->first();
        if ($customer) return $customer;

        // Try fuzzy name match (requires pg_trgm extension)
        $customer = Customer::where('company_id', $companyId)
            ->whereRaw('similarity(name, ?) > 0.3', [$identifier])
            ->orderByRaw('similarity(name, ?) DESC', [$identifier])
            ->first();
        if ($customer) return $customer;

        throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Customer not found: {$identifier}");
    }
}
