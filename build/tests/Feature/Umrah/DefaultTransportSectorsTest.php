<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Umrah\Models\TransportSector;
use App\Modules\Umrah\Services\TransportCatalogService;
use Illuminate\Support\Facades\DB;

/**
 * A group moves between three cities, so there are six ordered pairs a
 * coach can be asked to drive. The catalogue used to ship four of them,
 * and the two it missed were the Jeddah-Madinah pair -- which the
 * ordinary itinerary needs on the way home.
 */
function sectorCatalogueCompany(): Company
{
    $user = User::factory()->create();
    $company = Company::create([
        'name' => 'Sector Catalogue Test',
        'slug' => 'sector-catalogue-'.str()->lower(str()->random(8)),
        'owner_id' => $user->id,
        'base_currency' => 'SAR',
    ]);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

    return $company;
}

test('every journey between the three cities has a sector', function () {
    $company = sectorCatalogueCompany();

    app(TransportCatalogService::class)->ensureDefaultSectors($company->id);

    $legs = TransportSector::where('company_id', $company->id)
        ->get()
        ->map(fn (TransportSector $sector) => $sector->origin.' -> '.$sector->destination);

    // City to city, both directions, all three pairs.
    $required = [
        'Jeddah Airport -> Makkah Hotel',
        'Makkah Hotel -> Jeddah Airport',
        'Makkah Hotel -> Madinah Hotel',
        'Madinah Hotel -> Makkah Hotel',
        'Jeddah Airport -> Madinah Hotel',
        'Madinah Hotel -> Jeddah Airport',
    ];

    foreach ($required as $leg) {
        expect($legs)->toContain($leg);
    }
});

test('the Madinah airport transfers are still there', function () {
    $company = sectorCatalogueCompany();

    app(TransportCatalogService::class)->ensureDefaultSectors($company->id);

    $codes = TransportSector::where('company_id', $company->id)->pluck('code');

    expect($codes)->toContain('MEDA-MED')->toContain('MED-MEDA');
});

test('seeding twice does not double the catalogue', function () {
    $company = sectorCatalogueCompany();
    $service = app(TransportCatalogService::class);

    $service->ensureDefaultSectors($company->id);
    $first = TransportSector::where('company_id', $company->id)->count();
    $service->ensureDefaultSectors($company->id);

    expect(TransportSector::where('company_id', $company->id)->count())->toBe($first);
});
