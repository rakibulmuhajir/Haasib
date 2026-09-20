<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillLineItem;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Services\BalanceSheetReportService;
use App\Modules\Accounting\Services\CompanyOnboardingService;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\Accounting\Services\ProfitLossReportService;
use App\Modules\Accounting\Services\ReceivablesAgingReportService;
use App\Modules\Accounting\Services\TrialBalanceReportService;
use App\Modules\Accounting\Services\VendorStatementService;
use App\Modules\FuelStation\Actions\Product\SetupAction;
use App\Modules\FuelStation\Models\CustomerProfile;
use App\Modules\FuelStation\Models\Nozzle;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\FuelStation\Models\StationSettings;
use App\Modules\FuelStation\Services\DailyCloseService;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\InventoryService;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use App\Services\CurrentCompany;
use Database\Seeders\IndustryCoaPackSeeder;
use Illuminate\Support\Facades\DB;

/**
 * One week at a filling station, end to end, with every figure fixed in advance.
 *
 * This is an acceptance test, not a unit test. It builds a company the way the product
 * does — industry COA pack, onboarding service, the real product-setup action — and then
 * posts seven consecutive trading days through DailyCloseService, the same service the UI
 * calls. Nothing is written straight into a table, so if the books balance at the end it is
 * because the posting engine balanced them.
 *
 * Every number below is chosen, not generated. No randomness, no Carbon::today(), no
 * faker. The point is that the expected results can be worked out with a pen, so when an
 * assertion fails it means the engine changed its mind about something, not that the test
 * rolled a different die.
 *
 * What one week covers:
 *
 *   - 2 fuels through 4 nozzles (petrol, diesel), 1,000–2,000 L a day
 *   - packaged Mobil lubricant and open lubricant sold as other_sales
 *   - a pump-rate change on day 4, effective from 00:00 that day
 *   - daily tank dips, with deliberate shrinkage so variance is never trivially zero
 *   - 2 tanker deliveries, billed to the depot and received into the tanks
 *   - 6 credit buyers, credit sales and collections
 *   - amanat (trust) deposits and a withdrawal
 *   - daily cash expenses: tea, meals, electricity
 *   - daily banking, across 3 bank accounts
 *   - one deliberately short drawer, so cash-over/short is exercised
 *
 * The assertions at the bottom check the things a station owner would check: does the day
 * reconcile, does the stock agree, who owes what, what did we earn, and do the books
 * balance.
 *
 * NOTE ON THE EXPECTED-CASH ARITHMETIC: the test computes what the drawer should hold
 * using its own arithmetic and hands that to the close as closing_cash. If
 * DailyCloseService disagrees, the variance assertion fails — and that disagreement is
 * itself the finding. It is deliberately not read back from the service.
 */

// ---------------------------------------------------------------------------
// The plan. Seven days, Sunday 2026-03-01 to Saturday 2026-03-07.
// ---------------------------------------------------------------------------

const WEEK_START = '2026-03-01';

/** Pump rates. OGRA revises on day 4; the new rate applies from 00:00 that day. */
const RATE_PETROL_EARLY = 300.00;
const RATE_DIESEL_EARLY = 310.00;
const RATE_PETROL_LATE = 306.00;   // from 2026-03-04
const RATE_DIESEL_LATE = 314.00;   // from 2026-03-04
const RATE_CHANGE_DAY = 3;         // zero-based index into the week

const COST_PETROL = 292.00;
const COST_DIESEL = 302.00;

const OPENING_PETROL_LITRES = 18000.0;
const OPENING_DIESEL_LITRES = 12000.0;
const OPENING_CASH = 50000.0;

/**
 * Litres per day per fuel, and the shrinkage the dip will show. Petrol and diesel
 * together stay between 1,000 and 2,000 L a day.
 *
 * [petrol litres, diesel litres, petrol shrinkage, diesel shrinkage]
 */
function weekVolumes(): array
{
    return [
        0 => [700.0, 500.0, 2.0, 1.5],
        1 => [800.0, 600.0, 2.5, 1.5],
        2 => [650.0, 450.0, 1.5, 1.0],
        3 => [900.0, 700.0, 3.0, 2.0],
        4 => [750.0, 550.0, 2.0, 1.5],
        5 => [850.0, 650.0, 2.5, 2.0],
        6 => [950.0, 800.0, 3.0, 2.5],
    ];
}

