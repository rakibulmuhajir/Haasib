<?php

namespace App\Modules\Accounting\Actions\Vendor;

use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\Vendor;
use App\Support\PaletteFormatter;

class ViewAction implements PaletteAction
{
    use ResolvesVendors;

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
        $vendor = $this->resolveVendor($params['id'], $company->id);

        $billStats = Bill::where('company_id', $company->id)
            ->where('vendor_id', $vendor->id)
            ->selectRaw("
                COUNT(*) as total_count,
                COUNT(CASE WHEN status NOT IN ('paid', 'void', 'cancelled') AND balance > 0 THEN 1 END) as unpaid_count,
                COALESCE(SUM(total_amount), 0) as total_billed,
                COALESCE(SUM(CASE WHEN status NOT IN ('paid', 'void', 'cancelled') THEN balance ELSE 0 END), 0) as total_outstanding
            ")
            ->first();

        $lastBill = Bill::where('company_id', $company->id)
            ->where('vendor_id', $vendor->id)
            ->orderByDesc('bill_date')
            ->first();

        $currency = $vendor->base_currency ?? $company->base_currency ?? 'USD';

        return [
            'data' => PaletteFormatter::table(
                headers: ['Field', 'Value'],
                rows: [
                    ['Vendor Number', $vendor->vendor_number ?? '—'],
                    ['Name', $vendor->name],
                    ['Type', Vendor::TYPES[$vendor->vendor_type] ?? '—'],
                    ['Email', $vendor->email ?? '—'],
                    ['Phone', $vendor->phone ?? '—'],
                    ['Currency', $currency],
                    ['Status', $vendor->is_active ? '{success}Active{/}' : '{secondary}Inactive{/}'],
                    ['', ''],
                    ['Bill Statistics', ''],
                    ['Total Bills', (string) ($billStats->total_count ?? 0)],
                    ['Unpaid Bills', (string) ($billStats->unpaid_count ?? 0)],
                    ['Total Billed', PaletteFormatter::money((float) ($billStats->total_billed ?? 0), $currency)],
                    ['Outstanding', $this->formatBalance((float) ($billStats->total_outstanding ?? 0), $currency)],
                    ['Last Bill', $lastBill?->bill_date?->format('M j, Y') ?? '—'],
                    ['', ''],
                    ['Contact Information', ''],
                    ['Address', $this->formatAddress($vendor)],
                    ['Website', $vendor->website ?? '—'],
                    ['Tax ID', $vendor->tax_id ?? '—'],
                    ['Account Number', $vendor->account_number ?? '—'],
                    ['Payment Terms', $vendor->payment_terms !== null ? "{$vendor->payment_terms} days" : '—'],
                    ['', ''],
                    ['Internal', ''],
                    ['Created', $vendor->created_at?->format('M j, Y') ?? '—'],
                    ['Updated', $vendor->updated_at?->format('M j, Y') ?? '—'],
                ],
                footer: "Vendor ID: {$vendor->id}"
            ),
        ];
    }

    /** Money owed is not an emergency until it is unpaid, so zero stays quiet. */
    private function formatBalance(float $amount, string $currency): string
    {
        if ($amount <= 0) {
            return '{success}'.PaletteFormatter::money(0, $currency).'{/}';
        }

        return '{warning}'.PaletteFormatter::money($amount, $currency).'{/}';
    }

    private function formatAddress(Vendor $vendor): string
    {
        $address = $vendor->address;

        if (! is_array($address)) {
            return '—';
        }

        $parts = array_filter([
            $address['street'] ?? null,
            $address['city'] ?? null,
            $address['state'] ?? null,
            $address['zip'] ?? null,
            $address['country'] ?? null,
        ]);

        return $parts ? implode(', ', $parts) : '—';
    }
}
