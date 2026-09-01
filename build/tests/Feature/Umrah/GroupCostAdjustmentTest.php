<?php

use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Services\GroupAccountingService;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/TicketingFixtures.php';

/**
 * A supplier who lowers their price after a group is built.
 *
 * Until now the accounting screen could change what the agent is charged
 * but not what the suppliers charge, so the only way to record a cheaper
 * visa was to move the group onto a different vendor record. The cost is
 * now editable directly and the difference posts against the payable.
 *
 * The same screen used to re-price a standard bus from the provider's
 * rates on every save, so correcting one figure quietly re-costed the
 * group at whatever the provider charges today.
 */
function costAdjustmentFixture(): object
{
    Illuminate\Support\Carbon::setTestNow('2026-09-15 09:00:00');

    $f = ticketingCompany([
        'industry_code' => 'umrah',
        'settings' => ['modules' => ['umrah' => true]],
        'base_currency' => 'SAR',
    ]);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$f->company->id]);

    foreach ([
        ['1100', 'Accounts Receivable', 'asset', 'accounts_receivable', 'debit'],
        ['2000', 'Accounts Payable', 'liability', 'accounts_payable', 'credit'],
        ['4100', 'Visa Revenue', 'revenue', 'revenue', 'credit'],
        ['4110', 'Transport Revenue', 'revenue', 'revenue', 'credit'],
        ['5100', 'Visa Cost', 'cogs', 'cogs', 'debit'],
        ['5110', 'Transport Cost', 'cogs', 'cogs', 'debit'],
    ] as [$code, $name, $type, $subtype, $normal]) {
        App\Modules\Accounting\Models\Account::firstOrCreate(
            ['company_id' => $f->company->id, 'code' => $code],
            ['name' => $name, 'type' => $type, 'subtype' => $subtype, 'normal_balance' => $normal],
        );
    }

    $agent = Agent::create([
        'company_id' => $f->company->id,
        'agent_number' => 'AGT-'.str()->upper(str()->random(5)),
        'name' => 'Cost Adjustment Agent',
    ]);
    $visaVendor = VisaVendor::create([
        'company_id' => $f->company->id,
        'vendor_number' => 'VIS-'.str()->upper(str()->random(5)),
        'name' => 'Visa Vendor',
        'service_type' => VisaVendor::SERVICE_VISA_PROVIDER,
        'adult_retail_amount' => 600, 'adult_cost_amount' => 520,
        'child_retail_amount' => 600, 'child_cost_amount' => 520,
    ]);
    $busVendor = VisaVendor::create([
        'company_id' => $f->company->id,
        'vendor_number' => 'TRN-'.str()->upper(str()->random(5)),
        'name' => 'Bus Vendor',
        'service_type' => VisaVendor::SERVICE_TRANSPORT_PROVIDER,
        'standard_bus_retail_amount' => 80,
        'standard_bus_cost_amount' => 50,
        'charge_child_fare' => true,
    ]);

    $group = VisaGroup::create([
        'company_id' => $f->company->id,
        'agent_id' => $agent->id,
        'vendor_id' => $visaVendor->id,
        'mandatory_transport_vendor_id' => $busVendor->id,
        'group_number' => 'UGR-'.str()->upper(str()->random(5)),
        'name' => 'Cost adjustment group',
        'status' => VisaGroup::STATUS_VISA_APPROVED,
        'transport_mode' => VisaGroup::TRANSPORT_STANDARD_BUS,
        'transport_required' => true,
        'passenger_count' => 2,
        'visa_sale_amount' => 1200, 'visa_cost_amount' => 1040,
        'transport_amount' => 160, 'transport_cost_amount' => 100,
        'standard_bus_retail_amount' => 80, 'standard_bus_cost_amount' => 50,
        'standard_bus_charge_child_fare' => true,
        'standard_bus_billable_passenger_count' => 2,
        'total_receivable' => 1360,
    ]);

    foreach (['First Passenger', 'Second Passenger'] as $name) {
        Passenger::create([
            'company_id' => $f->company->id,
            'visa_group_id' => $group->id,
            'full_name' => $name,
            'service_type' => Passenger::SERVICE_VISA_TRANSPORT,
        ]);
    }

    return (object) [
        'company' => $f->company,
        'user' => $f->user,
        'group' => $group->fresh(),
        'visaVendor' => $visaVendor,
        'busVendor' => $busVendor,
    ];
}

function adjustAccounting(object $f, array $overrides = []): VisaGroup
{
    return app(GroupAccountingService::class)->update($f->group->fresh(), array_merge([
        'vendor_id' => $f->visaVendor->id,
        'mandatory_transport_vendor_id' => $f->busVendor->id,
        'visa_sale_amount' => 1200,
        'transport_amount' => 160,
        'discount_amount' => 0,
        'visa_cost_amount' => 1040,
        'transport_cost_amount' => 100,
        'reason' => 'Supplier renegotiation',
    ], $overrides));
}

