<?php

use App\Dashboard\DashboardLayoutResolver;
use App\Dashboard\WidgetRegistry;
use App\Facades\CompanyContext;
use App\Models\Company;
use App\Models\DashboardLayout;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Umrah\Dashboard\Widgets\CashBookWidget;
use App\Modules\Umrah\Dashboard\Widgets\CashPositionWidget;
use App\Modules\Umrah\Dashboard\Widgets\DeparturesWidget;
use App\Modules\Umrah\Dashboard\Widgets\RefundsAwaitingDecisionWidget;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\GroupPayment;
use App\Modules\Umrah\Models\Refund;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Services\RefundService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        ->toBe(['umrah.cash_position', 'umrah.refunds_awaiting_decision', 'umrah.cash_book', 'umrah.agent_balances', 'umrah.vendor_balances']);
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

/**
 * Chart of accounts + fiscal year/period fixture for cash_position tests.
 * Returns the four accounts the widget cares about, keyed by role.
 */
function dashboardWidgetsLedgerAccounts(Company $company): array
{
    $fy = FiscalYear::create([
        'company_id' => $company->id,
        'name' => 'FY 2026',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'status' => 'open',
    ]);

    $period = AccountingPeriod::create([
        'company_id' => $company->id,
        'fiscal_year_id' => $fy->id,
        // Covers the whole year rather than one month. Refund postings are
        // dated today, so a single-month period made these tests start
        // failing the morning the calendar left it.
        'name' => 'FY 2026',
        'period_number' => 1,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    $cash = Account::create([
        'company_id' => $company->id,
        'code' => '1050',
        'name' => 'Petty Cash',
        'type' => 'asset',
        'subtype' => 'cash',
        'normal_balance' => 'debit',
    ]);

    $agentAdvances = Account::create([
        'company_id' => $company->id,
        'code' => '2200',
        'name' => 'Agent Advances',
        'type' => 'liability',
        'subtype' => 'other_current_liability',
        'normal_balance' => 'credit',
    ]);

    $vendorPayable = Account::create([
        'company_id' => $company->id,
        'code' => '2100',
        'name' => 'Accounts Payable',
        'type' => 'liability',
        'subtype' => 'accounts_payable',
        'normal_balance' => 'credit',
    ]);

    $refundsPayable = Account::create([
        'company_id' => $company->id,
        'code' => '2300',
        'name' => 'Refunds Payable',
        'type' => 'liability',
        'subtype' => 'other_current_liability',
        'normal_balance' => 'credit',
    ]);

    return [
        'fiscal_year_id' => $fy->id,
        'period_id' => $period->id,
        'cash' => $cash,
        'agent_advances' => $agentAdvances,
        'vendor_payable' => $vendorPayable,
        'refunds_payable' => $refundsPayable,
    ];
}

/**
 * Inserts a transaction + balanced pair of journal lines directly, bypassing
 * the posting service (acceptable per the widget's test contract). Pass
 * status 'posted' or 'draft'.
 */
function dashboardWidgetsPostLine(
    Company $company,
    array $ledger,
    string $debitAccountId,
    string $creditAccountId,
    float $amount,
    string $status = 'posted',
): void {
    $transactionId = (string) Str::uuid();

    DB::table('acct.transactions')->insert([
        'id' => $transactionId,
        'company_id' => $company->id,
        'transaction_number' => 'JNL-'.Str::random(10),
        'transaction_type' => 'manual',
        'transaction_date' => '2026-08-15',
        'posting_date' => '2026-08-15',
        'fiscal_year_id' => $ledger['fiscal_year_id'],
        'period_id' => $ledger['period_id'],
        'currency' => $company->base_currency,
        'base_currency' => $company->base_currency,
        'status' => $status,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('acct.journal_entries')->insert([
        [
            'id' => (string) Str::uuid(),
            'company_id' => $company->id,
            'transaction_id' => $transactionId,
            'account_id' => $debitAccountId,
            'line_number' => 1,
            'debit_amount' => $amount,
            'credit_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => (string) Str::uuid(),
            'company_id' => $company->id,
            'transaction_id' => $transactionId,
            'account_id' => $creditAccountId,
            'line_number' => 2,
            'debit_amount' => 0,
            'credit_amount' => $amount,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);
}

test('cash_position computes its three lines from posted entries only, excluding an unposted transaction', function () {
    [$company, $owner] = dashboardWidgetsCompany();
    CompanyContext::setContext($company);
    $ledger = dashboardWidgetsLedgerAccounts($company);

    // Posted: cash in 1000, held-for-agents 800, owed-to-vendors 200.
    dashboardWidgetsPostLine($company, $ledger, $ledger['cash']->id, $ledger['agent_advances']->id, 400, 'posted');
    dashboardWidgetsPostLine($company, $ledger, $ledger['cash']->id, $ledger['vendor_payable']->id, 200, 'posted');
    dashboardWidgetsPostLine($company, $ledger, $ledger['cash']->id, $ledger['agent_advances']->id, 400, 'posted');

    // Unposted (draft) — must not be counted.
    dashboardWidgetsPostLine($company, $ledger, $ledger['cash']->id, $ledger['agent_advances']->id, 9999, 'draft');

    $data = (new CashPositionWidget)->resolve($company, $owner, []);
    $lines = collect($data['lines'])->keyBy('label');

    expect($lines['Cash and bank']['amount'])->toBe(1000.0)
        ->and($lines['Held for agents']['amount'])->toBe(800.0)
        ->and($lines['Owed to vendors']['amount'])->toBe(200.0)
        ->and($data['total'])->toBe(1000.0 - 800.0 - 200.0)
        ->and($data['currency'])->toBe($company->base_currency);
});

test('cash_position presents liability lines as positive magnitudes with a minus sign, not negative numbers', function () {
    [$company, $owner] = dashboardWidgetsCompany();
    CompanyContext::setContext($company);
    $ledger = dashboardWidgetsLedgerAccounts($company);

    dashboardWidgetsPostLine($company, $ledger, $ledger['cash']->id, $ledger['agent_advances']->id, 150, 'posted');
    dashboardWidgetsPostLine($company, $ledger, $ledger['cash']->id, $ledger['vendor_payable']->id, 50, 'posted');

    $data = (new CashPositionWidget)->resolve($company, $owner, []);
    $lines = collect($data['lines'])->keyBy('label');

    expect($lines['Cash and bank']['sign'])->toBeNull()
        ->and($lines['Held for agents']['amount'])->toBe(150.0)
        ->and($lines['Held for agents']['sign'])->toBe('−')
        ->and($lines['Owed to vendors']['amount'])->toBe(50.0)
        ->and($lines['Owed to vendors']['sign'])->toBe('−');
});

test('cash_position conclusion goes negative when agent advances plus payables exceed cash, and survives into the payload', function () {
    [$company, $owner] = dashboardWidgetsCompany();
    CompanyContext::setContext($company);
    $ledger = dashboardWidgetsLedgerAccounts($company);

    $suspense = Account::create([
        'company_id' => $company->id,
        'code' => '5900',
        'name' => 'Suspense',
        'type' => 'expense',
        'subtype' => 'operating_expense',
        'normal_balance' => 'debit',
    ]);

    // Cash 100 (funded from suspense so it does not touch the liabilities),
    // held for agents 90, owed to vendors 40 => total = 100 - 90 - 40 = -30.
    dashboardWidgetsPostLine($company, $ledger, $ledger['cash']->id, $suspense->id, 100, 'posted');
    dashboardWidgetsPostLine($company, $ledger, $suspense->id, $ledger['agent_advances']->id, 90, 'posted');
    dashboardWidgetsPostLine($company, $ledger, $suspense->id, $ledger['vendor_payable']->id, 40, 'posted');

    $data = (new CashPositionWidget)->resolve($company, $owner, []);

    expect($data['total'])->toBe(-30.0)
        ->and($data['total'])->toBeLessThan(0);
});

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

    $data = (new DeparturesWidget)->resolve($company, $agentOneUser, []);

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
        'service_type' => VisaVendor::SERVICE_VISA_PROVIDER,
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

    $data = (new CashBookWidget)->resolve($company, $owner, []);

    $rowsByNumber = collect($data['rows'])->keyBy('payment_number');

    expect($rowsByNumber['UPM-IN-1']['in'])->toBe(500.0)
        ->and($rowsByNumber['UPM-IN-1']['out'])->toBeNull()
        ->and($rowsByNumber['UPM-OUT-1']['out'])->toBe(200.0)
        ->and($rowsByNumber['UPM-OUT-1']['in'])->toBeNull()
        ->and($data['totals']['in'])->toBe(500.0)
        ->and($data['totals']['out'])->toBe(200.0)
        ->and($data['total_movements'])->toBe(2);
});

/*
 * The page prop shape, asserted over HTTP.
 *
 * Every other test here calls the resolver or a widget directly, which is
 * why a controller shipping `tabs` while the page expected `dashboard`
 * survived a green suite and broke production instead. A dashboard whose
 * data is assembled from a registry needs at least one test that renders
 * the actual page and reads the actual prop names.
 */
test('the dashboard page ships a single dashboard prop naming its active tab', function () {
    [$company, $owner] = dashboardWidgetsCompany();
    CompanyContext::setContext($company);

    $this->actingAs($owner)
        ->get("/{$company->slug}/umrah")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Umrah/Dashboard/Index')
            ->has('dashboard.tabs', 2)
            ->where('dashboard.activeTab', 'upcoming')
            ->where('dashboard.tabs.0.key', 'upcoming')
            ->where('dashboard.tabs.1.key', 'money')
            ->has('dashboard.tabs.0.widgets.0.data')
            ->where('dashboard.tabs.1.widgets.0.data', null)
        );
});

test('asking for a tab by name makes it the active tab and the one carrying data', function () {
    [$company, $owner] = dashboardWidgetsCompany();
    CompanyContext::setContext($company);

    $this->actingAs($owner)
        ->get("/{$company->slug}/umrah?tab=money")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('dashboard.activeTab', 'money')
            ->has('dashboard.tabs.1.widgets.0.data')
            ->where('dashboard.tabs.0.widgets.0.data', null)
        );
});

test('the departures widget ranks groups yet to travel above travelled groups that still owe', function () {
    [$company, $owner] = dashboardWidgetsCompany();
    CompanyContext::setContext($company);

    $agent = Agent::create([
        'company_id' => $company->id,
        'agent_number' => 'AGT-RANK',
        'name' => 'Ranking Agent',
    ]);

    $make = function (string $number, string $travelDate, float $balance) use ($company, $agent): void {
        VisaGroup::create([
            'company_id' => $company->id,
            'agent_id' => $agent->id,
            'group_number' => $number,
            'name' => $number,
            'status' => VisaGroup::STATUS_PASSPORTS_RECEIVED,
            'travel_date' => $travelDate,
            'passenger_count' => 1,
            'balance' => $balance,
        ]);
    };

    $make('UGR-SOON', now()->addDays(3)->toDateString(), 500);
    $make('UGR-LATER', now()->addDays(30)->toDateString(), 0);
    $make('UGR-FLOWN-OWING', now()->subDays(4)->toDateString(), 900);
    $make('UGR-FLOWN-OLDER-OWING', now()->subDays(40)->toDateString(), 100);
    $make('UGR-FLOWN-SETTLED', now()->subDays(2)->toDateString(), 0);

    $data = (new DeparturesWidget)->resolve($company, $owner, []);

    // Yet to travel first (soonest first), then travelled-and-owing (most
    // recent first). A group that has travelled and settled needs nothing
    // doing, so it is absent entirely.
    expect(collect($data['rows'])->pluck('group_number')->all())->toBe([
        'UGR-SOON',
        'UGR-LATER',
        'UGR-FLOWN-OWING',
        'UGR-FLOWN-OLDER-OWING',
    ]);
});

test('the departures chip names direction in words rather than a signed day count', function () {
    [$company, $owner] = dashboardWidgetsCompany();
    CompanyContext::setContext($company);

    $agent = Agent::create([
        'company_id' => $company->id,
        'agent_number' => 'AGT-CHIP',
        'name' => 'Chip Agent',
    ]);

    VisaGroup::create([
        'company_id' => $company->id, 'agent_id' => $agent->id, 'group_number' => 'UGR-FUTURE', 'name' => 'Future',
        'status' => VisaGroup::STATUS_PASSPORTS_RECEIVED,
        'travel_date' => now()->addDays(6)->toDateString(), 'passenger_count' => 1, 'balance' => 0,
    ]);
    VisaGroup::create([
        'company_id' => $company->id, 'agent_id' => $agent->id, 'group_number' => 'UGR-PAST', 'name' => 'Past',
        'status' => VisaGroup::STATUS_PASSPORTS_RECEIVED,
        'travel_date' => now()->subDays(30)->toDateString(), 'passenger_count' => 1, 'balance' => 250,
    ]);

    $rows = collect((new DeparturesWidget)->resolve($company, $owner, [])['rows'])->keyBy('group_number');

    expect($rows['UGR-FUTURE']['chip'])->toBe('in 6 days')
        ->and($rows['UGR-PAST']['chip'])->toBe('30 days ago')
        ->and($rows['UGR-PAST']['days_until'])->toBeLessThan(0);
});

/*
 * Phase 3 -- Surfacing (docs/contracts/refunds.md). The refund itself already
 * posts to the ledger (Phase 2); these tests are about the dashboard making
 * it visible: a fourth cash_position line, and a decision queue.
 */

function refundWidgetPayload(Agent $agent, float $amount = 200.0): array
{
    return [
        'party_type' => Refund::PARTY_AGENT,
        'party_id' => $agent->id,
        'service' => Refund::SERVICE_OTHER,
        'refund_number' => null,
        'amount' => $amount,
        'currency' => 'SAR',
        'reason' => 'Overpaid on visa package, refunding the excess.',
    ];
}

test('cash_position adds a fourth Refunds owed line from account 2300, subtracted like the other liabilities', function () {
    [$company, $owner] = dashboardWidgetsCompany();
    CompanyContext::setContext($company);
    $ledger = dashboardWidgetsLedgerAccounts($company);

    dashboardWidgetsPostLine($company, $ledger, $ledger['cash']->id, $ledger['agent_advances']->id, 150, 'posted');
    dashboardWidgetsPostLine($company, $ledger, $ledger['cash']->id, $ledger['vendor_payable']->id, 50, 'posted');
    dashboardWidgetsPostLine($company, $ledger, $ledger['cash']->id, $ledger['refunds_payable']->id, 75, 'posted');

    $data = (new CashPositionWidget)->resolve($company, $owner, []);
    $lines = collect($data['lines'])->keyBy('label');

    expect($lines['Refunds owed']['amount'])->toBe(75.0)
        ->and($lines['Refunds owed']['sign'])->toBe('−')
        ->and($data['total'])->toBe((150.0 + 50.0 + 75.0) - 150.0 - 50.0 - 75.0);
});

test('accepting an agent refund moves its amount from held-for-agents to refunds owed, leaving the cash_position total unchanged', function () {
    [$company, $owner] = dashboardWidgetsCompany();
    CompanyContext::setContext($company);
    $ledger = dashboardWidgetsLedgerAccounts($company);

    // The agent already holds a 500 advance, sitting in 2200.
    dashboardWidgetsPostLine($company, $ledger, $ledger['cash']->id, $ledger['agent_advances']->id, 500, 'posted');

    $agent = Agent::create([
        'company_id' => $company->id,
        'agent_number' => 'AGT-RF1',
        'name' => 'Refund Agent',
        'total_paid' => 100000.0,
        'total_receivable' => 0.0,
        'balance' => -100000.0,
    ]);

    $before = (new CashPositionWidget)->resolve($company, $owner, []);
    $beforeLines = collect($before['lines'])->keyBy('label');
    expect($beforeLines['Held for agents']['amount'])->toBe(500.0)
        ->and($beforeLines['Refunds owed']['amount'])->toBe(0.0)
        ->and($before['total'])->toBe(0.0);

    $refund = app(RefundService::class)->request($company->id, refundWidgetPayload($agent, 200), $owner->id);
    app(RefundService::class)->approve($refund, [], $owner->id);

    $after = (new CashPositionWidget)->resolve($company, $owner, []);
    $afterLines = collect($after['lines'])->keyBy('label');

    // Accepting posts Dr 2200 / Cr 2300 (UmrahCoreService::postRefundAccept())
    // -- the 200 leaves "held for agents" and lands in "refunds owed" by
    // construction, so it cannot be counted in both.
    expect($afterLines['Held for agents']['amount'])->toBe(300.0)
        ->and($afterLines['Refunds owed']['amount'])->toBe(200.0)
        // No cash moved and the liability just changed which subtracted line
        // records it -- moving an amount between two lines that are both
        // subtracted from the total cannot move the total.
        ->and($after['total'])->toBe($before['total']);
});

test('settling an accepted refund in cash leaves the cash_position total unchanged, because the liability it pays off was already subtracted at acceptance', function () {
    [$company, $owner] = dashboardWidgetsCompany();
    CompanyContext::setContext($company);
    $ledger = dashboardWidgetsLedgerAccounts($company);

    dashboardWidgetsPostLine($company, $ledger, $ledger['cash']->id, $ledger['agent_advances']->id, 500, 'posted');

    $agent = Agent::create([
        'company_id' => $company->id,
        'agent_number' => 'AGT-RF2',
        'name' => 'Refund Agent Two',
        'total_paid' => 100000.0,
        'total_receivable' => 0.0,
        'balance' => -100000.0,
    ]);

    $refund = app(RefundService::class)->request($company->id, refundWidgetPayload($agent, 200), $owner->id);
    app(RefundService::class)->approve($refund, [], $owner->id);

    $accepted = (new CashPositionWidget)->resolve($company, $owner, []);

    app(RefundService::class)->settle($refund->fresh(), [
        'settlement_method' => Refund::SETTLEMENT_CASH,
        'account_id' => $ledger['cash']->id,
        'date' => '2026-08-20',
    ], $owner->id);

    $settled = (new CashPositionWidget)->resolve($company, $owner, []);
    $settledLines = collect($settled['lines'])->keyBy('label');

    // Settling in cash posts Dr 2300 / Cr cash: cash and bank drops by 200
    // and refunds owed drops by the same 200 -- a subtracted liability paid
    // off with tracked cash is a wash for the total, not a further
    // reduction of it. The 200 already left "what's actually yours" the
    // moment the refund was accepted; settlement only changes its form (an
    // unpaid liability becomes cash that has gone out the door), it does
    // not subtract it a second time.
    expect($settledLines['Cash and bank']['amount'])->toBe(300.0)
        ->and($settledLines['Refunds owed']['amount'])->toBe(0.0)
        ->and($settled['total'])->toBe($accepted['total']);
});

test('the refunds awaiting decision widget lists a requested refund and excludes every other status', function () {
    [$company, $owner] = dashboardWidgetsCompany();
    CompanyContext::setContext($company);
    $ledger = dashboardWidgetsLedgerAccounts($company);

    $agent = Agent::create([
        'company_id' => $company->id,
        'agent_number' => 'AGT-AWD',
        'name' => 'Awaiting Decision Agent',
        'total_paid' => 100000.0,
        'total_receivable' => 0.0,
        'balance' => -100000.0,
    ]);

    $requested = app(RefundService::class)->request($company->id, refundWidgetPayload($agent, 150), $owner->id);

    $accepted = app(RefundService::class)->request($company->id, refundWidgetPayload($agent, 100), $owner->id);
    app(RefundService::class)->approve($accepted, [], $owner->id);

    $rejected = app(RefundService::class)->request($company->id, refundWidgetPayload($agent, 50), $owner->id);
    app(RefundService::class)->reject($rejected, 'Not valid.', $owner->id);

    $refunded = app(RefundService::class)->request($company->id, refundWidgetPayload($agent, 80), $owner->id);
    app(RefundService::class)->approve($refunded, [], $owner->id);
    app(RefundService::class)->settle($refunded->fresh(), [
        'settlement_method' => Refund::SETTLEMENT_CASH,
        'account_id' => $ledger['cash']->id,
        'date' => '2026-08-20',
    ], $owner->id);

    $credited = app(RefundService::class)->request($company->id, refundWidgetPayload($agent, 60), $owner->id);
    app(RefundService::class)->approve($credited, [], $owner->id);
    app(RefundService::class)->settle($credited->fresh(), [
        'settlement_method' => Refund::SETTLEMENT_CREDIT,
    ], $owner->id);

    $cancelled = app(RefundService::class)->request($company->id, refundWidgetPayload($agent, 40), $owner->id);
    app(RefundService::class)->approve($cancelled, [], $owner->id);
    app(RefundService::class)->cancel($cancelled->fresh(), 'Approved in error.', $owner->id);

    $data = (new RefundsAwaitingDecisionWidget)->resolve($company, $owner, []);

    expect(collect($data['rows'])->pluck('id')->all())->toBe([$requested->id])
        ->and($data['rows'][0]['reason'])->toBe('Overpaid on visa package, refunding the excess.')
        ->and($data['rows'][0]['amount'])->toBe(150.0)
        ->and($data['rows'][0]['party_name'])->toBe('Awaiting Decision Agent')
        ->and($data['rows'][0]['requested_by'])->toBe($owner->name);
});

test('the refunds awaiting decision widget lists the oldest request first', function () {
    [$company, $owner] = dashboardWidgetsCompany();
    CompanyContext::setContext($company);
    dashboardWidgetsLedgerAccounts($company);

    $agent = Agent::create([
        'company_id' => $company->id,
        'agent_number' => 'AGT-ORD',
        'name' => 'Order Agent',
        'total_paid' => 100000.0,
        'total_receivable' => 0.0,
        'balance' => -100000.0,
    ]);

    $newer = app(RefundService::class)->request($company->id, refundWidgetPayload($agent, 100), $owner->id);
    $older = app(RefundService::class)->request($company->id, refundWidgetPayload($agent, 100), $owner->id);
    $older->forceFill(['requested_at' => now()->subDays(5)])->saveQuietly();

    $data = (new RefundsAwaitingDecisionWidget)->resolve($company, $owner, []);

    expect(collect($data['rows'])->pluck('id')->all())->toBe([$older->id, $newer->id]);
});

test('a user without umrah.refund.approve does not receive the refunds awaiting decision widget', function () {
    [$company, $owner] = dashboardWidgetsCompany();
    [$operationsUser] = dashboardWidgetsAgentWithRole($company, 'operations');
    CompanyContext::setContext($company);

    expect(app(WidgetRegistry::class)->has('umrah.refunds_awaiting_decision'))->toBeTrue();

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
                    ['key' => 'umrah.refunds_awaiting_decision', 'span' => 12, 'options' => []],
                ],
            ],
        ],
    ]);

    $tabs = app(DashboardLayoutResolver::class)->resolve($operationsUser, $company, 'umrah');

    expect(collect($tabs[0]['widgets'])->pluck('key')->all())->toBe(['umrah.departures']);
});
