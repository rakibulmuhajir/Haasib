<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\Refund;
use App\Modules\Umrah\Models\TransportFare;
use App\Modules\Umrah\Models\TransportSector;
use App\Modules\Umrah\Models\TransportService;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

/**
 * The eight paths a person would walk by hand after 2026_08_24_000012 renamed
 * umrah.visa_vendors.vendor_type to service_type, driven over the real routes
 * so every FormRequest, every Rule::exists and every controller branch that
 * names the column is exercised.
 *
 * The one failure mode a PHP-side rename cannot produce on its own is a Vue
 * page still posting the old field name: the request would simply drop it and
 * fail its required rule. Paths 1 and 3 are where that would show up, and
 * VendorFieldNameTest.php checks the same thing statically.
 */
function serviceTypeCompany(): array
{
    $owner = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Service Type Test '.str()->random(6),
        'slug' => 'service-type-'.str()->lower(str()->random(10)),
        'owner_id' => $owner->id,
        'base_currency' => 'SAR',
        'industry_code' => 'umrah',
        'settings' => ['modules' => ['umrah' => true]],
    ]);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$owner->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($company);

    DB::table('auth.company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $owner->id,
        'role' => 'owner',
        'joined_at' => now(),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    app(CompanyContextService::class)->withContext(
        $company,
        fn () => app(CompanyContextService::class)->assignRole($owner, 'owner'),
    );

    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

    return [$company, $owner];
}

/** The payload the visa vendor form posts, with rates that satisfy the gt:0 rules. */
function serviceTypeVendorPayload(string $serviceType, string $name = 'Desk'): array
{
    return [
        'vendor_number' => null,
        'name' => $name,
        'service_type' => $serviceType,
        'adult_retail_amount' => 1200,
        'adult_cost_amount' => 1000,
        'child_retail_amount' => 900,
        'child_cost_amount' => 800,
    ];
}

function serviceTypeTransportPayload(string $name = 'Bus Co'): array
{
    return [
        'vendor_number' => null,
        'name' => $name,
        'is_company_owned' => false,
        'standard_bus_retail_amount' => 250,
        'standard_bus_cost_amount' => 180,
        'charge_child_fare' => false,
    ];
}

// Path 1 -- create a visa vendor as Government and get a Government back.
it('stores the service type the vendor form posted, not the default', function () {
    [$company, $owner] = serviceTypeCompany();

    $this->actingAs($owner)
        ->post("/{$company->slug}/umrah/vendors", serviceTypeVendorPayload(VisaVendor::SERVICE_GOVERNMENT, 'Consulate Desk'))
        ->assertSessionHasNoErrors();

    $vendor = VisaVendor::where('company_id', $company->id)->firstOrFail();

    expect($vendor->service_type)->toBe(VisaVendor::SERVICE_GOVERNMENT)
        ->and($vendor->name)->toBe('Consulate Desk')
        // First vendor on the company, so it also became the default -- proving
        // the branch that reads service_type before deciding that ran.
        ->and($vendor->is_default)->toBeTrue();
});

it('refuses a vendor whose service type is missing, rather than defaulting it', function () {
    [$company, $owner] = serviceTypeCompany();
    $payload = serviceTypeVendorPayload(VisaVendor::SERVICE_GOVERNMENT);
    unset($payload['service_type']);

    $this->actingAs($owner)
        ->post("/{$company->slug}/umrah/vendors", $payload)
        ->assertSessionHasErrors('service_type');

    expect(VisaVendor::where('company_id', $company->id)->count())->toBe(0);
});

it('refuses a vendor sent under the old vendor_type field name', function () {
    [$company, $owner] = serviceTypeCompany();
    $payload = serviceTypeVendorPayload(VisaVendor::SERVICE_GOVERNMENT);
    $payload['vendor_type'] = $payload['service_type'];
    unset($payload['service_type']);

    $this->actingAs($owner)
        ->post("/{$company->slug}/umrah/vendors", $payload)
        ->assertSessionHasErrors('service_type');
});

// Path 2 -- edit it to Visa provider and have the change stick.
it('keeps an edited service type after the record is re-read', function () {
    [$company, $owner] = serviceTypeCompany();
    $this->actingAs($owner)->post("/{$company->slug}/umrah/vendors", serviceTypeVendorPayload(VisaVendor::SERVICE_GOVERNMENT));
    $vendor = VisaVendor::where('company_id', $company->id)->firstOrFail();

    $this->actingAs($owner)
        ->put("/{$company->slug}/umrah/vendors/{$vendor->id}", [
            ...serviceTypeVendorPayload(VisaVendor::SERVICE_VISA_PROVIDER, 'Consulate Desk'),
            'vendor_number' => $vendor->vendor_number,
        ])
        ->assertSessionHasNoErrors();

    expect($vendor->fresh()->service_type)->toBe(VisaVendor::SERVICE_VISA_PROVIDER);
});