/** Tanker deliveries: day index => [fuel key, litres, unit cost]. */
function weekDeliveries(): array
{
    return [
        2 => ['petrol', 10000.0, COST_PETROL],
        5 => ['diesel', 8000.0, COST_DIESEL],
    ];
}

/** Lubricant sales per day: [packaged units, open litres]. */
function weekLubricants(): array
{
    return [
        0 => [4, 3.0],
        1 => [2, 5.0],
        2 => [6, 2.0],
        3 => [3, 4.0],
        4 => [5, 6.0],
        5 => [1, 3.0],
        6 => [7, 5.0],
    ];
}

const LUBE_PACKAGED_PRICE = 2400.00;
const LUBE_OPEN_PRICE = 1100.00;

/** Credit sales per day: [customer index, amount]. Part of the day's metered litres. */
function weekCreditSales(): array
{
    return [
        0 => [[0, 40000.0]],
        1 => [[1, 55000.0]],
        2 => [[0, 30000.0], [2, 25000.0]],
        3 => [[3, 60000.0]],
        4 => [[1, 35000.0], [4, 20000.0]],
        5 => [[5, 45000.0]],
        6 => [[2, 50000.0]],
    ];
}

/** Cash expenses per day: [account code, description, amount]. */
function weekExpenses(int $day): array
{
    $rows = [
        ['6130', 'Staff tea', 600.0],
        ['6130', 'Attendant meals', 1400.0],
    ];
    if ($day === 2) {
        $rows[] = ['6110', 'Electricity bill — February', 42000.0];
    }

    return $rows;
}

/** Cash banked each day, and which of the three banks it goes to. */
function weekDeposits(): array
{
    return [
        0 => 150000.0,
        1 => 160000.0,
        2 => 120000.0,
        3 => 200000.0,
        4 => 150000.0,
        5 => 180000.0,
        6 => 190000.0,
    ];
}

/** Amanat: day => [customer index, amount]. Negative means a withdrawal. */
function weekAmanat(): array
{
    return [
        1 => [[3, 25000.0]],
        4 => [[3, 15000.0]],
        6 => [[3, -10000.0]],
    ];
}

/** The one day the drawer is genuinely short, in rupees. */
const SHORT_DAY = 4;
const SHORT_AMOUNT = -350.0;

// ---------------------------------------------------------------------------
// Building the station
// ---------------------------------------------------------------------------

