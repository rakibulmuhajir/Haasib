<?php

use App\Facades\CompanyContext;
use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Umrah\Http\Controllers\VoucherController;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\CommercialRate;
use App\Modules\Umrah\Models\Hotel;
use App\Modules\Umrah\Models\HotelRoomRate;
use App\Modules\Umrah\Models\HotelVendor;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\PricingCategory;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Services\CommercialRateResolver;
use App\Modules\Umrah\Services\UmrahCoreService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require_once __DIR__.'/TicketingFixtures.php';

function commercialPricingAddMember(Company $company, User $user, string $role): void
{
    addCompanyMemberRow($company, $user, $role);
    CompanyContext::withContext($company, fn () => CompanyContext::assignRole($user, $role));
}

function commercialPricingFixture(): object
{
    Carbon::setTestNow('2026-09-07 12:00:00');
    $base = ticketingCompany([
        'name' => 'Commercial Pricing Test',
        'industry_code' => 'umrah',
        'settings' => ['modules' => ['umrah' => true]],
        'base_currency' => 'SAR',
    ]);
    $company = $base->company;
    $owner = $base->user;
    $accountant = User::factory()->withoutTwoFactor()->create();
    $outsider = User::factory()->withoutTwoFactor()->create();

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$owner->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($company);
    commercialPricingAddMember($company, $owner, 'owner');
    commercialPricingAddMember($company, $accountant, 'accountant');
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    CompanyContext::setContext($company);

    $category = PricingCategory::create([
        'company_id' => $company->id,
        'name' => 'Preferred',
        'description' => 'Preferred agent terms',
        'is_active' => true,
    ]);
    $agent = Agent::create([
        'company_id' => $company->id,
        'agent_number' => 'AGT-PRICE',
        'name' => 'Priced Agent',
        'pricing_category_id' => $category->id,
        'country' => 'Pakistan',
    ]);
    $otherAgent = Agent::create([
        'company_id' => $company->id,
        'agent_number' => 'AGT-NORMAL',
        'name' => 'Normal Agent',
        'country' => 'Pakistan',
    ]);
    $visaVendor = VisaVendor::create([
        'company_id' => $company->id,
        'vendor_number' => 'VIS-PRICE',
        'name' => 'Pricing Visa Supplier',
        'service_type' => VisaVendor::SERVICE_VISA_PROVIDER,
        'adult_retail_amount' => 900,
        'adult_cost_amount' => 750,
        'child_retail_amount' => 500,
        'child_cost_amount' => 400,
        'is_default' => true,
        'is_active' => true,
    ]);

    return (object) compact('company', 'owner', 'accountant', 'outsider', 'category', 'agent', 'otherAgent', 'visaVendor');
}

function commercialRateData(object $fixture, array $overrides = []): array
{
    return array_replace([
        'company_id' => $fixture->company->id,
        'service_type' => CommercialRate::SERVICE_VISA_ADULT,
        'visa_vendor_id' => $fixture->visaVendor->id,
        'scope_type' => CommercialRate::SCOPE_DEFAULT,
        'calculation_type' => CommercialRate::CALC_SET_PRICE,
        'amount' => 1000,
        'cost_amount' => 700,
        'currency' => 'SAR',
        'effective_from' => '2026-09-01',
        'effective_until' => null,
        'is_active' => true,
    ], $overrides);
}

test('commercial pricing schema uses tenant tables and immutable group snapshots', function () {
    expect(Schema::hasTable('umrah.pricing_categories'))->toBeTrue()
        ->and(Schema::hasTable('umrah.commercial_rates'))->toBeTrue()
        ->and(Schema::hasColumn('umrah.agents', 'pricing_category_id'))->toBeTrue()
        ->and(Schema::hasColumn('umrah.visa_groups', 'pricing_snapshot'))->toBeTrue();
});

