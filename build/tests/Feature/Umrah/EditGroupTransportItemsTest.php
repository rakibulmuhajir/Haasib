<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\GroupTransportItem;
use App\Modules\Umrah\Models\TransportFare;
use App\Modules\Umrah\Models\TransportSector;
use App\Modules\Umrah\Models\TransportService;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Services\UmrahCoreService;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

/*
 * A specialized group's vehicles used to be collected on create and never
 * again: the only post-creation operation was removeGroupTransport(), which
 * deletes every one of them. A bus entered with the wrong vehicle count, the
 * wrong terminal or the wrong supplier could not be corrected -- the group had
 * to be cancelled and rebuilt, taking its posted sale and cost with it.
 *
 * These tests drive the correction through the real edit route, because that
 * is where the money has to stay consistent: the group's transport_amount and
 * transport_cost_amount move, recalculateGroup() folds them into the
 * receivable, and postGroupFinancialAdjustment() posts the difference as UGA
 * and UGC deltas rather than restating the original sale.
 *
 * assertRedirect() rides along with every assertSessionHasNoErrors(), because
 * the latter passes on a 500 -- that is exactly how a dead transport-fare
 * route stayed hidden until 85d9a586.
 */
function transportEditCompany(): array
{
    $owner = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Transport Edit Test '.str()->random(8),
        'slug' => 'transport-edit-test-'.str()->lower(str()->random(10)),
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

    foreach ([
        ['1100', 'Accounts Receivable', 'asset', 'accounts_receivable', 'debit', 'SAR'],
        ['2100', 'Accounts Payable', 'liability', 'accounts_payable', 'credit', 'SAR'],
        ['4100', 'Revenue', 'revenue', 'operating_revenue', 'credit', null],
        ['5100', 'Cost of Sales', 'cogs', 'direct_cost', 'debit', null],
    ] as [$code, $name, $type, $subtype, $normal, $currency]) {
        Account::create([
            'company_id' => $company->id,
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'subtype' => $subtype,
            'normal_balance' => $normal,
            'currency' => $currency,
            'is_active' => true,
        ]);
    }

    return [$company, $owner];
}

/**
 * One transport supplier with one per-vehicle fare. The service states no
 * pax_capacity, so withSeatedVehicleCounts() has no seat count to raise the
 * vehicle count against and each test's quantity survives as written.
 */
function transportEditFare(Company $company, string $suffix, float $sale, float $cost): TransportFare
{
    $vendor = VisaVendor::create([
        'company_id' => $company->id,
        'vendor_number' => "TRN-TE-{$suffix}",
        'name' => "Transport Edit Provider {$suffix}",
        'service_type' => VisaVendor::SERVICE_TRANSPORT_PROVIDER,
        'is_active' => true,
    ]);

    $service = TransportService::create([
        'company_id' => $company->id,
        'name' => "Transport Edit Coach {$suffix}",
        'vehicle_type' => 'Bus',
        'pax_capacity' => null,
        'is_active' => true,
    ]);

    $sector = TransportSector::create([
        'company_id' => $company->id,
        'code' => "TE-{$suffix}",
        'name' => "Transport Edit Sector {$suffix}",
        'origin' => 'Jeddah',
        'destination' => 'Makkah',
        'is_active' => true,
    ]);

    return TransportFare::create([
        'company_id' => $company->id,
        'transport_vendor_id' => $vendor->id,
        'transport_service_id' => $service->id,
        'transport_sector_id' => $sector->id,
        'name' => "Transport Edit Fare {$suffix}",
        'charging_basis' => TransportFare::BASIS_PER_VEHICLE,
        'sale_amount' => $sale,
        'cost_amount' => $cost,
        'is_active' => true,
    ]);
}

/**
 * A real posted group, built through createGroup() rather than assembled by
 * hand, so its sale and cost are already on the ledger and the edit under
 * test produces a genuine delta.
 *
 * The visa vendor carries no rates, which prices the visa at zero and leaves
 * transport as the only money on the group. includes_visa stays true so the
 * VISA_APPROVED status createGroup() assigns matches the kind check.
 */