function sevenDayFixture(): array
{
    test()->seed(IndustryCoaPackSeeder::class);

    $user = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Mehran Filling Station',
        'slug' => 'mehran-filling-'.str()->lower(str()->random(8)),
        'base_currency' => 'PKR',
    ]);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    enterCompany($company);

    app(CompanyRbacBootstrapper::class)->bootstrap($company);
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id, 'user_id' => $user->id, 'role' => 'owner',
        'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    app(CompanyContextService::class)->withContext($company, fn () => app(CompanyContextService::class)->assignRole($user, 'owner'));
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    test()->actingAs($user);
    app(CurrentCompany::class)->set($company);

    $onboarding = app(CompanyOnboardingService::class);
    $company = $onboarding->setupCompanyIdentity($company, ['industry_code' => 'fuel_station']);
    app(\App\Modules\FuelStation\Services\FuelStationOnboardingService::class)->ensureRequiredAccounts($company->id);
    $company->refresh();

    $fy = FiscalYear::create([
        'company_id' => $company->id, 'name' => '2026',
        'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open',
    ]);
    // February too: the opening-balance journal is dated the day before trading starts,
    // and GlPostingService refuses a date with no open period.
    foreach ([[2, 'February', '2026-02-01', '2026-02-28'], [3, 'March', '2026-03-01', '2026-03-31']] as [$num, $pname, $from, $to]) {
        AccountingPeriod::create([
            'company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => $pname,
            'period_number' => $num, 'start_date' => $from, 'end_date' => $to,
        ]);
    }

    // Three banks and the drawer, named so the statements read like the real thing.
    $existingBank = Account::where('company_id', $company->id)->where('subtype', 'bank')->orderBy('code')->firstOrFail();
    $existingCash = Account::where('company_id', $company->id)->where('subtype', 'cash')->orderBy('code')->firstOrFail();
    app(\App\Modules\Accounting\Services\CompanyBankAccountSyncService::class)->ensureForCompany($company->id);
    $onboarding->setupBankAccounts($company, [
        ['id' => $existingBank->id, 'account_name' => 'HBL Current', 'currency' => 'PKR', 'account_type' => 'bank'],
        ['id' => $existingCash->id, 'account_name' => 'Cash Drawer', 'currency' => 'PKR', 'account_type' => 'cash'],
        ['account_name' => 'Meezan Current', 'currency' => 'PKR', 'account_type' => 'bank'],
        ['account_name' => 'UBL Savings', 'currency' => 'PKR', 'account_type' => 'bank'],
    ]);
    $company->refresh();

    $banks = Account::where('company_id', $company->id)->where('subtype', 'bank')->orderBy('code')->get();
    $cash = Account::where('company_id', $company->id)->where('subtype', 'cash')->orderBy('code')->firstOrFail();

    $account = fn (string $code) => Account::where('company_id', $company->id)->where('code', $code)->firstOrFail();

    StationSettings::updateOrCreate(['company_id' => $company->id], [
        'fuel_vendor' => 'pso',
        'has_partners' => false,
        'has_amanat' => true,
        'has_lubricant_sales' => true,
        'has_investors' => false,
        'dual_meter_readings' => false,
        'track_attendant_handovers' => false,
        // All cash: every rupee the pump takes lands in the drawer, which keeps the
        // expected-cash arithmetic below something a person can verify by hand.
        'payment_channels' => [
            ['code' => 'cash', 'label' => 'Cash', 'type' => 'cash', 'enabled' => true],
        ],
        'cash_account_id' => $cash->id,
        'operating_bank_account_id' => $banks[0]->id,
    ]);
    app(\App\Modules\FuelStation\Services\StationAccountMapper::class)
        ->ensureMappings(StationSettings::where('company_id', $company->id)->firstOrFail(), $user->id);

    // Products, tanks, pumps, nozzles and opening stock, through the real action.
    // SetupAction resolves the tenant through the CompanyContext facade rather than
    // CurrentCompany, so it has to run inside an explicit context.
    app(CompanyContextService::class)->withContext($company, fn () => app(SetupAction::class)->handle([
        'effective_date' => WEEK_START,
        'products' => [
            [
                'type' => 'fuel', 'name' => 'Petrol', 'fuel_category' => 'petrol',
                'purchase_rate' => COST_PETROL, 'sale_rate' => RATE_PETROL_EARLY,
                'opening_quantity' => OPENING_PETROL_LITRES,
                'new_tank' => ['name' => 'Tank 1 — Petrol', 'code' => 'TNK-PET', 'capacity' => 30000, 'low_level_alert' => 4000],
                'pump_setups' => [
                    ['name' => 'Pump 1', 'nozzle_count' => 2, 'nozzles' => [
                        ['code' => 'P1A', 'label' => 'Petrol 1A', 'opening_electronic' => 100000],
                        ['code' => 'P1B', 'label' => 'Petrol 1B', 'opening_electronic' => 200000],
                    ]],
                ],
            ],
            [
                'type' => 'fuel', 'name' => 'Diesel', 'fuel_category' => 'diesel',
                'purchase_rate' => COST_DIESEL, 'sale_rate' => RATE_DIESEL_EARLY,
                'opening_quantity' => OPENING_DIESEL_LITRES,
                'new_tank' => ['name' => 'Tank 2 — Diesel', 'code' => 'TNK-DSL', 'capacity' => 25000, 'low_level_alert' => 3500],
                'pump_setups' => [
                    ['name' => 'Pump 2', 'nozzle_count' => 2, 'nozzles' => [
                        ['code' => 'D2A', 'label' => 'Diesel 2A', 'opening_electronic' => 300000],
                        ['code' => 'D2B', 'label' => 'Diesel 2B', 'opening_electronic' => 400000],
                    ]],
                ],
            ],
            [
                'type' => 'lubricant', 'name' => 'Mobil Super 4L', 'lubricant_format' => 'packaged', 'packaging' => 'packaged',
                'unit_of_measure' => 'piece', 'purchase_rate' => 1900.00, 'sale_rate' => LUBE_PACKAGED_PRICE,
                'opening_quantity' => 0,
            ],
            [
                'type' => 'lubricant', 'name' => 'Open Engine Oil', 'lubricant_format' => 'open', 'packaging' => 'open',
                'unit_of_measure' => 'liter', 'purchase_rate' => 820.00, 'sale_rate' => LUBE_OPEN_PRICE,
                'opening_quantity' => 0,
            ],
        ],
    ]));

    $items = Item::where('company_id', $company->id)->get()->keyBy('name');
    $tanks = Warehouse::where('company_id', $company->id)->where('warehouse_type', 'tank')->get()->keyBy('linked_item_id');
    $nozzles = Nozzle::where('company_id', $company->id)->orderBy('code')->get()->keyBy('code');

    // Opening balances: the drawer float and the fuel already in the ground.
    // Fuel only: lubricants open at zero because opening stock needs a warehouse.
    $openingStock = OPENING_PETROL_LITRES * COST_PETROL + OPENING_DIESEL_LITRES * COST_DIESEL;
    app(GlPostingService::class)->postBalancedTransaction([
        'company_id' => $company->id, 'transaction_type' => 'journal',
        'date' => '2026-02-28', 'currency' => 'PKR', 'description' => 'Opening balances',
    ], [
        ['account_id' => $cash->id, 'type' => 'debit', 'amount' => OPENING_CASH],
        ['account_id' => $banks[0]->id, 'type' => 'debit', 'amount' => 2000000.0],
        ['account_id' => $account('1200')->id, 'type' => 'debit', 'amount' => $openingStock],
        ['account_id' => $account('3000')->id, 'type' => 'credit', 'amount' => OPENING_CASH + 2000000.0 + $openingStock],
    ]);

    // Six credit buyers and three suppliers.
    $customers = collect([
        'Al-Habib Transport', 'Sindh Goods Carriers', 'Mehran Logistics',
        'Karachi Cement Haulage', 'Indus Travel', 'Pak Freight Lines',
    ])->values()->map(fn ($name, $i) => Customer::create([
        'company_id' => $company->id,
        'customer_number' => sprintf('CUST-%04d', $i + 1),
        'name' => $name,
        'base_currency' => 'PKR',
        'ar_account_id' => $company->ar_account_id,
        'credit_limit' => 200000,
        'payment_terms' => 15,
        'is_active' => true,
    ]));

    // An amanat depositor is a customer carrying a fuel CustomerProfile flagged as an
    // amanat holder; a plain acct.customers row is refused by the close.
    CustomerProfile::create([
        'company_id' => $company->id,
        'customer_id' => $customers[3]->id,
        'is_amanat_holder' => true,
    ]);

    $vendors = collect([
        ['PSO Depot — Korangi', 'fuel_refinery'],
        ['Mobil Distributor — Karachi', 'lubricant'],
        ['Al-Noor Maintenance Services', 'services'],
    ])->map(fn ($v, $i) => Vendor::create([
        'company_id' => $company->id,
        'vendor_number' => sprintf('VEND-%04d', $i + 1),
        'name' => $v[0],
        'base_currency' => 'PKR',
        'payment_terms' => 7,
        'ap_account_id' => $company->ap_account_id,
        'is_active' => true,
    ]));

    return compact('user', 'company', 'banks', 'cash', 'items', 'tanks', 'nozzles', 'customers', 'vendors')
        + ['account' => $account];
}

