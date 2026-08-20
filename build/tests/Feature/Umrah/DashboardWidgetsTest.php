<?php

use App\Dashboard\DashboardLayoutResolver;
use App\Dashboard\WidgetRegistry;
use App\Facades\CompanyContext;
use App\Models\Company;
use App\Models\DashboardLayout;
use App\Models\User;
use App\Modules\Umrah\Dashboard\Widgets\CashBookWidget;
use App\Modules\Umrah\Dashboard\Widgets\DeparturesWidget;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\GroupPayment;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

function dashboardWidgetsCompany(): array
{
    $owner = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Dashboard Widgets '.str()->random(8),
        'slug' => 'dashboard-widgets-'.str()->lower(str()->random(10)),
        'base_currency' => 'SAR',
        'industry_code' => 'umrah',
        'settings' => ['modules' => ['umrah' => true]],
    ]);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$owner->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($company);
    dashboardWidgetsMember($company, $owner, 'owner');
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");

    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

    return [$company, $owner];
}

function dashboardWidgetsMember(Company $company, User $user, string $role): void
{
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => $role,
        'joined_at' => now(),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    CompanyContext::withContext($company, fn () => CompanyContext::assignRole($user, $role));
}

function dashboardWidgetsAgent(Company $company, string $number): array
{
    $agentUser = User::factory()->withoutTwoFactor()->create();
    dashboardWidgetsMember($company, $agentUser, 'agent');

    $agent = Agent::create([
        'company_id' => $company->id,
        'agent_number' => $number,
        'name' => 'Agent '.$number,
        'user_id' => $agentUser->id,
        'is_active' => true,
    ]);

    return [$agentUser, $agent];
}

test('the resolver falls back to the role default layout when no saved layout exists', function () {
    [$company, $owner] = dashboardWidgetsCompany();
    CompanyContext::setContext($company);

    $tabs = app(DashboardLayoutResolver::class)->resolve($owner, $company, 'umrah');

    $expectedTabKeys = collect(config('dashboards.umrah.roles.owner'))->pluck('key')->all();
    expect(collect($tabs)->pluck('key')->all())->toBe($expectedTabKeys);

    // The dashboard is deliberately scoped to widgets real data already backs:
    // Upcoming (departures) and Money (cash book + the two balance registers).
    // needs_attention and transport_readiness stay registered but unplaced.
    expect(collect($tabs)->pluck('key')->all())->toBe(['upcoming', 'money']);

    $money = collect($tabs)->firstWhere('key', 'money');
    expect(collect($money['widgets'])->pluck('key')->all())
        ->toBe(['umrah.cash_book', 'umrah.agent_balances', 'umrah.vendor_balances']);
});

test('an unregistered widget key in a saved layout is dropped rather than throwing', function () {
    [$company, $owner] = dashboardWidgetsCompany();
    CompanyContext::setContext($company);

    DashboardLayout::create([
        'user_id' => $owner->id,
        'company_id' => $company->id,
        'dashboard_key' => 'umrah',
        'tabs' => [
            [
                'key' => 'overview',
                'label' => 'Overview',
                'widgets' => [
                    ['key' => 'umrah.departures', 'span' => 12, 'options' => []],
                    ['key' => 'umrah.this_widget_was_removed', 'span' => 6, 'options' => []],
                ],
            ],
        ],
    ]);

    $tabs = app(DashboardLayoutResolver::class)->resolve($owner, $company, 'umrah');

    expect($tabs)->toHaveCount(1);
    expect(collect($tabs[0]['widgets'])->pluck('key')->all())->toBe(['umrah.departures']);
});

test('a widget the user lacks permission for is dropped from the resolved layout', function () {
    [$company, $owner] = dashboardWidgetsCompany();
    [$operationsUser] = dashboardWidgetsAgentWithRole($company, 'operations');
    CompanyContext::setContext($company);

    // Operations lacks umrah.agent.view — confirm the registry knows the widget
    // (sanity: the key is real) before proving the resolver drops it for this user.
    expect(app(WidgetRegistry::class)->has('umrah.agent_balances'))->toBeTrue();

    DashboardLayout::create([
        'user_id' => $operationsUser->id,
        'company_id' => $company->id,
        'dashboard_key' => 'umrah',
        'tabs' => [
            [
                'key' => 'money',
                'label' => 'Money',
                'widgets' => [
                    ['key' => 'umrah.departures', 'span' => 12, 'options' => []],
                    ['key' => 'umrah.agent_balances', 'span' => 6, 'options' => []],
                ],
            ],
        ],
    ]);

    $tabs = app(DashboardLayoutResolver::class)->resolve($operationsUser, $company, 'umrah');

    expect(collect($tabs[0]['widgets'])->pluck('key')->all())->toBe(['umrah.departures']);
});

