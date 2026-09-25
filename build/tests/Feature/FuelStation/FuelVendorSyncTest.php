<?php

use App\Models\Company;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\FuelStation\Services\FuelVendorSyncService;
use Illuminate\Support\Facades\DB;

/**
 * Saving Station Settings makes sure the station's brand has a supplier. It used to look for the
 * exact name "PARCO", miss the station's own "Total Parco", and add a second, empty supplier on
 * every save.
 */
test('the brand supplier already on file is reused, whatever it is called', function () {
    $company = Company::create(['name' => 'Sync Co', 'slug' => 'sync-'.str()->lower(str()->random(8)), 'base_currency' => 'PKR']);
    DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    $own = Vendor::create([
        'company_id' => $company->id, 'vendor_number' => 'VEND-00001', 'name' => 'Total Parco',
        'base_currency' => 'PKR', 'is_active' => true,
    ]);

    foreach (['parco', 'total'] as $brand) {
        $found = app(FuelVendorSyncService::class)->ensureVendorForStationSetting($company, $brand);
        expect($found->id)->toBe($own->id);
    }

    expect(Vendor::where('company_id', $company->id)->count())->toBe(1)
        ->and($own->fresh()->vendor_number)->toBe('VEND-00001'); // its own number is kept
});

test('a brand with no supplier on file still gets one', function () {
    $company = Company::create(['name' => 'Sync Co 2', 'slug' => 'sync-'.str()->lower(str()->random(8)), 'base_currency' => 'PKR']);
    DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

    $made = app(FuelVendorSyncService::class)->ensureVendorForStationSetting($company, 'shell');

    expect($made->name)->toBe('Shell Pakistan')
        ->and(Vendor::where('company_id', $company->id)->count())->toBe(1);
});