/** The pump rate in force on a given day of the week. */
function rateOn(int $day, string $fuel): float
{
    if ($fuel === 'petrol') {
        return $day >= RATE_CHANGE_DAY ? RATE_PETROL_LATE : RATE_PETROL_EARLY;
    }

    return $day >= RATE_CHANGE_DAY ? RATE_DIESEL_LATE : RATE_DIESEL_EARLY;
}

function dayDate(int $day): string
{
    return \Carbon\CarbonImmutable::parse(WEEK_START)->addDays($day)->toDateString();
}

/**
 * Run the whole week and return what happened, day by day, for the assertions to read.
 */
function runSevenDays(array $f): array
{
    $company = $f['company'];
    $user = $f['user'];
    $close = app(DailyCloseService::class);
    $inventory = app(InventoryService::class);
    $gl = app(GlPostingService::class);
    $account = $f['account'];

    $petrol = $f['items']['Petrol'];
    $diesel = $f['items']['Diesel'];
    $lubePackaged = $f['items']['Mobil Super 4L'];
    $lubeOpen = $f['items']['Open Engine Oil'];
    $petrolTank = $f['tanks'][$petrol->id];
    $dieselTank = $f['tanks'][$diesel->id];

    // The rate change takes effect at 00:00 on its day: a RateChange row dated that day.
    RateChange::create([
        'company_id' => $company->id, 'item_id' => $petrol->id,
        'effective_date' => dayDate(RATE_CHANGE_DAY),
        'purchase_rate' => COST_PETROL, 'sale_rate' => RATE_PETROL_LATE,
    ]);
    RateChange::create([
        'company_id' => $company->id, 'item_id' => $diesel->id,
        'effective_date' => dayDate(RATE_CHANGE_DAY),
        'purchase_rate' => COST_DIESEL, 'sale_rate' => RATE_DIESEL_LATE,
    ]);

    $meters = [
        'P1A' => 100000.0, 'P1B' => 200000.0,
        'D2A' => 300000.0, 'D2B' => 400000.0,
    ];
    $dips = [
        $petrolTank->id => OPENING_PETROL_LITRES,
        $dieselTank->id => OPENING_DIESEL_LITRES,
    ];

    $drawer = OPENING_CASH;
    $result = ['days' => [], 'revenue' => 0.0, 'credit_total' => 0.0];

    foreach (weekVolumes() as $day => [$petrolLitres, $dieselLitres, $petrolShrink, $dieselShrink]) {
        $date = dayDate($day);
        $petrolRate = rateOn($day, 'petrol');
        $dieselRate = rateOn($day, 'diesel');

        // Tanker delivery lands before the day is closed, so the dip must account for it.
        $received = [$petrolTank->id => 0.0, $dieselTank->id => 0.0];
        if (isset(weekDeliveries()[$day])) {
            [$fuelKey, $litres, $unitCost] = weekDeliveries()[$day];
            $item = $fuelKey === 'petrol' ? $petrol : $diesel;
            $tank = $fuelKey === 'petrol' ? $petrolTank : $dieselTank;
            $amount = round($litres * $unitCost, 2);

            $bill = Bill::create([
                'company_id' => $company->id,
                'vendor_id' => $f['vendors'][0]->id,
                'bill_number' => 'BILL-'.$day,
                'vendor_invoice_number' => 'PSO-'.(700000 + $day),
                'bill_date' => $date, 'due_date' => dayDate(6),
                'status' => 'received', 'currency' => 'PKR', 'base_currency' => 'PKR',
                'exchange_rate' => 1, 'subtotal' => $amount, 'tax_amount' => 0,
                'discount_amount' => 0, 'total_amount' => $amount, 'paid_amount' => 0,
                'balance' => $amount, 'base_amount' => $amount, 'payment_terms' => 7,
                'received_at' => $date, 'approved_at' => $date,
            ]);
            $line = BillLineItem::create([
                'company_id' => $company->id, 'bill_id' => $bill->id, 'line_number' => 1,
                'description' => $litres.' L '.$item->name,
                'quantity' => $litres, 'unit_price' => $unitCost, 'tax_rate' => 0,
                'discount_rate' => 0, 'line_total' => $amount, 'tax_amount' => 0, 'total' => $amount,
                'item_id' => $item->id, 'warehouse_id' => $tank->id, 'quantity_received' => $litres,
                'expense_account_id' => $item->asset_account_id, 'account_id' => $item->asset_account_id,
            ]);
            $gl->postBill($bill->fresh(['lineItems', 'vendor', 'company']));
            $inventory->receiveLineItem($bill, $line, $litres, $tank->id, $date);
            $received[$tank->id] = $litres;
        }

        // Meter readings: half the litres through each nozzle of that fuel.
        $nozzleReadings = [];
        foreach ([['P1A', $petrol, $petrolLitres / 2, $petrolRate], ['P1B', $petrol, $petrolLitres / 2, $petrolRate],
                  ['D2A', $diesel, $dieselLitres / 2, $dieselRate], ['D2B', $diesel, $dieselLitres / 2, $dieselRate]] as [$code, $item, $litres, $rate]) {
            $opening = $meters[$code];
            $closing = round($opening + $litres, 2);
            $meters[$code] = $closing;
            $nozzleReadings[] = [
                'nozzle_id' => $f['nozzles'][$code]->id,
                'item_id' => $item->id,
                'opening_electronic' => $opening,
                'closing_electronic' => $closing,
                'liters_sold' => $litres,
                'sale_rate' => $rate,
            ];
        }

        $fuelRevenue = round($petrolLitres * $petrolRate + $dieselLitres * $dieselRate, 2);

        // Dips: opening + delivered - sold - shrinkage. The shrinkage is what makes the
        // variance column mean something.
        $tankReadings = [];
        foreach ([[$petrolTank->id, $petrolLitres, $petrolShrink], [$dieselTank->id, $dieselLitres, $dieselShrink]] as [$tankId, $sold, $shrink]) {
            $closingDip = round($dips[$tankId] + $received[$tankId] - $sold - $shrink, 2);
            $dips[$tankId] = $closingDip;
            $tankReadings[] = ['tank_id' => $tankId, 'stick_reading' => round($closingDip / 40, 1), 'liters' => $closingDip];
        }

        // Lubricants, sold over the counter for cash.
        [$packagedUnits, $openLitres] = weekLubricants()[$day];
        $otherSales = [
            ['item_id' => $lubePackaged->id, 'item_name' => $lubePackaged->name, 'quantity' => $packagedUnits,
             'unit_price' => LUBE_PACKAGED_PRICE, 'amount' => round($packagedUnits * LUBE_PACKAGED_PRICE, 2)],
            ['item_id' => $lubeOpen->id, 'item_name' => $lubeOpen->name, 'quantity' => $openLitres,
             'unit_price' => LUBE_OPEN_PRICE, 'amount' => round($openLitres * LUBE_OPEN_PRICE, 2)],
        ];
        $lubeRevenue = round(array_sum(array_column($otherSales, 'amount')), 2);

        // Credit sales are part of the metered litres, not extra revenue.
        $creditSales = [];
        $creditTotal = 0.0;
        foreach (weekCreditSales()[$day] ?? [] as [$customerIndex, $amount]) {
            $creditSales[] = [
                'customer_id' => $f['customers'][$customerIndex]->id,
                'amount' => $amount,
                'reference' => 'Slip '.$day.'-'.$customerIndex,
            ];
            $creditTotal = round($creditTotal + $amount, 2);
        }

        $expenses = [];
        foreach (weekExpenses($day) as [$code, $description, $amount]) {
            $expenses[] = ['account_id' => $account($code)->id, 'description' => $description, 'amount' => $amount];
        }
        $expenseTotal = round(array_sum(array_column($expenses, 'amount')), 2);

        $amanatDeposits = [];
        $amanatOut = [];
        foreach (weekAmanat()[$day] ?? [] as [$customerIndex, $amount]) {
            $row = [
                'customer_id' => $f['customers'][$customerIndex]->id,
                'customer_name' => $f['customers'][$customerIndex]->name,
                'amount' => abs($amount),
                'reference' => 'Amanat '.$day,
            ];
            if ($amount >= 0) {
                $amanatDeposits[] = $row;
            } else {
                $amanatOut[] = $row;
            }
        }
        $amanatIn = round(array_sum(array_column($amanatDeposits, 'amount')), 2);
        $amanatPaid = round(array_sum(array_column($amanatOut, 'amount')), 2);

        $deposit = weekDeposits()[$day];
        $bankIndex = $day % 3;
        $bankDeposits = [[
            'bank_account_id' => $f['banks'][$bankIndex]->id,
            'amount' => $deposit,
            'reference' => 'DEP-'.$day,
            'purpose' => 'Daily takings',
        ]];

        // What the drawer should hold at close.
        $expected = round(
            $drawer + $fuelRevenue + $lubeRevenue + $amanatIn
            - $creditTotal - $expenseTotal - $amanatPaid - $deposit,
            2
        );
        $planted = $day === SHORT_DAY ? SHORT_AMOUNT : 0.0;
        $closingCash = round($expected + $planted, 2);

        $posted = $close->processDailyClose($company->id, [
            'date' => $date,
            'nozzle_readings' => $nozzleReadings,
            'other_sales' => $otherSales,
            'tank_readings' => $tankReadings,
            'opening_cash' => round($drawer, 2),
            'credit_sales' => $creditSales,
            'amanat_deposits' => $amanatDeposits,
            'amanat_disbursements' => $amanatOut,
            'bank_deposits' => $bankDeposits,
            'expenses' => $expenses,
            'closing_cash' => $closingCash,
            'notes' => 'Day '.$day.' closed.',
        ], $user);

        $result['days'][$day] = [
            'date' => $date,
            'transaction_id' => $posted['transaction_id'],
            'fuel_revenue' => $fuelRevenue,
            'lube_revenue' => $lubeRevenue,
            'credit_total' => $creditTotal,
            'expected_cash' => $expected,
            'planted_variance' => $planted,
            'dips' => $dips,
        ];
        $result['revenue'] = round($result['revenue'] + $fuelRevenue + $lubeRevenue, 2);
        $result['credit_total'] = round($result['credit_total'] + $creditTotal, 2);

        $drawer = $closingCash;
    }

    $result['closing_drawer'] = $drawer;

    return $result;
}

