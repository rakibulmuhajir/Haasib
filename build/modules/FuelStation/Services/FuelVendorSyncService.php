<?php

namespace App\Modules\FuelStation\Services;

use App\Models\Company;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\FuelStation\Models\StationSettings;
use Illuminate\Support\Facades\DB;

class FuelVendorSyncService
{
    public function ensureVendorForStationSetting(Company $company, string $fuelVendorCode): ?Vendor
    {
        $vendorName = StationSettings::VENDORS[$fuelVendorCode] ?? null;
        if (!$vendorName || $fuelVendorCode === 'other') {
            return null;
        }

        $vendorNumber = 'FUEL-' . strtoupper($fuelVendorCode);
        $baseCurrency = strtoupper((string) ($company->base_currency ?: 'PKR'));

        return DB::transaction(function () use ($company, $vendorName, $vendorNumber, $baseCurrency) {
            $vendor = Vendor::where('company_id', $company->id)
                ->where(function ($query) use ($vendorNumber, $vendorName) {
                    $query->where('vendor_number', $vendorNumber)
                        ->orWhere('name', $vendorName);
                })
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if ($vendor) {
                $vendor->fill([
                    'vendor_number' => $vendor->vendor_number ?: $vendorNumber,
                    'vendor_type' => Vendor::TYPE_FUEL_DISTRIBUTOR,
                    'base_currency' => $vendor->base_currency ?: $baseCurrency,
                    'payment_terms' => $vendor->payment_terms ?: 30,
                    'is_active' => true,
                ])->save();

                return $vendor;
            }

            return Vendor::create([
                'company_id' => $company->id,
                'vendor_number' => $vendorNumber,
                'name' => $vendorName,
                'vendor_type' => Vendor::TYPE_FUEL_DISTRIBUTOR,
                'base_currency' => $baseCurrency,
                'payment_terms' => 30,
                'is_active' => true,
            ]);
        });
    }

    public function ensureForExistingSettings(): int
    {
        $count = 0;

        StationSettings::query()
            ->with('company')
            ->orderBy('company_id')
            ->each(function (StationSettings $settings) use (&$count) {
                if (!$settings->company) {
                    return;
                }

                if ($this->ensureVendorForStationSetting($settings->company, $settings->fuel_vendor)) {
                    $count++;
                }
            });

        return $count;
    }
}