function dashboardWidgetsAgentWithRole(Company $company, string $role): array
{
    $user = User::factory()->withoutTwoFactor()->create();
    dashboardWidgetsMember($company, $user, $role);

    return [$user];
}

test("an agent user's departures widget returns only their own groups", function () {
    [$company] = dashboardWidgetsCompany();
    [$agentOneUser, $agentOne] = dashboardWidgetsAgent($company, 'AGT-ONE');
    [, $agentTwo] = dashboardWidgetsAgent($company, 'AGT-TWO');

    VisaGroup::create([
        'company_id' => $company->id,
        'agent_id' => $agentOne->id,
        'group_number' => 'UGR-ONE',
        'name' => 'Group One',
        'status' => VisaGroup::STATUS_PASSPORTS_RECEIVED,
        'travel_date' => now()->addDays(5)->toDateString(),
        'passenger_count' => 2,
    ]);
    VisaGroup::create([
        'company_id' => $company->id,
        'agent_id' => $agentTwo->id,
        'group_number' => 'UGR-TWO',
        'name' => 'Group Two',
        'status' => VisaGroup::STATUS_PASSPORTS_RECEIVED,
        'travel_date' => now()->addDays(7)->toDateString(),
        'passenger_count' => 3,
    ]);

    $data = (new DeparturesWidget())->resolve($company, $agentOneUser, []);

    expect($data['rows'])->toHaveCount(1)
        ->and($data['rows'][0]['group_number'])->toBe('UGR-ONE');
});

test('cash_book widget puts a received payment in the in column and a paid payment in the out column', function () {
    [$company, $owner] = dashboardWidgetsCompany();
    $agent = Agent::create([
        'company_id' => $company->id,
        'agent_number' => 'AGT-CB',
        'name' => 'Cash Book Agent',
    ]);

    GroupPayment::create([
        'company_id' => $company->id,
        'agent_id' => $agent->id,
        'direction' => GroupPayment::DIRECTION_RECEIVED,
        'payment_number' => 'UPM-IN-1',
        'payment_date' => now()->toDateString(),
        'amount' => 500,
        'currency' => 'SAR',
        'base_currency' => 'SAR',
        'base_amount' => 500,
        'method' => GroupPayment::METHOD_CASH,
        'status' => GroupPayment::STATUS_POSTED,
    ]);

    $vendor = VisaVendor::create([
        'company_id' => $company->id,
        'vendor_number' => 'VIS-CB',
        'name' => 'Cash Book Vendor',
        'vendor_type' => VisaVendor::TYPE_VISA_PROVIDER,
    ]);

    GroupPayment::create([
        'company_id' => $company->id,
        'visa_vendor_id' => $vendor->id,
        'direction' => GroupPayment::DIRECTION_SENT,
        'payment_number' => 'UPM-OUT-1',
        'payment_date' => now()->toDateString(),
        'amount' => 200,
        'currency' => 'SAR',
        'base_currency' => 'SAR',
        'base_amount' => 200,
        'method' => GroupPayment::METHOD_CASH,
        'status' => GroupPayment::STATUS_POSTED,
    ]);

    $data = (new CashBookWidget())->resolve($company, $owner, []);

    $rowsByNumber = collect($data['rows'])->keyBy('payment_number');

    expect($rowsByNumber['UPM-IN-1']['in'])->toBe(500.0)
        ->and($rowsByNumber['UPM-IN-1']['out'])->toBeNull()
        ->and($rowsByNumber['UPM-OUT-1']['out'])->toBe(200.0)
        ->and($rowsByNumber['UPM-OUT-1']['in'])->toBeNull()
        ->and($data['totals']['in'])->toBe(500.0)
        ->and($data['totals']['out'])->toBe(200.0)
        ->and($data['total_movements'])->toBe(2);
});
