<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Umrah\Http\Requests\UpdateVisaGroupRequest;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/*
 * VisaGroupController::update() re-derives every passenger's service_type
 * when a group's includes_visa answer actually changes -- leaving it behind
 * is exactly the group/passenger mismatch GroupAccountingService's docblocks
 * describe as a real historical billing bug. These tests exercise that
 * re-derivation through the real HTTP route, plus the accompanying
 * visa_sale_amount/visa_cost_amount/vendor_id zeroing, and confirm the two
 * validation rules the fix must not disturb: "must sell something" and
 * "cannot switch a group to specialized transport after creation."
 */
function serviceChoiceCompany(): array
{
    $owner = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Service Choice Test '.str()->random(8),
        'slug' => 'service-choice-test-'.str()->lower(str()->random(10)),
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

function serviceChoiceGroup(Company $company, string $transportMode, bool $includesVisa): VisaGroup
{
    $agent = Agent::create([
        'company_id' => $company->id,
        'agent_number' => 'AGT-'.strtoupper(str()->random(5)),
        'name' => 'Service Choice Agent',
    ]);

    // is_default lets resolveGroupVendors() fall back to this vendor when a
    // flip-back-to-visa update sends no vendor_id of its own (the record's
    // own vendor_id was cleared by the earlier flip-to-false, exactly as
    // production groups without a chosen default would behave).
    $vendor = VisaVendor::create([
        'company_id' => $company->id,
        'vendor_number' => 'VIS-'.strtoupper(str()->random(5)),
        'name' => 'Service Choice Visa Vendor',
        'service_type' => VisaVendor::SERVICE_VISA_PROVIDER,
        'is_active' => true,
        'is_default' => true,
    ]);

    $group = VisaGroup::create([
        'company_id' => $company->id,
        'agent_id' => $agent->id,
        'vendor_id' => $includesVisa ? $vendor->id : null,
        'group_number' => 'UGR-'.strtoupper(str()->random(5)),
        'name' => 'Service Choice Group',
        // 'draft' is the one status visa_groups_status_matches_kind_check allows
        // regardless of includes_visa, so flipping the answer in either
        // direction never trips it -- this test cares about service_type
        // re-derivation, not the visa-application status lifecycle.
        'status' => VisaGroup::STATUS_DRAFT,
        'travel_date' => now()->addMonth()->toDateString(),
        'includes_visa' => $includesVisa,
        'transport_mode' => $transportMode,
        'transport_required' => $transportMode !== VisaGroup::TRANSPORT_NONE,
        'visa_sale_amount' => $includesVisa ? 300 : 0,
        'visa_cost_amount' => $includesVisa ? 200 : 0,
    ]);

    // Financial totals default to 0 on a freshly-created group; without this,
    // postGroupFinancialAdjustment() would see a fake delta from 0 to the
    // real total the very first time recalculateGroup() runs during the
    // update under test, which has nothing to do with what these tests are
    // checking and unbalances the GL posting.
    return app(App\Modules\Umrah\Services\UmrahCoreService::class)->recalculateGroup($group->fresh());
}

function serviceChoicePassenger(VisaGroup $group, string $serviceType): Passenger
{
    return Passenger::create([
        'company_id' => $group->company_id,
        'visa_group_id' => $group->id,
        'full_name' => 'Service Choice Passenger',
        'passport_number' => 'P'.strtoupper(str()->random(8)),
        'nationality' => 'PK',
        'service_type' => $serviceType,
    ]);
}

function serviceChoicePayload(VisaGroup $group, array $overrides = []): array
{
    return array_merge([
        'name' => $group->name,
        'transport_mode' => $group->transport_mode,
        'includes_visa' => $group->includes_visa,
    ], $overrides);
}

test('flipping a group to transport-only re-derives its passengers', function () {
    [$company, $owner] = serviceChoiceCompany();
    $group = serviceChoiceGroup($company, VisaGroup::TRANSPORT_SPECIALIZED, true);
    $passengerOne = serviceChoicePassenger($group, Passenger::SERVICE_VISA_TRANSPORT);
    $passengerTwo = serviceChoicePassenger($group, Passenger::SERVICE_VISA_TRANSPORT);

    $this->actingAs($owner)
        ->put("/{$company->slug}/umrah/groups/{$group->id}", serviceChoicePayload($group, [
            'includes_visa' => false,
        ]))
        ->assertSessionHasNoErrors()->assertRedirect();

    expect($passengerOne->fresh()->service_type)->toBe(Passenger::SERVICE_TRANSPORT_ONLY)
        ->and($passengerTwo->fresh()->service_type)->toBe(Passenger::SERVICE_TRANSPORT_ONLY);

    $fresh = $group->fresh();
    expect((float) $fresh->visa_sale_amount)->toBe(0.0)
        ->and((float) $fresh->visa_cost_amount)->toBe(0.0)
        ->and($fresh->vendor_id)->toBeNull();
});

test('flipping a group back to visa re-derives its passengers again', function () {
    [$company, $owner] = serviceChoiceCompany();
    $group = serviceChoiceGroup($company, VisaGroup::TRANSPORT_SPECIALIZED, false);
    $passenger = serviceChoicePassenger($group, Passenger::SERVICE_TRANSPORT_ONLY);

    $this->actingAs($owner)
        ->put("/{$company->slug}/umrah/groups/{$group->id}", serviceChoicePayload($group, [
            'includes_visa' => true,
        ]))
        ->assertSessionHasNoErrors()->assertRedirect();

    expect($passenger->fresh()->service_type)->toBe(Passenger::SERVICE_VISA_TRANSPORT);
});

test('an unchanged includes_visa answer leaves passengers alone', function () {
    [$company, $owner] = serviceChoiceCompany();
    $group = serviceChoiceGroup($company, VisaGroup::TRANSPORT_SPECIALIZED, true);
    // Deliberately mismatched, as if left over from before this feature existed --
    // proves the controller only re-derives service_type when includes_visa
    // actually changes, rather than unconditionally overwriting it.
    $passenger = serviceChoicePassenger($group, Passenger::SERVICE_TRANSPORT_ONLY);

    $this->actingAs($owner)
        ->put("/{$company->slug}/umrah/groups/{$group->id}", serviceChoicePayload($group, [
            'name' => 'Renamed Service Choice Group',
        ]))
        ->assertSessionHasNoErrors()->assertRedirect();

    expect($passenger->fresh()->service_type)->toBe(Passenger::SERVICE_TRANSPORT_ONLY)
        ->and($group->fresh()->name)->toBe('Renamed Service Choice Group');
});

test('the must-sell-something rule still rejects no visa and no transport through the edit path', function () {
    [$company, $owner] = serviceChoiceCompany();
    $group = serviceChoiceGroup($company, VisaGroup::TRANSPORT_STANDARD_BUS, true);

    $this->actingAs($owner)
        ->put("/{$company->slug}/umrah/groups/{$group->id}", serviceChoicePayload($group, [
            'includes_visa' => false,
            'transport_mode' => VisaGroup::TRANSPORT_NONE,
        ]))
        ->assertSessionHasErrors('transport_mode');

    expect($group->fresh()->includes_visa)->toBeTrue();
});

test('a non-specialized group still cannot be switched to specialized transport', function () {
    [$company, $owner] = serviceChoiceCompany();
    $group = serviceChoiceGroup($company, VisaGroup::TRANSPORT_STANDARD_BUS, true);

    $this->actingAs($owner)
        ->put("/{$company->slug}/umrah/groups/{$group->id}", serviceChoicePayload($group, [
            'transport_mode' => VisaGroup::TRANSPORT_SPECIALIZED,
        ]))
        ->assertSessionHasErrors('transport_mode');

    expect($group->fresh()->transport_mode)->toBe(VisaGroup::TRANSPORT_STANDARD_BUS);
});

test('UpdateVisaGroupRequest rules refuse specialized transport for a non-specialized group directly', function () {
    [$company] = serviceChoiceCompany();
    $group = serviceChoiceGroup($company, VisaGroup::TRANSPORT_STANDARD_BUS, true);

    $route = new Illuminate\Routing\Route('PUT', 'groups/{group}', []);
    $route->bind(new \Illuminate\Http\Request);
    $route->setParameter('group', $group->id);

    $request = UpdateVisaGroupRequest::create("/{$company->slug}/umrah/groups/{$group->id}", 'PUT', [
        'name' => $group->name,
        'transport_mode' => VisaGroup::TRANSPORT_SPECIALIZED,
        'includes_visa' => true,
    ]);
    $request->setRouteResolver(fn () => $route);

    $validator = Validator::make($request->all(), $request->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('transport_mode'))->toBeTrue();
});