test('setup and pricing pages are limited to commercial pricing roles', function () {
    $f = commercialPricingFixture();
    CommercialRate::create(commercialRateData($f));

    $this->actingAs($f->owner)->get("/{$f->company->slug}/umrah/settings")
        ->assertOk()->assertInertia(fn ($page) => $page->component('Umrah/Settings/Index'));
    $this->actingAs($f->owner)->get("/{$f->company->slug}/umrah/settings/pricing")
        ->assertOk()->assertInertia(fn ($page) => $page
        ->component('Umrah/Settings/Pricing')
        ->where('canManagePricing', true)
        ->has('categories', 1));
    $this->actingAs($f->accountant)->get("/{$f->company->slug}/umrah/settings/pricing")->assertForbidden();
    $this->actingAs($f->outsider)->get("/{$f->company->slug}/umrah/settings/pricing")->assertRedirect();

    $agentResponse = $this->actingAs($f->accountant)->get("/{$f->company->slug}/umrah/agents/{$f->agent->id}")
        ->assertOk()->assertInertia(fn ($page) => $page
        ->where('canManagePricing', false)
        ->where('rates', [])
        ->where('categories', []));
    expect(json_encode($agentResponse->viewData('page')['props']))->not->toContain('cost_amount');
});

test('categories can be created and assigned but active assignments block deactivation', function () {
    $f = commercialPricingFixture();

    $this->actingAs($f->owner)
        ->post("/{$f->company->slug}/umrah/settings/pricing/categories", ['name' => 'Volume Partner', 'description' => 'Monthly volume'])
        ->assertRedirect()->assertSessionHasNoErrors();
    $volume = PricingCategory::where('company_id', $f->company->id)->where('name', 'Volume Partner')->firstOrFail();

    $this->actingAs($f->owner)
        ->put("/{$f->company->slug}/umrah/agents/{$f->otherAgent->id}/pricing-category", ['pricing_category_id' => $volume->id])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($f->otherAgent->fresh()->pricing_category_id)->toBe($volume->id);

    $this->actingAs($f->owner)
        ->patch("/{$f->company->slug}/umrah/settings/pricing/categories/{$volume->id}/status", ['is_active' => false])
        ->assertSessionHasErrors('category');
    expect($volume->fresh()->is_active)->toBeTrue();

    $this->actingAs($f->owner)
        ->post("/{$f->company->slug}/umrah/settings/pricing/categories", ['name' => 'volume partner'])
        ->assertSessionHasErrors('name');
});

test('rate request rejects malformed scopes targets costs percentages dates and overlaps', function () {
    $f = commercialPricingFixture();
    $url = "/{$f->company->slug}/umrah/settings/pricing/rates";
    $valid = [
        'service_type' => 'visa_adult',
        'target_id' => $f->visaVendor->id,
        'scope_type' => 'default',
        'scope_id' => null,
        'calculation_type' => 'set_price',
        'amount' => 1000,
        'cost_amount' => 700,
        'effective_from' => '2026-09-01',
        'effective_until' => '2026-09-30',
    ];

    $this->actingAs($f->owner)->post($url, $valid)->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($f->owner)->post($url, [...$valid, 'effective_from' => '2026-09-15', 'effective_until' => null])
        ->assertSessionHasErrors('effective_from');
    $this->actingAs($f->owner)->post($url, [...$valid, 'scope_type' => 'category', 'scope_id' => null, 'cost_amount' => null])
        ->assertSessionHasErrors('scope_id');
    $this->actingAs($f->owner)->post($url, [...$valid, 'scope_type' => 'category', 'scope_id' => $f->category->id, 'cost_amount' => 1])
        ->assertSessionHasErrors('cost_amount');
    $this->actingAs($f->owner)->post($url, [...$valid, 'scope_type' => 'category', 'scope_id' => $f->category->id, 'calculation_type' => 'discount_percentage', 'amount' => null, 'cost_amount' => null, 'percentage' => 101])
        ->assertSessionHasErrors('percentage');
    $this->actingAs($f->owner)->post($url, [...$valid, 'service_type' => 'standard_transport'])
        ->assertSessionHasErrors('target_id');
    $this->actingAs($f->owner)->post($url, [...$valid, 'target_id' => 'not-a-uuid'])
        ->assertSessionHasErrors('target_id');
    $this->actingAs($f->owner)->post($url, [...$valid, 'target_id' => '00000000-0000-0000-0000-000000000099'])
        ->assertSessionHasErrors('target_id');
    $this->actingAs($f->owner)->post($url, [...$valid, 'effective_from' => '2026-10-10', 'effective_until' => '2026-10-01'])
        ->assertSessionHasErrors('effective_until');
});

