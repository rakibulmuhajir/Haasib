<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\GroupTransportItem;
use App\Modules\Umrah\Models\TransportFare;
use App\Modules\Umrah\Models\TransportPackageSector;
use App\Modules\Umrah\Models\TransportSector;
use App\Modules\Umrah\Models\TransportService;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Services\TransportCatalogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function createMasterDataCompany(string $slug): Company
{
    $user = User::factory()->create();
    $company = Company::create([
        'name' => 'Master Data Test',
        'slug' => $slug,
        'owner_id' => $user->id,
        'base_currency' => 'USD',
    ]);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

    return $company;
}

test('transport dependencies enforce a non-destructive lifecycle', function () {
    $company = createMasterDataCompany('master-data-lifecycle');
    $catalog = app(TransportCatalogService::class);
    $sector = TransportSector::create([
        'company_id' => $company->id,
        'code' => 'JED-MAK-T',
        'name' => 'Jeddah to Makkah',
        'origin' => 'Jeddah',
        'destination' => 'Makkah',
        'is_active' => true,
    ]);
    $package = $catalog->createPackage($company->id, [
        'name' => 'Complete Journey Test',
        'sector_ids' => [$sector->id],
    ]);

    expect(fn () => $catalog->setActive($sector, false))
        ->toThrow(ValidationException::class, 'Deactivate this sector\'s active fares and journey packages first.');

    $catalog->setActive($package, false);
    $catalog->setActive($sector, false);

    expect($package->fresh()->is_active)->toBeFalse()
        ->and($sector->fresh()->is_active)->toBeFalse()
        ->and($sector->fresh()->trashed())->toBeFalse();

    expect(fn () => $catalog->setActive($package->fresh(), true))
        ->toThrow(ValidationException::class, 'Reactivate every sector in this journey package first.');

    $catalog->setActive($sector->fresh(), true);
    $catalog->setActive($package->fresh(), true);

    expect($package->fresh()->is_active)->toBeTrue();
});

test('package edits restore links and fare edits preserve historical group snapshots', function () {
    $company = createMasterDataCompany('master-data-snapshots');
    $catalog = app(TransportCatalogService::class);
    $firstSector = $catalog->createSector($company->id, [
        'code' => 'FIRST-T',
        'name' => 'First sector',
        'origin' => 'A',
        'destination' => 'B',
    ]);
    $secondSector = $catalog->createSector($company->id, [
        'code' => 'SECOND-T',
        'name' => 'Second sector',
        'origin' => 'B',
        'destination' => 'C',
    ]);
    $package = $catalog->createPackage($company->id, [
        'name' => 'Editable Journey',
        'sector_ids' => [$firstSector->id, $secondSector->id],
    ]);

    $catalog->updatePackage($package, ['name' => 'Editable Journey', 'sector_ids' => [$firstSector->id]]);
    $catalog->updatePackage($package->fresh(), ['name' => 'Editable Journey', 'sector_ids' => [$firstSector->id, $secondSector->id]]);

    expect(TransportPackageSector::where('transport_package_id', $package->id)->count())->toBe(2)
        ->and(TransportPackageSector::withTrashed()->where('transport_package_id', $package->id)->count())->toBe(2);

    $agent = Agent::create(['company_id' => $company->id, 'agent_number' => 'AGT-MD', 'name' => 'Master Agent']);
    $visaVendor = VisaVendor::create([
        'company_id' => $company->id,
        'vendor_number' => 'VIS-MD',
        'name' => 'Visa Vendor',
        'vendor_type' => VisaVendor::TYPE_VISA_PROVIDER,
    ]);
    $transportVendor = VisaVendor::create([
        'company_id' => $company->id,
        'vendor_number' => 'TRN-MD',
        'name' => 'Transport Vendor',
        'vendor_type' => VisaVendor::TYPE_TRANSPORT_PROVIDER,
    ]);
    $service = TransportService::create([
        'company_id' => $company->id,
        'name' => 'Test Bus',
        'vehicle_type' => 'Bus',
        'is_active' => true,
    ]);
    $fare = $catalog->createFare($company->id, [
        'transport_vendor_id' => $transportVendor->id,
        'transport_service_id' => $service->id,
        'transport_sector_id' => $firstSector->id,
        'name' => 'Original Fare',
        'charging_basis' => TransportFare::BASIS_PER_VEHICLE,
        'sale_amount' => 100,
        'cost_amount' => 80,
    ]);
    $group = VisaGroup::create([
        'company_id' => $company->id,
        'agent_id' => $agent->id,
        'vendor_id' => $visaVendor->id,
        'group_number' => 'UGR-MD',
        'name' => 'Snapshot Group',
        'status' => VisaGroup::STATUS_PASSPORTS_RECEIVED,
        'transport_required' => true,
        'transport_mode' => VisaGroup::TRANSPORT_SPECIALIZED,
    ]);
    $snapshot = GroupTransportItem::create([
        'company_id' => $company->id,
        'visa_group_id' => $group->id,
        'transport_vendor_id' => $transportVendor->id,
        'transport_fare_id' => $fare->id,
        'transport_service_id' => $service->id,
        'transport_sector_id' => $firstSector->id,
        'description' => 'Original Fare',
        'charging_basis' => TransportFare::BASIS_PER_VEHICLE,
        'quantity' => 1,
        'unit_sale_amount' => 100,
        'unit_cost_amount' => 80,
        'total_sale_amount' => 100,
        'total_cost_amount' => 80,
    ]);

    $catalog->updateFare($fare, [
        'transport_vendor_id' => $transportVendor->id,
        'transport_service_id' => $service->id,
        'transport_sector_id' => $firstSector->id,
        'name' => 'New Fare',
        'charging_basis' => TransportFare::BASIS_PER_VEHICLE,
        'sale_amount' => 250,
        'cost_amount' => 200,
    ]);

    expect((float) $fare->fresh()->sale_amount)->toBe(250.0)
        ->and((float) $snapshot->fresh()->unit_sale_amount)->toBe(100.0)
        ->and((float) $snapshot->fresh()->unit_cost_amount)->toBe(80.0)
        ->and($snapshot->fresh()->description)->toBe('Original Fare');
});
