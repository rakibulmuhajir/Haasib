<?php

namespace App\Modules\Umrah\Dashboard\Widgets;

use App\Constants\Permissions;
use App\Dashboard\DashboardWidget;
use App\Models\Company;
use App\Models\User;
use App\Modules\Umrah\Models\HotelVendor;
use App\Modules\Umrah\Models\VisaVendor;

/**
 * "Vendors we owe" — visa, hotel and transport vendors with an open balance.
 *
 * Transport vendors are not a separate model: a "transport vendor" is a
 * umrah.visa_vendors row with service_type = transport_provider (see
 * VisaVendor::SERVICE_TRANSPORT_PROVIDER, and how GroupTransportItem /
 * VisaGroup::mandatoryTransportVendor() both point transport_vendor_id at
 * this same table).
 */
class VendorBalancesWidget implements DashboardWidget
{
    public function key(): string
    {
        return 'umrah.vendor_balances';
    }

    public function title(): string
    {
        return 'Vendors we owe';
    }

    public function description(): string
    {
        return 'Visa, hotel and transport vendors ranked by balance owed.';
    }

    public function permission(): ?string
    {
        return Permissions::UMRAH_VENDOR_VIEW;
    }

    public function defaultSpan(): int
    {
        return 6;
    }

    public function minSpan(): int
    {
        return 6;
    }

    public function resolve(Company $company, User $user, array $options): array
    {
        $limit = (int) ($options['limit'] ?? 8);

        $visaVendors = VisaVendor::where('company_id', $company->id)
            ->where('balance', '<>', 0)
            ->get(['id', 'vendor_number', 'vendor_id', 'service_type', 'balance'])
            ->map(fn (VisaVendor $vendor): array => [
                'id' => $vendor->id,
                'vendor_number' => $vendor->vendor_number,
                'name' => $vendor->name,
                'kind' => $vendor->service_type === VisaVendor::SERVICE_TRANSPORT_PROVIDER ? 'transport' : 'visa',
                'balance' => (float) $vendor->balance,
            ]);

        $hotelVendors = HotelVendor::where('company_id', $company->id)
            ->where('balance', '<>', 0)
            ->get(['id', 'vendor_number', 'name', 'balance'])
            ->map(fn (HotelVendor $vendor): array => [
                'id' => $vendor->id,
                'vendor_number' => $vendor->vendor_number,
                'name' => $vendor->name,
                'kind' => 'hotel',
                'balance' => (float) $vendor->balance,
            ]);

        $rows = $visaVendors->concat($hotelVendors)
            ->sortByDesc('balance')
            ->take($limit)
            ->values()
            ->all();

        return [
            'rows' => $rows,
            'currency' => $company->base_currency,
        ];
    }
}