function transportEditGroup(Company $company, array $items): VisaGroup
{
    $agent = Agent::create([
        'company_id' => $company->id,
        'agent_number' => 'AGT-'.strtoupper(str()->random(5)),
        'name' => 'Transport Edit Agent',
    ]);

    $vendor = VisaVendor::create([
        'company_id' => $company->id,
        'vendor_number' => 'VIS-'.strtoupper(str()->random(5)),
        'name' => 'Transport Edit Visa Vendor',
        'service_type' => VisaVendor::SERVICE_VISA_PROVIDER,
        'is_active' => true,
        'is_default' => true,
    ]);

    return app(UmrahCoreService::class)->createGroup($company->id, [
        'agent_id' => $agent->id,
        'vendor_id' => $vendor->id,
        'name' => 'Transport Edit Group',
        'includes_visa' => true,
        'transport_mode' => VisaGroup::TRANSPORT_SPECIALIZED,
        'travel_date' => now()->addMonth()->toDateString(),
        'passenger_count' => 4,
        'transport_items' => $items,
    ]);
}

function transportEditPayload(VisaGroup $group, array $items): array
{
    return [
        'name' => $group->name,
        'transport_mode' => VisaGroup::TRANSPORT_SPECIALIZED,
        'includes_visa' => true,
        'vendor_id' => $group->vendor_id,
        'transport_items' => $items,
    ];
}

function transportEditItem(TransportFare $fare, array $overrides = []): array
{
    return array_merge([
        'transport_fare_id' => $fare->id,
        'terminal' => 'standard',
        'quantity' => 1,
        'passenger_count' => 4,
    ], $overrides);
}

function adjustmentCount(VisaGroup $group, string $type): int
{
    return DB::table('acct.transactions')
        ->where('reference_id', $group->id)
        ->where('transaction_type', $type)
        ->count();
}

test('changing a vehicle count reprices the group and posts the difference', function () {
    [$company, $owner] = transportEditCompany();
    $fare = transportEditFare($company, 'ONE', 100, 80);
    $group = transportEditGroup($company, [transportEditItem($fare)]);

    expect((float) $group->transport_amount)->toBe(100.0)
        ->and((float) $group->transport_cost_amount)->toBe(80.0);

    $this->actingAs($owner)
        ->put("/{$company->slug}/umrah/groups/{$group->id}", transportEditPayload($group, [
            transportEditItem($fare, ['quantity' => 2]),
        ]))
        ->assertSessionHasNoErrors()->assertRedirect();

    $fresh = $group->fresh();
    expect((float) $fresh->transport_amount)->toBe(200.0)
        ->and((float) $fresh->transport_cost_amount)->toBe(160.0)
        ->and((float) $fresh->total_receivable)->toBe(200.0)
        ->and($fresh->transportItems()->count())->toBe(1)
        ->and((int) $fresh->transportItems()->first()->quantity)->toBe(2);

    // One save, one pair of adjustments -- the original sale and cost stay
    // posted at their first amounts and only the difference is restated.
    expect(adjustmentCount($fresh, 'umrah_group_sale_adjustment'))->toBe(1)
        ->and(adjustmentCount($fresh, 'umrah_group_cost_adjustment'))->toBe(1);
});

test('adding a second vehicle adds its sale and its cost', function () {
    [$company, $owner] = transportEditCompany();
    $first = transportEditFare($company, 'ADDA', 100, 80);
    $second = transportEditFare($company, 'ADDB', 250, 200);
    $group = transportEditGroup($company, [transportEditItem($first)]);

    $this->actingAs($owner)
        ->put("/{$company->slug}/umrah/groups/{$group->id}", transportEditPayload($group, [
            transportEditItem($first),
            transportEditItem($second),
        ]))
        ->assertSessionHasNoErrors()->assertRedirect();

    $fresh = $group->fresh();
    expect((float) $fresh->transport_amount)->toBe(350.0)
        ->and((float) $fresh->transport_cost_amount)->toBe(280.0)
        ->and($fresh->transportItems()->count())->toBe(2);
});