// Path 3 -- a transport provider lands in the transport register, not the visa one.
it('files a transport provider under transport and keeps it out of the visa register', function () {
    [$company, $owner] = serviceTypeCompany();

    $this->actingAs($owner)
        ->post("/{$company->slug}/umrah/transport-providers", serviceTypeTransportPayload('Haramain Coaches'))
        ->assertSessionHasNoErrors();

    $provider = VisaVendor::where('company_id', $company->id)->firstOrFail();
    expect($provider->service_type)->toBe(VisaVendor::SERVICE_TRANSPORT_PROVIDER)
        // A transport provider is never the default visa vendor, however empty
        // the visa register is.
        ->and($provider->is_default)->toBeFalse();

    $this->actingAs($owner)
        ->get("/{$company->slug}/umrah/vendors")
        ->assertInertia(fn ($page) => $page->where('vendors.total', 0));

    $this->actingAs($owner)
        ->get("/{$company->slug}/umrah/transport-providers")
        ->assertInertia(fn ($page) => $page->where('providers.total', 1));
});

it('offers only transport providers to the transport fare picker', function () {
    [$company, $owner] = serviceTypeCompany();
    $this->actingAs($owner)->post("/{$company->slug}/umrah/vendors", serviceTypeVendorPayload(VisaVendor::SERVICE_VISA_PROVIDER, 'Visa Desk'));
    $this->actingAs($owner)->post("/{$company->slug}/umrah/transport-providers", serviceTypeTransportPayload('Haramain Coaches'));

    $this->actingAs($owner)
        ->get("/{$company->slug}/umrah/settings/transport-services")
        ->assertInertia(fn ($page) => $page->where('transportVendors', fn ($vendors) => count($vendors) === 1));
});

// Path 4 -- a group takes a visa vendor and a mandatory transport vendor together.
it('accepts a group carrying both a visa vendor and a mandatory transport vendor', function () {
    [$company, $owner] = serviceTypeCompany();
    $this->actingAs($owner)->post("/{$company->slug}/umrah/vendors", serviceTypeVendorPayload(VisaVendor::SERVICE_VISA_PROVIDER, 'Visa Desk'));
    $this->actingAs($owner)->post("/{$company->slug}/umrah/transport-providers", serviceTypeTransportPayload('Haramain Coaches'));

    $visaVendor = VisaVendor::where('company_id', $company->id)->where('service_type', VisaVendor::SERVICE_VISA_PROVIDER)->firstOrFail();
    $transportVendor = VisaVendor::where('company_id', $company->id)->where('service_type', VisaVendor::SERVICE_TRANSPORT_PROVIDER)->firstOrFail();
    $agent = Agent::create(['company_id' => $company->id, 'agent_number' => 'AGT-ST', 'name' => 'Group Agent', 'is_active' => true]);

    $this->actingAs($owner)
        ->post("/{$company->slug}/umrah/groups", [
            'group_number' => null,
            'name' => 'Both Parties Group',
            'agent_id' => $agent->id,
            'vendor_id' => $visaVendor->id,
            'mandatory_transport_vendor_id' => $transportVendor->id,
            'transport_mode' => VisaGroup::TRANSPORT_STANDARD_BUS,
            'transport_required' => true,
            'includes_visa' => true,
        ])
        ->assertSessionHasNoErrors();

    $group = VisaGroup::where('company_id', $company->id)->firstOrFail();
    expect($group->vendor_id)->toBe($visaVendor->id)
        ->and($group->mandatory_transport_vendor_id)->toBe($transportVendor->id);
});

it('refuses a transport provider in the group visa vendor slot', function () {
    [$company, $owner] = serviceTypeCompany();
    $this->actingAs($owner)->post("/{$company->slug}/umrah/transport-providers", serviceTypeTransportPayload('Haramain Coaches'));
    $transportVendor = VisaVendor::where('company_id', $company->id)->firstOrFail();
    $agent = Agent::create(['company_id' => $company->id, 'agent_number' => 'AGT-ST2', 'name' => 'Group Agent', 'is_active' => true]);

    $this->actingAs($owner)
        ->post("/{$company->slug}/umrah/groups", [
            'group_number' => null,
            'agent_id' => $agent->id,
            'vendor_id' => $transportVendor->id,
            'mandatory_transport_vendor_id' => $transportVendor->id,
            'transport_mode' => VisaGroup::TRANSPORT_STANDARD_BUS,
            'transport_required' => true,
            'includes_visa' => true,
        ])
        ->assertSessionHasErrors('vendor_id');
});

