<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\GroupPayment;
use App\Modules\Umrah\Models\GroupTransportItem;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Services\UmrahCoreService;
use Illuminate\Support\Facades\DB;

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

test('group vendors resolve from the company default and its transport mapping', function () {
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
        'mandatory_transport_vendor_id' => $transportVendor->id,
    ]);

    $resolved = app(UmrahCoreService::class)->resolveGroupVendors($company->id, [
        'vendor_id' => '11111111-1111-1111-1111-111111111111',
        'mandatory_transport_vendor_id' => '22222222-2222-2222-2222-222222222222',
        'transport_mode' => VisaGroup::TRANSPORT_STANDARD_BUS,
    ], true);

    expect($resolved['vendor_id'])->toBe($visaVendor->id)
        ->and($resolved['mandatory_transport_vendor_id'])->toBe($transportVendor->id);

    $visaVendor->update(['is_default' => false]);
    $combinedVendor = VisaVendor::create([
        'company_id' => $company->id,
        'vendor_number' => 'VIS-COMBINED',
        'name' => 'Visa and Transport',
        'vendor_type' => VisaVendor::TYPE_VISA_PROVIDER,
        'is_default' => true,
        'provides_mandatory_transport' => true,
    ]);
    $combined = app(UmrahCoreService::class)->resolveGroupVendors($company->id, [
        'transport_mode' => VisaGroup::TRANSPORT_STANDARD_BUS,
    ], true);

    expect($combined['vendor_id'])->toBe($combinedVendor->id)
        ->and($combined['mandatory_transport_vendor_id'])->toBe($combinedVendor->id);
});