test('a report can be filtered to one agent', function () {
    // Laravel reads a dot in an exists rule as connection.table, so the
    // agent filter asked for a connection called umrah and threw before
    // running a query. Every report filtered by a party did.
    $f = costAdjustmentFixture();

    Illuminate\Support\Facades\DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(App\Services\CompanyRbacBootstrapper::class)->bootstrap($f->company);
    ticketingAddCompanyMember($f->company, $f->user, 'owner');
    Illuminate\Support\Facades\DB::select("SELECT set_config('app.is_super_admin', 'false', false)");

    $agentId = $f->group->agent_id;

    test()->actingAs($f->user)
        ->get("/{$f->company->slug}/umrah/reports/agent-statement?agent_id={$agentId}&start=2026-09-01&end=2026-09-30")
        ->assertOk();
});

test('an agent from nowhere is still refused', function () {
    $f = costAdjustmentFixture();

    Illuminate\Support\Facades\DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(App\Services\CompanyRbacBootstrapper::class)->bootstrap($f->company);
    ticketingAddCompanyMember($f->company, $f->user, 'owner');
    Illuminate\Support\Facades\DB::select("SELECT set_config('app.is_super_admin', 'false', false)");

    test()->actingAs($f->user)
        ->get("/{$f->company->slug}/umrah/reports/agent-statement?agent_id=01a04ccc-964b-738d-baf0-21a0994f687e&start=2026-09-01&end=2026-09-30")
        ->assertSessionHasErrors('agent_id');
});