test('inactive overlapping rules cannot be reactivated', function () {
    $f = commercialPricingFixture();
    CommercialRate::create(commercialRateData($f, ['effective_until' => '2026-09-30']));
    $inactive = CommercialRate::create(commercialRateData($f, [
        'effective_from' => '2026-09-15',
        'effective_until' => '2026-10-15',
        'is_active' => false,
    ]));

    $this->actingAs($f->owner)
        ->patch("/{$f->company->slug}/umrah/settings/pricing/rates/{$inactive->id}/status", ['is_active' => true])
        ->assertSessionHasErrors('rate');

    expect($inactive->fresh()->is_active)->toBeFalse();
});

test('database exclusion constraint rejects concurrent overlapping active rules', function () {
    $f = commercialPricingFixture();
    CommercialRate::create(commercialRateData($f, ['effective_until' => '2026-09-30']));

    expect(fn () => CommercialRate::create(commercialRateData($f, [
        'effective_from' => '2026-09-30',
        'effective_until' => '2026-10-10',
    ])))->toThrow(QueryException::class);
});

test('resolver applies agent category default and legacy precedence without exposing cost overrides', function () {
    $f = commercialPricingFixture();
    $default = CommercialRate::create(commercialRateData($f));
    CommercialRate::create(commercialRateData($f, [
        'scope_type' => 'category', 'pricing_category_id' => $f->category->id,
        'calculation_type' => 'discount_percentage', 'amount' => null, 'percentage' => 10, 'cost_amount' => null,
    ]));
    $agentRule = CommercialRate::create(commercialRateData($f, [
        'scope_type' => 'agent', 'agent_id' => $f->agent->id,
        'calculation_type' => 'set_price', 'amount' => 850, 'cost_amount' => null,
    ]));
    $resolver = app(CommercialRateResolver::class);

    $agentPrice = $resolver->visa($f->visaVendor, 'adult', $f->agent->id, '2026-09-10');
    $agentRule->update(['is_active' => false]);
    $categoryPrice = $resolver->visa($f->visaVendor, 'adult', $f->agent->id, '2026-09-10');
    $normalPrice = $resolver->visa($f->visaVendor, 'adult', $f->otherAgent->id, '2026-09-10');
    $legacyPrice = $resolver->visa($f->visaVendor, 'adult', $f->otherAgent->id, '2026-08-10');

    expect($agentPrice['sale_amount'])->toBe(850.0)->and($agentPrice['source'])->toBe('agent')->and($agentPrice['cost_amount'])->toBe(700.0)
        ->and($categoryPrice['sale_amount'])->toBe(900.0)->and($categoryPrice['source'])->toBe('category')->and($categoryPrice['cost_amount'])->toBe(700.0)
        ->and($normalPrice['sale_amount'])->toBe(1000.0)->and($normalPrice['source'])->toBe('default')
        ->and($legacyPrice['sale_amount'])->toBe(900.0)->and($legacyPrice['cost_amount'])->toBe(750.0)->and($legacyPrice['source'])->toBe('legacy')
        ->and($normalPrice['default_rule_id'])->toBe($default->id);

    $f->category->update(['is_active' => false]);
    expect($resolver->visa($f->visaVendor, 'adult', $f->agent->id, '2026-09-10')['source'])->toBe('default');
});

test('new groups snapshot the winning rate and later edits do not rewrite history', function () {
    $f = commercialPricingFixture();
    foreach ([
        ['1100', 'Accounts Receivable', 'asset', 'accounts_receivable', 'debit'],
        ['2000', 'Accounts Payable', 'liability', 'accounts_payable', 'credit'],
        ['4100', 'Visa Revenue', 'revenue', 'revenue', 'credit'],
        ['5100', 'Visa Cost', 'cogs', 'cogs', 'debit'],
    ] as [$code, $name, $type, $subtype, $normal]) {
        Account::firstOrCreate(['company_id' => $f->company->id, 'code' => $code], ['name' => $name, 'type' => $type, 'subtype' => $subtype, 'normal_balance' => $normal]);
    }
    CommercialRate::create(commercialRateData($f));
    $categoryRate = CommercialRate::create(commercialRateData($f, [
        'scope_type' => 'category', 'pricing_category_id' => $f->category->id,
        'calculation_type' => 'discount_percentage', 'amount' => null, 'percentage' => 10, 'cost_amount' => null,
    ]));
    $service = app(UmrahCoreService::class);
    $payload = [
        'agent_id' => $f->agent->id, 'vendor_id' => $f->visaVendor->id,
        'group_number' => 'PRICE-SNAPSHOT-1', 'name' => 'Snapshot test', 'travel_date' => '2026-09-10',
        'passenger_count' => 1, 'transport_mode' => VisaGroup::TRANSPORT_NONE, 'includes_visa' => true,
        'passengers' => [['full_name' => 'Adult Pilgrim', 'imported_age' => 30, 'service_type' => Passenger::SERVICE_VISA_TRANSPORT]],
    ];
    $group = $service->createGroup($f->company->id, $payload);
    $categoryRate->update(['percentage' => 20]);
    $newGroup = $service->createGroup($f->company->id, [...$payload, 'group_number' => 'PRICE-SNAPSHOT-2']);

    expect((float) $group->visa_sale_amount)->toBe(900.0)
        ->and((float) $group->visa_cost_amount)->toBe(700.0)
        ->and(data_get($group->pricing_snapshot, 'visa.adult.source'))->toBe('category')
        ->and((float) $group->fresh()->visa_sale_amount)->toBe(900.0)
        ->and((float) $newGroup->visa_sale_amount)->toBe(800.0);
});