// Path 5 -- a fare against a transport provider is accepted; against a visa desk it is not.
it('takes a transport fare against a transport provider and refuses one against a visa desk', function () {
    [$company, $owner] = serviceTypeCompany();
    $this->actingAs($owner)->post("/{$company->slug}/umrah/vendors", serviceTypeVendorPayload(VisaVendor::SERVICE_VISA_PROVIDER, 'Visa Desk'));
    $this->actingAs($owner)->post("/{$company->slug}/umrah/transport-providers", serviceTypeTransportPayload('Haramain Coaches'));

    $visaVendor = VisaVendor::where('company_id', $company->id)->where('service_type', VisaVendor::SERVICE_VISA_PROVIDER)->firstOrFail();
    $transportVendor = VisaVendor::where('company_id', $company->id)->where('service_type', VisaVendor::SERVICE_TRANSPORT_PROVIDER)->firstOrFail();

    $service = TransportService::create([
        'company_id' => $company->id, 'name' => 'Coach 1', 'vehicle_type' => 'Bus', 'is_active' => true,
    ]);
    $sector = TransportSector::create([
        'company_id' => $company->id, 'code' => 'JED-MAK-ST', 'name' => 'Jeddah to Makkah',
        'origin' => 'Jeddah', 'destination' => 'Makkah', 'is_active' => true,
    ]);

    $fare = [
        'name' => 'Airport transfer',
        'transport_service_id' => $service->id,
        'transport_sector_id' => $sector->id,
        'charging_basis' => TransportFare::BASIS_PER_VEHICLE,
        'sale_amount' => 400,
        'cost_amount' => 300,
    ];

    // assertRedirect as well as assertSessionHasNoErrors: a 500 carries no
    // validation errors either, which is how the prohibited_with rule that
    // never existed sat in this request unnoticed.
    $this->actingAs($owner)
        ->post("/{$company->slug}/umrah/settings/transport-fares", [...$fare, 'transport_vendor_id' => $transportVendor->id])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($owner)
        ->post("/{$company->slug}/umrah/settings/transport-fares", [...$fare, 'transport_vendor_id' => $visaVendor->id])
        ->assertSessionHasErrors('transport_vendor_id');

    expect(TransportFare::where('company_id', $company->id)->count())->toBe(1);
});

it('refuses a transport fare that names both a sector and a journey package', function () {
    [$company, $owner] = serviceTypeCompany();
    $this->actingAs($owner)->post("/{$company->slug}/umrah/transport-providers", serviceTypeTransportPayload('Haramain Coaches'));
    $transportVendor = VisaVendor::where('company_id', $company->id)->firstOrFail();

    $service = TransportService::create([
        'company_id' => $company->id, 'name' => 'Coach 2', 'vehicle_type' => 'Bus', 'is_active' => true,
    ]);
    $sector = TransportSector::create([
        'company_id' => $company->id, 'code' => 'JED-MAD-ST', 'name' => 'Jeddah to Madinah',
        'origin' => 'Jeddah', 'destination' => 'Madinah', 'is_active' => true,
    ]);
    $package = app(App\Modules\Umrah\Services\TransportCatalogService::class)
        ->createPackage($company->id, ['name' => 'Full Journey', 'sector_ids' => [$sector->id]]);

    $this->actingAs($owner)
        ->post("/{$company->slug}/umrah/settings/transport-fares", [
            'name' => 'Ambiguous fare',
            'transport_vendor_id' => $transportVendor->id,
            'transport_service_id' => $service->id,
            'transport_sector_id' => $sector->id,
            'transport_package_id' => $package->id,
            'charging_basis' => TransportFare::BASIS_PER_VEHICLE,
            'sale_amount' => 400,
            'cost_amount' => 300,
        ])
        ->assertSessionHasErrors('transport_package_id');

    expect(TransportFare::where('company_id', $company->id)->count())->toBe(0);
});