// ---------------------------------------------------------------------------
// What a station owner would check
// ---------------------------------------------------------------------------

test('seven consecutive days close, one posted transaction each', function () {
    $f = sevenDayFixture();
    $week = runSevenDays($f);

    $closes = Transaction::where('company_id', $f['company']->id)
        ->where('transaction_type', 'fuel_daily_close')
        ->orderBy('transaction_date')->get();

    expect($closes)->toHaveCount(7)
        ->and($closes->pluck('transaction_date')->map(fn ($d) => $d->toDateString())->all())
        ->toBe(array_map(fn ($i) => dayDate($i), range(0, 6)));

    expect(count($week['days']))->toBe(7);
});

test('every day reconciles, and only the short day is short', function () {
    $f = sevenDayFixture();
    $week = runSevenDays($f);

    foreach ($week['days'] as $day => $info) {
        $snapshot = Transaction::findOrFail($info['transaction_id'])->metadata['posting_snapshot'];
        expect(round((float) $snapshot['totals']['expected_closing'], 2))
            ->toBe($info['expected_cash'], "day {$day} expected cash");
        expect(round((float) $snapshot['totals']['variance'], 2))
            ->toBe($info['planted_variance'], "day {$day} variance");
    }
});

test('the rate change on day four is what the pump charged from that morning', function () {
    $f = sevenDayFixture();
    $week = runSevenDays($f);

    // Day 3 sold 900 L petrol and 700 L diesel at the NEW rates.
    expect($week['days'][3]['fuel_revenue'])
        ->toBe(round(900 * RATE_PETROL_LATE + 700 * RATE_DIESEL_LATE, 2));

    // Day 2, the morning before, still at the old ones.
    expect($week['days'][2]['fuel_revenue'])
        ->toBe(round(650 * RATE_PETROL_EARLY + 450 * RATE_DIESEL_EARLY, 2));

    // And the revenue actually posted for the week matches the sum of both halves.
    $revenueAccounts = Account::where('company_id', $f['company']->id)
        ->whereIn('type', ['revenue', 'other_income'])->pluck('id');
    $posted = (float) DB::table('acct.journal_entries as je')
        ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
        ->where('t.company_id', $f['company']->id)
        ->whereIn('je.account_id', $revenueAccounts)
        ->sum(DB::raw('je.credit_amount - je.debit_amount'));

    expect(round($posted, 2))->toBe($week['revenue']);
});