test('hotel pricing resolves each occupied night across effective periods', function () {
    $f = commercialPricingFixture();
    $vendor = HotelVendor::create(['company_id' => $f->company->id, 'vendor_number' => 'HV-PRICE', 'name' => 'Hotel Supplier', 'is_active' => true]);
    $hotel = Hotel::create(['company_id' => $f->company->id, 'hotel_vendor_id' => $vendor->id, 'name' => 'Dated Hotel', 'city' => 'Makkah', 'is_active' => true]);
    $room = HotelRoomRate::create(['company_id' => $f->company->id, 'hotel_id' => $hotel->id, 'room_type' => 'double', 'retail_amount' => 100, 'cost_amount' => 60, 'is_active' => true]);
    CommercialRate::create([
        'company_id' => $f->company->id, 'service_type' => 'hotel_room', 'hotel_room_rate_id' => $room->id,
        'scope_type' => 'default', 'calculation_type' => 'set_price', 'amount' => 120, 'cost_amount' => 70,
        'currency' => 'SAR', 'effective_from' => '2026-09-01', 'effective_until' => '2026-09-02', 'is_active' => true,
    ]);
    CommercialRate::create([
        'company_id' => $f->company->id, 'service_type' => 'hotel_room', 'hotel_room_rate_id' => $room->id,
        'scope_type' => 'default', 'calculation_type' => 'set_price', 'amount' => 150, 'cost_amount' => 80,
        'currency' => 'SAR', 'effective_from' => '2026-09-03', 'effective_until' => null, 'is_active' => true,
    ]);
    CommercialRate::create([
        'company_id' => $f->company->id, 'service_type' => 'hotel_room', 'hotel_room_rate_id' => $room->id,
        'scope_type' => 'category', 'pricing_category_id' => $f->category->id,
        'calculation_type' => 'discount_percentage', 'percentage' => 10,
        'currency' => 'SAR', 'effective_from' => '2026-09-01', 'effective_until' => null, 'is_active' => true,
    ]);
    $controller = app(VoucherController::class);
    $method = new ReflectionMethod($controller, 'resolveHotelStays');
    [$stays, $sale, $cost] = $method->invoke($controller, $f->company->id, [[
        'source' => 'company', 'hotel_id' => $hotel->id, 'hotel_name' => $hotel->name, 'city' => 'Makkah',
        'room_type' => 'double', 'room_count' => 1, 'check_in_date' => '2026-09-01', 'check_out_date' => '2026-09-05', 'notes' => null,
    ]], true, $f->agent->id);

    expect($sale)->toBe(972.0)->and($cost)->toBe(600.0)
        ->and($stays[0]['night_count'])->toBe(4)
        ->and($stays[0]['pricing_breakdown'])->toHaveCount(4)
        ->and($stays[0]['pricing_breakdown'][0]['sale_amount'])->toBe(108.0)
        ->and($stays[0]['pricing_breakdown'][2]['sale_amount'])->toBe(135.0);
});

test('quick booking quote changes by agent and never sends supplier cost', function () {
    $f = commercialPricingFixture();
    CommercialRate::create(commercialRateData($f));
    CommercialRate::create(commercialRateData($f, [
        'scope_type' => 'agent', 'agent_id' => $f->agent->id,
        'calculation_type' => 'set_price', 'amount' => 825, 'cost_amount' => null,
    ]));

    $response = $this->actingAs($f->owner)->get("/{$f->company->slug}/umrah/quick-booking?agent_id={$f->agent->id}&travel_date=2026-09-10")
        ->assertOk()->assertInertia(fn ($page) => $page
        ->where('pricing.visa.adult', 825)
        ->where('pricing.visa.source', 'mixed')
        ->where('pricing.agent_id', $f->agent->id)
        ->where('pricing.service_date', '2026-09-10'));

    expect(json_encode($response->viewData('page')['props']))->not->toContain('cost_amount');
});

