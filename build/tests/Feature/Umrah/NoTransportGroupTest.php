<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\GroupTransportItem;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Services\UmrahCoreService;
use Illuminate\Support\Facades\DB;

test('removing group transport clears stale persistence and recalculates totals', function () {
    $user = User::factory()->create();
    $company = Company::create(['name' => 'No Transport Test', 'slug' => 'no-transport-test', 'owner_id' => $user->id, 'base_currency' => 'SAR']);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    $agent = Agent::create(['company_id' => $company->id, 'agent_number' => 'AGT-NT', 'name' => 'No Transport Agent']);
    $visaVendor = VisaVendor::create(['company_id' => $company->id, 'vendor_number' => 'VIS-NT', 'name' => 'Visa Vendor', 'service_type' => VisaVendor::SERVICE_VISA_PROVIDER]);
    $transportVendor = VisaVendor::create(['company_id' => $company->id, 'vendor_number' => 'TRN-NT', 'name' => 'Transport Vendor', 'service_type' => VisaVendor::SERVICE_TRANSPORT_PROVIDER]);
    $group = VisaGroup::create([
        'company_id' => $company->id, 'agent_id' => $agent->id, 'vendor_id' => $visaVendor->id,
        'mandatory_transport_vendor_id' => $transportVendor->id, 'group_number' => 'UGR-NT', 'name' => 'Visa only group',
        'status' => VisaGroup::STATUS_VISA_APPROVED, 'transport_required' => true, 'transport_mode' => VisaGroup::TRANSPORT_SPECIALIZED,
        'visa_sale_amount' => 300, 'visa_cost_amount' => 200, 'transport_amount' => 100, 'transport_cost_amount' => 80,
    ]);
    $passenger = Passenger::create([
        'company_id' => $company->id, 'visa_group_id' => $group->id, 'full_name' => 'Visa Passenger',
        'service_type' => Passenger::SERVICE_TRANSPORT_ONLY, 'transport_charge_amount' => 100,
    ]);
    GroupTransportItem::create([
        'company_id' => $company->id, 'visa_group_id' => $group->id, 'transport_vendor_id' => $transportVendor->id,
        'description' => 'Stale fare', 'charging_basis' => 'flat_group', 'quantity' => 1,
        'unit_sale_amount' => 100, 'unit_cost_amount' => 80, 'total_sale_amount' => 100, 'total_cost_amount' => 80,
    ]);

    $updated = app(UmrahCoreService::class)->removeGroupTransport($group);

    expect($updated->transport_mode)->toBe(VisaGroup::TRANSPORT_NONE)
        ->and($updated->transport_required)->toBeFalse()
        ->and($updated->mandatory_transport_vendor_id)->toBeNull()
        ->and((float) $updated->transport_amount)->toBe(0.0)
        ->and((float) $updated->transport_cost_amount)->toBe(0.0)
        ->and((float) $updated->total_receivable)->toBe(300.0)
        ->and((float) $updated->profit)->toBe(100.0)
        ->and($group->transportItems()->count())->toBe(0)
        ->and($passenger->fresh()->service_type)->toBe(Passenger::SERVICE_VISA_TRANSPORT)
        ->and((float) $passenger->fresh()->transport_charge_amount)->toBe(0.0);
});