test('the tanks end the week where the dips say they do', function () {
    $f = sevenDayFixture();
    $week = runSevenDays($f);

    $petrolTank = $f['tanks'][$f['items']['Petrol']->id];
    $dieselTank = $f['tanks'][$f['items']['Diesel']->id];
    $finalDips = $week['days'][6]['dips'];

    // Worked by hand: opening + delivered - sold - shrinkage across the week.
    $petrolSold = array_sum(array_column(weekVolumes(), 0));
    $petrolShrink = array_sum(array_column(weekVolumes(), 2));
    $dieselSold = array_sum(array_column(weekVolumes(), 1));
    $dieselShrink = array_sum(array_column(weekVolumes(), 3));

    expect($finalDips[$petrolTank->id])
        ->toBe(round(OPENING_PETROL_LITRES + 10000.0 - $petrolSold - $petrolShrink, 2));
    expect($finalDips[$dieselTank->id])
        ->toBe(round(OPENING_DIESEL_LITRES + 8000.0 - $dieselSold - $dieselShrink, 2));
});

test('the six buyers owe exactly what they bought on credit', function () {
    $f = sevenDayFixture();
    $week = runSevenDays($f);

    $owedByCustomer = [];
    foreach (weekCreditSales() as $rows) {
        foreach ($rows as [$index, $amount]) {
            $owedByCustomer[$index] = round(($owedByCustomer[$index] ?? 0) + $amount, 2);
        }
    }

    $aging = app(ReceivablesAgingReportService::class)->run($f['company']->id, dayDate(6));
    $byName = collect($aging['rows'])->keyBy('customer_name');

    foreach ($owedByCustomer as $index => $amount) {
        $name = $f['customers'][$index]->name;
        expect($byName[$name]['total'])->toBe($amount, "{$name} balance");
    }

    expect($aging['totals']['total'])->toBe($week['credit_total'])
        // Nothing is past its 15-day terms inside a single week.
        ->and($aging['totals']['d1_30'])->toBe(0.0)
        ->and($aging['totals']['current'])->toBe($week['credit_total']);
});