test('commercial adjustments have predictable boundaries and never change supplier cost', function (string $method, ?float $amount, ?float $percentage, float $expected) {
    $f = commercialPricingFixture();
    CommercialRate::create(commercialRateData($f));
    CommercialRate::create(commercialRateData($f, [
        'scope_type' => 'agent', 'agent_id' => $f->agent->id,
        'calculation_type' => $method, 'amount' => $amount, 'percentage' => $percentage, 'cost_amount' => null,
    ]));

    $price = app(CommercialRateResolver::class)->visa($f->visaVendor, 'adult', $f->agent->id, '2026-09-10');
    expect($price['sale_amount'])->toBe($expected)->and($price['cost_amount'])->toBe(700.0);
})->with([
    'free exact price' => ['set_price', 0.0, null, 0.0],
    'fixed discount' => ['discount_amount', 125.0, null, 875.0],
    'discount floor' => ['discount_amount', 1500.0, null, 0.0],
    'zero discount' => ['discount_percentage', null, 0.0, 1000.0],
    'full discount' => ['discount_percentage', null, 100.0, 0.0],
    'fractional discount' => ['discount_percentage', null, 12.3456, 876.54],
    'fixed markup' => ['markup_amount', 125.25, null, 1125.25],
    'percentage markup' => ['markup_percentage', null, 12.5, 1125.0],
    'maximum markup' => ['markup_percentage', null, 1000.0, 11000.0],
]);

test('adjacent date periods are accepted and inclusive boundaries select exactly one rate', function () {
    $f = commercialPricingFixture();
    $url = "/{$f->company->slug}/umrah/settings/pricing/rates";
    $payload = [
        'service_type' => 'visa_adult', 'target_id' => $f->visaVendor->id,
        'scope_type' => 'default', 'calculation_type' => 'set_price', 'amount' => 1000,
        'effective_from' => '2026-09-01', 'effective_until' => '2026-09-30',
    ];
    $this->actingAs($f->owner)->post($url, $payload)->assertSessionHasNoErrors();
    $this->post($url, [...$payload, 'amount' => 1200, 'effective_from' => '2026-10-01', 'effective_until' => '2026-10-31'])
        ->assertSessionHasNoErrors();
    $resolver = app(CommercialRateResolver::class);
    foreach (['2026-08-31' => 900.0, '2026-09-01' => 1000.0, '2026-09-30' => 1000.0, '2026-10-01' => 1200.0, '2026-10-31' => 1200.0, '2026-11-01' => 900.0] as $date => $expected) {
        expect($resolver->visa($f->visaVendor, 'adult', null, $date)['sale_amount'])->toBe($expected);
    }
});

test('a dated override follows changing defaults and falls back to legacy when defaults expire', function () {
    $f = commercialPricingFixture();
    CommercialRate::create(commercialRateData($f, ['effective_until' => '2026-09-30', 'cost_amount' => null]));
    CommercialRate::create(commercialRateData($f, ['amount' => 1200, 'effective_from' => '2026-10-01', 'effective_until' => '2026-10-31']));
    CommercialRate::create(commercialRateData($f, [
        'scope_type' => 'agent', 'agent_id' => $f->agent->id,
        'calculation_type' => 'discount_percentage', 'amount' => null, 'percentage' => 10, 'cost_amount' => null,
    ]));
    $resolver = app(CommercialRateResolver::class);
    foreach (['2026-09-01' => 900.0, '2026-10-01' => 1080.0, '2026-11-01' => 810.0] as $date => $expected) {
        expect($resolver->visa($f->visaVendor, 'adult', $f->agent->id, $date)['sale_amount'])->toBe($expected);
    }
    expect($resolver->visa($f->visaVendor, 'adult', $f->agent->id, '2026-09-01')['cost_amount'])->toBe(750.0);
});

