<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\GroupPayment;
use App\Modules\Umrah\Models\GroupTransportItem;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Services\UmrahCoreService;
use Illuminate\Support\Facades\DB;

/** A bare company with the row-level-security context set, as the tests below expect. */
function transportSupplierCompany(string $slug): Company
{
    $company = Company::create([
        'name' => 'Transport Supplier Test',
        'slug' => $slug,
        'owner_id' => User::factory()->create()->id,
        'base_currency' => 'USD',
    ]);

    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

    return $company;
}

test('mandatory and specialized transport costs belong to their transport provider', function () {
    $user = User::factory()->create();
    $company = Company::create([
        'name' => 'Transport Supplier Test',
        'slug' => 'transport-supplier-test',
        'owner_id' => $user->id,
        'base_currency' => 'USD',
    ]);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

    $agent = Agent::create([
        'company_id' => $company->id,
        'agent_number' => 'AGT-TEST',
        'name' => 'Test Agent',
    ]);
    $visaVendor = VisaVendor::create([
        'company_id' => $company->id,
        'vendor_number' => 'VIS-TEST',
        'name' => 'Visa Supplier',
        'vendor_type' => VisaVendor::TYPE_VISA_PROVIDER,
    ]);
    $transportVendor = VisaVendor::create([
        'company_id' => $company->id,
        'vendor_number' => 'TRN-TEST',
        'name' => 'Company Transport',
        'vendor_type' => VisaVendor::TYPE_TRANSPORT_PROVIDER,
        'is_company_owned' => true,
    ]);
    $group = VisaGroup::create([
        'company_id' => $company->id,
        'agent_id' => $agent->id,
        'vendor_id' => $visaVendor->id,
        'mandatory_transport_vendor_id' => $transportVendor->id,
        'group_number' => 'UGR-TEST',
        'name' => 'Supplier Split',
        'status' => VisaGroup::STATUS_PASSPORTS_RECEIVED,
        'travel_date' => '2026-08-01',
        'transport_required' => true,
        'transport_mode' => VisaGroup::TRANSPORT_STANDARD_BUS,
        'included_bus_cost_per_passenger' => 50,
        'included_bus_cost_deduction' => 200,
        'mandatory_transport_cost_amount' => 200,
        'visa_cost_amount' => 1000,
        'transport_cost_amount' => 200,
    ]);
    GroupTransportItem::create([
        'company_id' => $company->id,
        'visa_group_id' => $group->id,
        'transport_vendor_id' => $transportVendor->id,
        'description' => 'Special sector',
        'total_cost_amount' => 400,
    ]);
    GroupPayment::create([
        'company_id' => $company->id,
        'direction' => GroupPayment::DIRECTION_SENT,
        'transport_vendor_id' => $transportVendor->id,
        'payment_number' => 'UPM-TEST',
        'payment_date' => '2026-07-20',
        'amount' => 80,
        'currency' => 'USD',
        'base_currency' => 'USD',
        'base_amount' => 80,
        'method' => GroupPayment::METHOD_CASH,
        'status' => GroupPayment::STATUS_POSTED,
    ]);

    $service = app(UmrahCoreService::class);
    $service->recalculateVendor($visaVendor->id);
    $service->recalculateVendor($transportVendor->id);
    $statement = $service->vendorStatement($transportVendor->fresh());

    expect((float) $visaVendor->fresh()->total_cost)->toBe(1000.0)
        ->and((float) $transportVendor->fresh()->total_cost)->toBe(600.0)
        ->and((float) $transportVendor->fresh()->total_paid)->toBe(80.0)
        ->and((float) $transportVendor->fresh()->balance)->toBe(520.0)
        ->and($statement['charges'])->toBe(600.0)
        ->and($statement['payments'])->toBe(80.0)
        ->and($statement['closing_balance'])->toBe(520.0);
});