test('an adjustment saves when the page sends no cost fields', function () {
    // A screen opened before the cost fields existed submits without them.
    // Absent has to mean unchanged, or every page somebody already had open
    // would start failing the moment this deployed.
    $f = costAdjustmentFixture();

    Illuminate\Support\Facades\DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(App\Services\CompanyRbacBootstrapper::class)->bootstrap($f->company);
    ticketingAddCompanyMember($f->company, $f->user, 'owner');
    Illuminate\Support\Facades\DB::select("SELECT set_config('app.is_super_admin', 'false', false)");

    test()->actingAs($f->user)
        ->put("/{$f->company->slug}/umrah/groups/{$f->group->id}/accounting", [
            'vendor_id' => $f->visaVendor->id,
            'mandatory_transport_vendor_id' => $f->busVendor->id,
            'visa_sale_amount' => 1120,
            'transport_amount' => 160,
            'discount_amount' => 0,
            'reason' => 'Rate changed after the group was booked',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $group = $f->group->fresh();

    expect((float) $group->visa_sale_amount)->toBe(1120.0)
        ->and((float) $group->visa_cost_amount)->toBe(1040.0);
});

test('the accounting screen saves an adjustment over http', function () {
    // Everything else here calls the service. This is the path a person
    // actually takes -- through the form request, with the payload the
    // page sends -- and nothing had covered it.
    $f = costAdjustmentFixture();

    Illuminate\Support\Facades\DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(App\Services\CompanyRbacBootstrapper::class)->bootstrap($f->company);
    ticketingAddCompanyMember($f->company, $f->user, 'owner');
    Illuminate\Support\Facades\DB::select("SELECT set_config('app.is_super_admin', 'false', false)");

    test()->actingAs($f->user)
        ->put("/{$f->company->slug}/umrah/groups/{$f->group->id}/accounting", [
            'vendor_id' => $f->visaVendor->id,
            'mandatory_transport_vendor_id' => $f->busVendor->id,
            'visa_sale_amount' => 1120,
            'transport_amount' => 160,
            'discount_amount' => 0,
            'visa_cost_amount' => 1040,
            'transport_cost_amount' => 100,
            'reason' => 'Rate changed after the group was booked',
            'reason_category' => 'rate_change',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect((float) $f->group->fresh()->visa_sale_amount)->toBe(1120.0);
});

test('a supplier lowering their visa price can be recorded', function () {
    $f = costAdjustmentFixture();

    // 520 a head down to 480: 1,040 becomes 960.
    $group = adjustAccounting($f, ['visa_cost_amount' => 960]);

    expect((float) $group->visa_cost_amount)->toBe(960.0)
        ->and((float) $group->profit)->toBe(1360.0 - 960.0 - 100.0);
});

test('the difference is posted, not the whole new cost', function () {
    $f = costAdjustmentFixture();
    adjustAccounting($f, ['visa_cost_amount' => 960]);

    $adjustment = App\Modules\Accounting\Models\Transaction::where('company_id', $f->company->id)
        ->where('transaction_type', 'umrah_group_cost_adjustment')
        ->latest('created_at')
        ->first();

    $moved = (float) DB::table('acct.journal_entries')
        ->where('transaction_id', $adjustment->id)
        ->sum('credit_amount');

    // 1,040 down to 960 is eighty, and eighty is what should move.
    expect($moved)->toBe(80.0);
});

test('the agent charge is untouched by a supplier price change', function () {
    // Cost is what the company pays. What the agent owes does not move.
    $f = costAdjustmentFixture();

    $group = adjustAccounting($f, ['visa_cost_amount' => 960]);

    expect((float) $group->total_receivable)->toBe(1360.0)
        ->and((float) $group->visa_sale_amount)->toBe(1200.0);
});

test('saving the screen does not re-price the bus at today rate', function () {
    // The provider raises its rate after this group was sold.
    $f = costAdjustmentFixture();
    $f->busVendor->update(['standard_bus_retail_amount' => 120, 'standard_bus_cost_amount' => 90]);

    // Someone corrects the discount. Nothing about transport was touched.
    $group = adjustAccounting($f, ['discount_amount' => 10]);

    expect((float) $group->transport_amount)->toBe(160.0)
        ->and((float) $group->transport_cost_amount)->toBe(100.0)
        ->and((float) $group->standard_bus_retail_amount)->toBe(80.0);
});

test('naming a different provider does re-price', function () {
    $f = costAdjustmentFixture();
    $other = VisaVendor::create([
        'company_id' => $f->company->id,
        'vendor_number' => 'TRN-'.str()->upper(str()->random(5)),
        'name' => 'Cheaper Bus',
        'service_type' => VisaVendor::SERVICE_TRANSPORT_PROVIDER,
        'standard_bus_retail_amount' => 60,
        'standard_bus_cost_amount' => 40,
        'charge_child_fare' => true,
    ]);

    $group = adjustAccounting($f, ['mandatory_transport_vendor_id' => $other->id]);

    // Two billable passengers at the new provider's rates.
    expect((float) $group->transport_amount)->toBe(120.0)
        ->and((float) $group->transport_cost_amount)->toBe(80.0);
});

function statementRows(object $f): Illuminate\Support\Collection
{
    return collect(app(App\Modules\Umrah\Services\TravelReportService::class)->build(
        $f->company,
        App\Models\User::first(),
        'agent-statement',
        ['start' => '2026-01-01', 'end' => '2026-12-31'],
        false,
    )['rows']);
}

test('an adjustment records what kind of change it was', function () {
    // The sentence says what happened; this says what kind of thing it was,
    // so the same question can be asked of every adjustment at once.
    $f = costAdjustmentFixture();

    adjustAccounting($f, [
        'visa_cost_amount' => 960,
        'reason' => 'Renegotiated with the supplier',
        'reason_category' => 'renegotiated',
    ]);

    $adjustment = App\Modules\Accounting\Models\Transaction::where('company_id', $f->company->id)
        ->where('transaction_type', 'umrah_group_cost_adjustment')
        ->latest('created_at')
        ->first();

    expect($adjustment->metadata['reason_category'])->toBe('renegotiated')
        ->and($adjustment->metadata['reason'])->toBe('Renegotiated with the supplier');
});

test('an adjustment without a category still records its reason', function () {
    // Everything written before the categories existed has a sentence and
    // no category, and guessing one from the words would invent a fact.
    $f = costAdjustmentFixture();

    adjustAccounting($f, ['visa_cost_amount' => 960, 'reason' => 'Something else entirely']);

    $adjustment = App\Modules\Accounting\Models\Transaction::where('company_id', $f->company->id)
        ->where('transaction_type', 'umrah_group_cost_adjustment')
        ->latest('created_at')
        ->first();

    expect($adjustment->metadata['reason_category'])->toBeNull()
        ->and($adjustment->metadata['reason'])->toBe('Something else entirely');
});

test('a sale adjustment shows on the agent statement as its own line', function () {
    // The agent has to see the charge they agreed and the change made
    // later, not one revised total dated the day of the original.
    $f = costAdjustmentFixture();

    adjustAccounting($f, ['visa_sale_amount' => 1500, 'reason' => 'Rate corrected after booking']);

    $rows = statementRows($f);
    $charge = $rows->firstWhere('type', 'charge');
    $adjustment = $rows->firstWhere('type', 'adjustment');

    expect((float) $charge['charge'])->toBe(1360.0)
        ->and($adjustment)->not->toBeNull()
        ->and((float) $adjustment['charge'])->toBe(300.0)
        ->and($adjustment['description'])->toContain('Rate corrected after booking');
});

test('the original charge plus its adjustments equal what is owed', function () {
    $f = costAdjustmentFixture();
    adjustAccounting($f, ['visa_sale_amount' => 1500, 'reason' => 'Rate corrected']);

    expect(round(statementRows($f)->sum('charge'), 2))->toBe(1660.0)
        ->and((float) $f->group->fresh()->total_receivable)->toBe(1660.0);
});

test('a cost change puts nothing on the agent statement', function () {
    // Cost is between the company and its supplier.
    $f = costAdjustmentFixture();

    adjustAccounting($f, ['visa_cost_amount' => 960, 'reason' => 'Supplier discount']);

    $rows = statementRows($f);

    expect($rows->firstWhere('type', 'adjustment'))->toBeNull()
        ->and(round($rows->sum('charge'), 2))->toBe(1360.0);
});

afterEach(function () {
    Illuminate\Support\Carbon::setTestNow();
});