test('pricing writes are forbidden for accountant operations and agent roles', function (string $role) {
    $f = commercialPricingFixture();
    $user = $f->accountant;
    if ($role !== 'accountant') {
        $user = User::factory()->withoutTwoFactor()->create();
        commercialPricingAddMember($f->company, $user, $role);
    }
    $rate = CommercialRate::create(commercialRateData($f));
    $base = "/{$f->company->slug}/umrah/settings/pricing";
    $this->actingAs($user)->get($base)->assertForbidden();
    $this->post("$base/categories", ['name' => 'Unauthorized'])->assertForbidden();
    $this->put("$base/categories/{$f->category->id}", ['name' => 'Unauthorized'])->assertForbidden();
    $this->patch("$base/categories/{$f->category->id}/status", ['is_active' => false])->assertForbidden();
    $this->post("$base/rates", [])->assertForbidden();
    $this->put("$base/rates/{$rate->id}", [])->assertForbidden();
    $this->patch("$base/rates/{$rate->id}/status", ['is_active' => false])->assertForbidden();
    $this->put("/{$f->company->slug}/umrah/agents/{$f->agent->id}/pricing-category", ['pricing_category_id' => null])->assertForbidden();
    expect($rate->fresh()->is_active)->toBeTrue()->and($f->agent->fresh()->pricing_category_id)->toBe($f->category->id);
})->with(['accountant', 'operations', 'agent']);

test('another company cannot supply the vendor agent or category for a pricing rule', function () {
    $f = commercialPricingFixture();
    $foreign = ticketingCompany();
    $foreignVendor = VisaVendor::create([
        'company_id' => $foreign->company->id, 'vendor_number' => 'FOREIGN-PRICE', 'name' => 'Other Company Supplier',
        'service_type' => VisaVendor::SERVICE_VISA_PROVIDER, 'adult_retail_amount' => 999, 'adult_cost_amount' => 500,
    ]);
    $foreignAgent = ticketingAgent($foreign->company);
    $foreignCategory = PricingCategory::create(['company_id' => $foreign->company->id, 'name' => 'Foreign Category', 'is_active' => true]);
    CompanyContext::setContext($f->company);
    $url = "/{$f->company->slug}/umrah/settings/pricing/rates";
    $payload = [
        'service_type' => 'visa_adult', 'target_id' => $f->visaVendor->id,
        'scope_type' => 'default', 'calculation_type' => 'set_price', 'amount' => 1000, 'effective_from' => '2026-09-01',
    ];
    $this->actingAs($f->owner)->post($url, [...$payload, 'target_id' => $foreignVendor->id])->assertSessionHasErrors('target_id');
    $this->post($url, [...$payload, 'scope_type' => 'agent', 'scope_id' => $foreignAgent->id])->assertSessionHasErrors('scope_id');
    $this->post($url, [...$payload, 'scope_type' => 'category', 'scope_id' => $foreignCategory->id])->assertSessionHasErrors('scope_id');
    expect(CommercialRate::where('company_id', $f->company->id)->count())->toBe(0);
});

test('a manager can create edit and deactivate a rate without moving its agent', function () {
    $f = commercialPricingFixture();
    $manager = User::factory()->withoutTwoFactor()->create();
    commercialPricingAddMember($f->company, $manager, 'manager');
    $base = "/{$f->company->slug}/umrah/settings/pricing";
    $payload = [
        'service_type' => 'visa_adult', 'target_id' => $f->visaVendor->id,
        'scope_type' => 'agent', 'scope_id' => $f->agent->id,
        'calculation_type' => 'set_price', 'amount' => 825, 'effective_from' => '2026-09-01',
    ];
    $this->actingAs($manager)->get($base)->assertOk();
    $this->post("$base/rates", $payload)->assertSessionHasNoErrors()->assertSessionHas('success');
    $rate = CommercialRate::where('company_id', $f->company->id)->firstOrFail();
    $this->put("$base/rates/{$rate->id}", [...$payload, 'amount' => 800])->assertSessionHasNoErrors()->assertSessionHas('success');
    expect((float) $rate->fresh()->amount)->toBe(800.0)->and($rate->fresh()->agent_id)->toBe($f->agent->id);
    $this->patch("$base/rates/{$rate->id}/status", ['is_active' => false])->assertSessionHasNoErrors();
    expect($rate->fresh()->is_active)->toBeFalse();
    $this->patch("$base/rates/{$rate->id}/status", ['is_active' => true])->assertSessionHasNoErrors();
    expect($rate->fresh()->is_active)->toBeTrue();
});