test('removing one of two vehicles reverses only that one', function () {
    [$company, $owner] = transportEditCompany();
    $keep = transportEditFare($company, 'KEEP', 100, 80);
    $drop = transportEditFare($company, 'DROP', 250, 200);
    $group = transportEditGroup($company, [transportEditItem($keep), transportEditItem($drop)]);

    expect((float) $group->transport_amount)->toBe(350.0);

    $this->actingAs($owner)
        ->put("/{$company->slug}/umrah/groups/{$group->id}", transportEditPayload($group, [
            transportEditItem($keep),
        ]))
        ->assertSessionHasNoErrors()->assertRedirect();

    $fresh = $group->fresh();
    expect((float) $fresh->transport_amount)->toBe(100.0)
        ->and((float) $fresh->transport_cost_amount)->toBe(80.0)
        ->and($fresh->transportItems()->count())->toBe(1)
        ->and($fresh->transportItems()->first()->transport_fare_id)->toBe($keep->id);

    // A save replaces the whole set rather than reconciling row by row, so
    // both original rows are soft-deleted and the kept one is written afresh.
    // Nothing outside the module holds a transport item's id, so the churn
    // costs nothing -- and what the group once charged for stays readable.
    expect(GroupTransportItem::onlyTrashed()->where('visa_group_id', $group->id)->count())->toBe(2)
        ->and(GroupTransportItem::onlyTrashed()->where('visa_group_id', $group->id)->where('transport_fare_id', $drop->id)->exists())->toBeTrue()
        ->and(GroupTransportItem::where('visa_group_id', $group->id)->where('transport_fare_id', $drop->id)->exists())->toBeFalse();
});

test('moving a vehicle to another supplier moves the payable with it', function () {
    [$company, $owner] = transportEditCompany();
    $from = transportEditFare($company, 'FROM', 100, 80);
    $to = transportEditFare($company, 'TOO', 100, 80);
    $group = transportEditGroup($company, [transportEditItem($from)]);

    expect((float) $from->transportVendor->fresh()->balance)->toBe(80.0)
        ->and((float) $to->transportVendor->fresh()->balance)->toBe(0.0);

    $this->actingAs($owner)
        ->put("/{$company->slug}/umrah/groups/{$group->id}", transportEditPayload($group, [
            transportEditItem($to),
        ]))
        ->assertSessionHasNoErrors()->assertRedirect();

    // The group's own money is unchanged -- both fares cost the same. What
    // moved is which supplier is owed, which only surfaces if the update
    // recalculates the supplier the work left as well as the one it went to.
    expect((float) $group->fresh()->transport_cost_amount)->toBe(80.0)
        ->and((float) $from->transportVendor->fresh()->balance)->toBe(0.0)
        ->and((float) $to->transportVendor->fresh()->balance)->toBe(80.0);
});

test('a specialized group cannot be saved with no vehicles at all', function () {
    [$company, $owner] = transportEditCompany();
    $fare = transportEditFare($company, 'EMPTY', 100, 80);
    $group = transportEditGroup($company, [transportEditItem($fare)]);

    // Emptying the list is not how transport is removed -- self-arranged
    // transport is, and it resets the passengers too. An empty specialized
    // group would sell transport it has no vehicles for.
    $this->actingAs($owner)
        ->put("/{$company->slug}/umrah/groups/{$group->id}", transportEditPayload($group, []))
        ->assertSessionHasErrors('transport_items');

    $fresh = $group->fresh();
    expect((float) $fresh->transport_amount)->toBe(100.0)
        ->and((float) $fresh->transport_cost_amount)->toBe(80.0)
        ->and($fresh->transportItems()->count())->toBe(1);
});

test('an unrelated edit leaves the vehicles and the money alone', function () {
    [$company, $owner] = transportEditCompany();
    $fare = transportEditFare($company, 'QUIET', 100, 80);
    $group = transportEditGroup($company, [transportEditItem($fare)]);
    $itemId = $group->transportItems()->first()->id;

    // A payload with no transport_items at all -- the shape every existing
    // caller of this route sends. Renaming a group must not silently delete
    // its vehicles, so an absent list means "unchanged", not "none".
    $this->actingAs($owner)
        ->put("/{$company->slug}/umrah/groups/{$group->id}", [
            'name' => 'Renamed Transport Edit Group',
            'transport_mode' => VisaGroup::TRANSPORT_SPECIALIZED,
            'includes_visa' => true,
            'vendor_id' => $group->vendor_id,
        ])
        ->assertSessionHasNoErrors()->assertRedirect();

    $fresh = $group->fresh();
    expect($fresh->name)->toBe('Renamed Transport Edit Group')
        ->and((float) $fresh->transport_amount)->toBe(100.0)
        ->and($fresh->transportItems()->count())->toBe(1)
        ->and($fresh->transportItems()->first()->id)->toBe($itemId);
});