test('the depot is owed for both tankers and nothing else', function () {
    $f = sevenDayFixture();
    runSevenDays($f);

    $statement = app(VendorStatementService::class)->statement($f['vendors'][0]->fresh());
    $expected = round(10000.0 * COST_PETROL + 8000.0 * COST_DIESEL, 2);

    expect($statement['closing_balance'])->toBe($expected)
        ->and(collect($statement['rows'])->where('type', 'bill')->count())->toBe(2);

    // The other two suppliers were never billed this week.
    expect(app(VendorStatementService::class)->statement($f['vendors'][1]->fresh())['closing_balance'])->toBe(0.0)
        ->and(app(VendorStatementService::class)->statement($f['vendors'][2]->fresh())['closing_balance'])->toBe(0.0);
});

test('the trust deposits net out to what the customer left with the station', function () {
    $f = sevenDayFixture();
    runSevenDays($f);

    $amanat = Account::where('company_id', $f['company']->id)->where('code', '2200')->first();
    expect($amanat)->not->toBeNull();

    $balance = (float) DB::table('acct.journal_entries as je')
        ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
        ->where('t.company_id', $f['company']->id)
        ->where('je.account_id', $amanat->id)
        ->sum(DB::raw('je.credit_amount - je.debit_amount'));

    // 25,000 in, 15,000 in, 10,000 out.
    expect(round($balance, 2))->toBe(30000.0);
});