// Path 6 -- the transport_vendor_id field on a payment takes only transport providers.
it('refuses a visa vendor on a payment transport vendor field', function () {
    [$company, $owner] = serviceTypeCompany();
    $this->actingAs($owner)->post("/{$company->slug}/umrah/vendors", serviceTypeVendorPayload(VisaVendor::SERVICE_VISA_PROVIDER, 'Visa Desk'));
    $this->actingAs($owner)->post("/{$company->slug}/umrah/transport-providers", serviceTypeTransportPayload('Haramain Coaches'));

    $visaVendor = VisaVendor::where('company_id', $company->id)->where('service_type', VisaVendor::SERVICE_VISA_PROVIDER)->firstOrFail();
    $transportVendor = VisaVendor::where('company_id', $company->id)->where('service_type', VisaVendor::SERVICE_TRANSPORT_PROVIDER)->firstOrFail();

    $payment = [
        'payment_date' => now()->toDateString(),
        'direction' => App\Modules\Umrah\Models\GroupPayment::DIRECTION_SENT,
        'amount' => 500,
        'currency' => 'SAR',
    ];

    $this->actingAs($owner)
        ->post("/{$company->slug}/umrah/payments", [...$payment, 'transport_vendor_id' => $visaVendor->id])
        ->assertSessionHasErrors('transport_vendor_id');

    $this->actingAs($owner)
        ->post("/{$company->slug}/umrah/payments", [...$payment, 'transport_vendor_id' => $transportVendor->id])
        ->assertSessionDoesntHaveErrors('transport_vendor_id');
});

// Path 7 -- each refund party type accepts its own vendor and refuses the other.
it('holds each refund party type to its own kind of vendor', function () {
    [$company, $owner] = serviceTypeCompany();
    $this->actingAs($owner)->post("/{$company->slug}/umrah/vendors", serviceTypeVendorPayload(VisaVendor::SERVICE_VISA_PROVIDER, 'Visa Desk'));
    $this->actingAs($owner)->post("/{$company->slug}/umrah/transport-providers", serviceTypeTransportPayload('Haramain Coaches'));

    $visaVendor = VisaVendor::where('company_id', $company->id)->where('service_type', VisaVendor::SERVICE_VISA_PROVIDER)->firstOrFail();
    $transportVendor = VisaVendor::where('company_id', $company->id)->where('service_type', VisaVendor::SERVICE_TRANSPORT_PROVIDER)->firstOrFail();

    $refund = fn (string $partyType, string $partyId, string $service): array => [
        'party_type' => $partyType,
        'party_id' => $partyId,
        'service' => $service,
        'refund_number' => null,
        'amount' => 100,
        'currency' => 'SAR',
        'reason' => 'Service was paid for and never delivered.',
    ];

    $this->actingAs($owner)
        ->post("/{$company->slug}/umrah/refunds", $refund(Refund::PARTY_VISA_VENDOR, $visaVendor->id, Refund::SERVICE_VISA))
        ->assertSessionHasNoErrors();

    $this->actingAs($owner)
        ->post("/{$company->slug}/umrah/refunds", $refund(Refund::PARTY_TRANSPORT_VENDOR, $transportVendor->id, Refund::SERVICE_TRANSPORT))
        ->assertSessionHasNoErrors();

    // And crossed over, each is refused on the other's party type.
    $this->actingAs($owner)
        ->post("/{$company->slug}/umrah/refunds", $refund(Refund::PARTY_VISA_VENDOR, $transportVendor->id, Refund::SERVICE_VISA))
        ->assertSessionHasErrors('party_id');

    $this->actingAs($owner)
        ->post("/{$company->slug}/umrah/refunds", $refund(Refund::PARTY_TRANSPORT_VENDOR, $visaVendor->id, Refund::SERVICE_TRANSPORT))
        ->assertSessionHasErrors('party_id');

    expect(Refund::where('company_id', $company->id)->count())->toBe(2);
});

// Path 8 -- the dashboard widget reads a transport provider as transport.
it('labels a vendor balance by what the vendor supplies', function () {
    [$company, $owner] = serviceTypeCompany();
    $this->actingAs($owner)->post("/{$company->slug}/umrah/vendors", serviceTypeVendorPayload(VisaVendor::SERVICE_VISA_PROVIDER, 'Visa Desk'));
    $this->actingAs($owner)->post("/{$company->slug}/umrah/transport-providers", serviceTypeTransportPayload('Haramain Coaches'));

    VisaVendor::where('company_id', $company->id)->update(['balance' => 5000]);

    $widget = app(App\Modules\Umrah\Dashboard\Widgets\VendorBalancesWidget::class)->resolve($company, $owner, []);
    $kinds = collect($widget['rows'])->pluck('kind', 'name');

    expect($kinds['Haramain Coaches'])->toBe('transport')
        ->and($kinds['Visa Desk'])->toBe('visa');
});