test('group vendors resolve visa and transport suppliers independently', function () {
    $user = User::factory()->create();
    $company = Company::create([
        'name' => 'Vendor Defaults Test',
        'slug' => 'vendor-defaults-test',
        'owner_id' => $user->id,
        'base_currency' => 'SAR',
    ]);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

    $transportVendor = VisaVendor::create([
        'company_id' => $company->id,
        'vendor_number' => 'TRN-DEFAULT',
        'name' => 'Default Transport',
        'vendor_type' => VisaVendor::TYPE_TRANSPORT_PROVIDER,
    ]);
    $visaVendor = VisaVendor::create([
        'company_id' => $company->id,
        'vendor_number' => 'VIS-DEFAULT',
        'name' => 'Default Visa',
        'vendor_type' => VisaVendor::TYPE_VISA_PROVIDER,
        'is_default' => true,
    ]);

    $resolved = app(UmrahCoreService::class)->resolveGroupVendors($company->id, [
        'vendor_id' => '11111111-1111-1111-1111-111111111111',
        'mandatory_transport_vendor_id' => '22222222-2222-2222-2222-222222222222',
        'transport_mode' => VisaGroup::TRANSPORT_STANDARD_BUS,
    ], true);

    expect($resolved['vendor_id'])->toBe($visaVendor->id)
        ->and($resolved['mandatory_transport_vendor_id'])->toBe($transportVendor->id);

    expect($resolved['vendor_id'])->not->toBe($resolved['mandatory_transport_vendor_id']);
});

test('standard bus requires an independent transport provider regardless of visa rate', function () {
    $company = transportSupplierCompany('transport-zero-bus-cost');
    $visaVendor = VisaVendor::create([
        'company_id' => $company->id,
        'vendor_number' => 'VIS-ZERO-BUS',
        'name' => 'Visa Without Included Bus',
        'vendor_type' => VisaVendor::TYPE_VISA_PROVIDER,
        'is_default' => true,
        'included_bus_cost_amount' => 0,
    ]);

    expect(fn () => app(UmrahCoreService::class)->resolveGroupVendors($company->id, [
        'transport_mode' => VisaGroup::TRANSPORT_STANDARD_BUS,
    ], true))->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('standard bus pricing excludes children when the provider does not charge child fare', function () {
    $company = transportSupplierCompany('transport-child-fare');
    $agent = Agent::create(['company_id' => $company->id, 'agent_number' => 'AGT-CHILD', 'name' => 'Child Fare Agent']);
    $visaVendor = VisaVendor::create(['company_id' => $company->id, 'vendor_number' => 'VIS-CHILD', 'name' => 'Visa Supplier', 'vendor_type' => VisaVendor::TYPE_VISA_PROVIDER]);
    $provider = VisaVendor::create([
        'company_id' => $company->id,
        'vendor_number' => 'TRN-CHILD',
        'name' => 'Bus Supplier',
        'vendor_type' => VisaVendor::TYPE_TRANSPORT_PROVIDER,
        'standard_bus_retail_amount' => 100,
        'standard_bus_cost_amount' => 80,
        'charge_child_fare' => false,
    ]);
    $group = VisaGroup::create([
        'company_id' => $company->id,
        'agent_id' => $agent->id,
        'vendor_id' => $visaVendor->id,
        'mandatory_transport_vendor_id' => $provider->id,
        'group_number' => 'UGR-CHILD',
        'name' => 'Child Fare Group',
        'status' => VisaGroup::STATUS_VISA_APPROVED,
        'travel_date' => '2026-08-01',
        'transport_required' => true,
        'transport_mode' => VisaGroup::TRANSPORT_STANDARD_BUS,
        'passenger_count' => 2,
    ]);
    Passenger::create(['company_id' => $company->id, 'visa_group_id' => $group->id, 'full_name' => 'Adult Passenger', 'imported_age' => 30, 'service_type' => Passenger::SERVICE_VISA_TRANSPORT]);
    Passenger::create(['company_id' => $company->id, 'visa_group_id' => $group->id, 'full_name' => 'Child Passenger', 'imported_age' => 8, 'service_type' => Passenger::SERVICE_VISA_TRANSPORT]);

    $pricing = app(UmrahCoreService::class)->standardBusPricingForGroup($group, $provider);

    expect($pricing['passenger_count'])->toBe(1)
        ->and($pricing['sale'])->toBe(100.0)
        ->and($pricing['cost'])->toBe(80.0);
});