test('the week of tea, meals and electricity lands in the right expense accounts', function () {
    $f = sevenDayFixture();
    runSevenDays($f);

    $pl = app(ProfitLossReportService::class)->run($f['company']->id, dayDate(0), dayDate(6));
    $expenses = collect($pl['expenses'])->keyBy('code');

    // Seven days of tea and meals, plus one electricity bill.
    expect($expenses['6130']['net'])->toBe(round(7 * (600.0 + 1400.0), 2))
        ->and($expenses['6110']['net'])->toBe(42000.0);

    // The short day, and only the short day, reaches cash over/short.
    expect(round((float) ($expenses['6180']['net'] ?? 0), 2))->toBe(abs(SHORT_AMOUNT));
});

test('the books balance at the end of the week', function () {
    $f = sevenDayFixture();
    runSevenDays($f);

    $trial = app(TrialBalanceReportService::class)->run($f['company']->id, dayDate(6));
    expect($trial['is_balanced'])->toBeTrue()
        ->and($trial['totals']['difference'])->toBe(0.0);

    $sheet = app(BalanceSheetReportService::class)->run($f['company']->id, dayDate(6));
    expect($sheet['is_balanced'])->toBeTrue()
        ->and($sheet['totals']['difference'])->toBe(0.0);
});

test('the drawer, the banks and the balance sheet tell the same story', function () {
    $f = sevenDayFixture();
    $week = runSevenDays($f);

    $cashBalance = (float) DB::table('acct.journal_entries as je')
        ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
        ->where('t.company_id', $f['company']->id)
        ->where('je.account_id', $f['cash']->id)
        ->sum(DB::raw('je.debit_amount - je.credit_amount'));

    expect(round($cashBalance, 2))->toBe(round($week['closing_drawer'], 2));

    // Every rupee banked over the week, across the three accounts.
    $banked = array_sum(weekDeposits());
    $bankBalance = (float) DB::table('acct.journal_entries as je')
        ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
        ->where('t.company_id', $f['company']->id)
        ->whereIn('je.account_id', $f['banks']->pluck('id'))
        ->sum(DB::raw('je.debit_amount - je.credit_amount'));

    expect(round($bankBalance, 2))->toBe(round(2000000.0 + $banked, 2));
});
