<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Services\CompanyBankAccountSyncService;
use App\Modules\Accounting\Services\CompanyOnboardingService;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\FuelStation\Actions\Product\SetupAction;
use App\Modules\FuelStation\Models\CustomerProfile;
use App\Modules\FuelStation\Models\StationSettings;
use App\Modules\FuelStation\Services\FuelStationOnboardingService;
use App\Modules\Payroll\Models\Employee;
use App\Modules\FuelStation\Services\StationAccountMapper;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use App\Services\CurrentCompany;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The station the seven-day browser scenario drives.
 *
 *   php artisan db:seed --class=Database\\Seeders\\ScenarioFuelStationSeeder
 *
 * Deliberately NOT the demo seeder. The demo exists for screenshots and carries a month of
 * generated trading; this carries none. It builds an empty, fully configured station — the
 * products, tanks, pumps, nozzles, banks, buyers and suppliers all named and fixed — and
 * stops on the morning of the first trading day, so the browser test supplies every figure
 * and the expected results are known in advance.
 *
 * Everything below is a constant. No randomness and no Carbon::today(): a scenario whose
 * inputs move cannot have expected outputs.
 *
 * Safe to re-run: purges its own company by slug first, scoped by company_id.
 */
class ScenarioFuelStationSeeder extends Seeder
{
    public const SLUG = 'scenario-mehran-fuel';
    public const EMAIL = 'scenario@haasib.test';
    public const PASSWORD = 'scenario-password';

    /** The morning the scenario starts. Trading runs for the seven days from here. */
    public const WEEK_START = '2026-03-01';

    public const RATE_PETROL = 300.00;
    public const RATE_DIESEL = 310.00;
    public const COST_PETROL = 292.00;
    public const COST_DIESEL = 302.00;

    public const OPENING_PETROL = 18000.0;
    public const OPENING_DIESEL = 12000.0;
    public const OPENING_CASH = 50000.0;
    public const OPENING_BANK = 2000000.0;

    public const LUBE_PACKAGED_PRICE = 2400.00;
    public const LUBE_OPEN_PRICE = 1100.00;

    public const CUSTOMERS = [
        'Al-Habib Transport',
        'Sindh Goods Carriers',
        'Mehran Logistics',
        'Karachi Cement Haulage',
        'Indus Travel',
        'Pak Freight Lines',
    ];

    public const VENDORS = [
        'PSO Depot — Korangi',
        'Mobil Distributor — Karachi',
        'Al-Noor Maintenance Services',
    ];

    public const BANKS = ['HBL Current', 'Meezan Current', 'UBL Savings'];

    /** The one buyer who leaves trust money with the station. */
    public const AMANAT_HOLDER = 'Karachi Cement Haulage';

    /** Salaries sum to 100,000 so a month's payroll is a round figure. */
    public const EMPLOYEES = [
        ['Muhammad', 'Ali', 'Pump Attendant', 35000],
        ['Ahmed', 'Raza', 'Pump Attendant', 32000],
        ['Usman', 'Khan', 'Station Manager', 33000],
    ];

    public function run(): void
    {
        $this->purge();

        // withoutTwoFactor: the factory enables 2FA, and a login that lands on
        // /two-factor-challenge can never reach the station.
        $user = User::where('email', self::EMAIL)->first() ?: User::factory()->withoutTwoFactor()->create([
            'name' => 'Scenario Manager',
            'username' => 'scenario',
            'email' => self::EMAIL,
            'password' => bcrypt(self::PASSWORD),
        ]);
        $user->forceFill([
            'password' => bcrypt(self::PASSWORD),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $company = Company::create([
            'name' => 'Mehran Filling Station',
            'slug' => self::SLUG,
            'base_currency' => 'PKR',
        ]);

        DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
        DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
        DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

        app(CompanyRbacBootstrapper::class)->bootstrap($company);
        DB::table('auth.company_user')->insert([
            'company_id' => $company->id, 'user_id' => $user->id, 'role' => 'owner',
            'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        app(CompanyContextService::class)->withContext(
            $company,
            fn () => app(CompanyContextService::class)->assignRole($user, 'owner')
        );
        app(CurrentCompany::class)->set($company);

        $onboarding = app(CompanyOnboardingService::class);
        $company = $onboarding->setupCompanyIdentity($company, ['industry_code' => 'fuel_station']);
        app(FuelStationOnboardingService::class)->ensureRequiredAccounts($company->id);
        $company->refresh();

        $fy = FiscalYear::create([
            'company_id' => $company->id, 'name' => '2026',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open',
        ]);
        foreach ([[2, 'February', '2026-02-01', '2026-02-28'], [3, 'March', '2026-03-01', '2026-03-31']] as [$n, $name, $from, $to]) {
            AccountingPeriod::create([
                'company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => $name,
                'period_number' => $n, 'start_date' => $from, 'end_date' => $to,
            ]);
        }

        // Three named banks plus the drawer.
        $bank = Account::where('company_id', $company->id)->where('subtype', 'bank')->orderBy('code')->firstOrFail();
        $cash = Account::where('company_id', $company->id)->where('subtype', 'cash')->orderBy('code')->firstOrFail();
        app(CompanyBankAccountSyncService::class)->ensureForCompany($company->id);
        $onboarding->setupBankAccounts($company, [
            ['id' => $bank->id, 'account_name' => self::BANKS[0], 'currency' => 'PKR', 'account_type' => 'bank'],
            ['id' => $cash->id, 'account_name' => 'Cash Drawer', 'currency' => 'PKR', 'account_type' => 'cash'],
            ['account_name' => self::BANKS[1], 'currency' => 'PKR', 'account_type' => 'bank'],
            ['account_name' => self::BANKS[2], 'currency' => 'PKR', 'account_type' => 'bank'],
        ]);
        $company->refresh();

        $cash = Account::where('company_id', $company->id)->where('subtype', 'cash')->orderBy('code')->firstOrFail();
        $bank = Account::where('company_id', $company->id)->where('subtype', 'bank')->orderBy('code')->firstOrFail();

        // Cash only, so every rupee the pump takes lands in the drawer and the expected
        // closing cash is arithmetic a person can check.
        StationSettings::updateOrCreate(['company_id' => $company->id], [
            'fuel_vendor' => 'pso',
            'has_partners' => false,
            'has_amanat' => true,
            'has_lubricant_sales' => true,
            'has_investors' => false,
            'dual_meter_readings' => false,
            'track_attendant_handovers' => false,
            'payment_channels' => [
                ['code' => 'cash', 'label' => 'Cash', 'type' => 'cash', 'enabled' => true],
            ],
            'cash_account_id' => $cash->id,
            'operating_bank_account_id' => $bank->id,
        ]);
        app(StationAccountMapper::class)->ensureMappings(
            StationSettings::where('company_id', $company->id)->firstOrFail(),
            $user->id
        );

        // Two fuels on four nozzles, plus packaged and open lubricant.
        // SetupAction resolves the tenant through the CompanyContext facade, not
        // CurrentCompany, so it has to run inside an explicit context.
        app(CompanyContextService::class)->withContext($company, fn () => app(SetupAction::class)->handle([
            'effective_date' => self::WEEK_START,
            'products' => [
                [
                    'type' => 'fuel', 'name' => 'Petrol', 'fuel_category' => 'petrol',
                    'purchase_rate' => self::COST_PETROL, 'sale_rate' => self::RATE_PETROL,
                    'opening_quantity' => self::OPENING_PETROL,
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
                    'purchase_rate' => self::COST_DIESEL, 'sale_rate' => self::RATE_DIESEL,
                    'opening_quantity' => self::OPENING_DIESEL,
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
                    'unit_of_measure' => 'piece', 'purchase_rate' => 1900.00,
                    'sale_rate' => self::LUBE_PACKAGED_PRICE, 'opening_quantity' => 0,
                ],
                [
                    'type' => 'lubricant', 'name' => 'Open Engine Oil', 'lubricant_format' => 'open', 'packaging' => 'open',
                    'unit_of_measure' => 'liter', 'purchase_rate' => 820.00,
                    'sale_rate' => self::LUBE_OPEN_PRICE, 'opening_quantity' => 0,
                ],
            ],
        ]));

        // Opening balances: the drawer, the bank, and the fuel already in the ground.
        // Fuel only. Lubricants open at zero and arrive on a purchase from the Mobil
        // distributor, which keeps their stock on the same footing as the fuel deliveries
        // rather than appearing from nowhere.
        $openingStock = self::OPENING_PETROL * self::COST_PETROL
            + self::OPENING_DIESEL * self::COST_DIESEL;

        app(GlPostingService::class)->postBalancedTransaction([
            'company_id' => $company->id, 'transaction_type' => 'journal',
            'date' => '2026-02-28', 'currency' => 'PKR', 'description' => 'Opening balances',
        ], [
            ['account_id' => $cash->id, 'type' => 'debit', 'amount' => self::OPENING_CASH],
            ['account_id' => $bank->id, 'type' => 'debit', 'amount' => self::OPENING_BANK],
            ['account_id' => $this->account($company, '1200')->id, 'type' => 'debit', 'amount' => $openingStock],
            ['account_id' => $this->account($company, '3000')->id, 'type' => 'credit',
             'amount' => self::OPENING_CASH + self::OPENING_BANK + $openingStock],
        ]);

        foreach (self::CUSTOMERS as $i => $name) {
            Customer::create([
                'company_id' => $company->id,
                'customer_number' => sprintf('CUST-%04d', $i + 1),
                'name' => $name,
                'base_currency' => 'PKR',
                'ar_account_id' => $company->ar_account_id,
                'credit_limit' => 200000,
                'payment_terms' => 15,
                'is_active' => true,
            ]);
        }

        // Every buyer is a credit customer; the amanat depositor additionally needs a
        // fuel CustomerProfile flagged is_amanat_holder, or DailyCloseService refuses the
        // deposit with "Selected Amanat depositor was not found."
        foreach (Customer::where('company_id', $company->id)->orderBy('customer_number')->get() as $i => $customer) {
            CustomerProfile::create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'is_credit_customer' => true,
                'is_amanat_holder' => $customer->name === self::AMANAT_HOLDER,
                'is_investor' => false,
            ]);
        }

        foreach (self::VENDORS as $i => $name) {
            Vendor::create([
                'company_id' => $company->id,
                'vendor_number' => sprintf('VEND-%04d', $i + 1),
                'name' => $name,
                'base_currency' => 'PKR',
                'payment_terms' => 7,
                'ap_account_id' => $company->ap_account_id,
                'is_active' => true,
            ]);
        }

        foreach (self::EMPLOYEES as $i => [$first, $last, $position, $salary]) {
            Employee::create([
                'company_id' => $company->id,
                'employee_number' => sprintf('EMP-%04d', $i + 1),
                'first_name' => $first,
                'last_name' => $last,
                'hire_date' => '2025-06-01',
                'employment_type' => 'full_time',
                'employment_status' => 'active',
                'department' => 'Forecourt',
                'position' => $position,
                'pay_frequency' => 'monthly',
                'base_salary' => $salary,
                'currency' => 'PKR',
                'is_active' => true,
            ]);
        }

        // Without these the fuel routes bounce to the onboarding wizard, so the browser
        // scenario would never reach the daily close.
        DB::table('auth.companies')->where('id', $company->id)->update([
            'onboarding_completed' => true,
            'industry' => 'energy',
        ]);

        DB::select("SELECT set_config('app.is_super_admin', 'false', false)");

        $this->command?->info("  scenario company: {$company->name} ({$company->slug})");
        $this->command?->info('  login: '.self::EMAIL.' / '.self::PASSWORD);
        $this->command?->info('  trading starts: '.self::WEEK_START.'  (no closes posted)');
    }

    private function account(Company $company, string $code): Account
    {
        return Account::where('company_id', $company->id)->where('code', $code)->firstOrFail();
    }

    /**
     * Mirrors DemoSupport::purgeDemoCompany — the FK order is load-bearing: companies
     * points at accounts, and posting templates and bank accounts do too, so the
     * self-references are broken first and accounts go almost last.
     */
    private function purge(): void
    {
        $company = Company::where('slug', self::SLUG)->first();
        if (! $company) {
            return;
        }

        DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
        DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);

        DB::table('auth.companies')->where('id', $company->id)->update([
            'ar_account_id' => null, 'ap_account_id' => null, 'income_account_id' => null,
            'expense_account_id' => null, 'bank_account_id' => null,
            'retained_earnings_account_id' => null, 'sales_tax_payable_account_id' => null,
            'purchase_tax_receivable_account_id' => null, 'transit_loss_account_id' => null,
            'transit_gain_account_id' => null,
        ]);

        $tables = [
            'fuel.daily_close_unlocks', 'fuel.daily_close_activity',
            'fuel.daily_close_reading_corrections', 'fuel.daily_close_drafts',
            'fuel.nozzle_readings', 'fuel.pump_readings', 'fuel.tank_readings',
            'fuel.attendant_handovers', 'fuel.amanat_transactions', 'fuel.sale_metadata',
            'fuel.rate_changes', 'fuel.nozzles', 'fuel.pumps', 'fuel.dip_chart_entries',
            'fuel.dip_sticks', 'fuel.investor_lots', 'fuel.investors',
            'fuel.customer_profiles', 'fuel.station_settings',
            'pay.payslip_lines', 'pay.payslips', 'pay.salary_advance_recoveries',
            'pay.salary_advances', 'pay.employee_benefits', 'pay.leave_requests',
            'pay.payroll_periods', 'pay.employees', 'pay.benefit_plans',
            'pay.deduction_types', 'pay.earning_types', 'pay.leave_types',
            'inv.cogs_entries', 'inv.cost_layers', 'inv.item_costs', 'inv.stock_movements',
            'inv.stock_levels', 'inv.stock_receipt_lines', 'inv.stock_receipts',
            'inv.items', 'inv.item_categories', 'inv.warehouses', 'inv.cost_policies',
            'acct.transaction_attachments',
            'acct.payment_allocations', 'acct.payments', 'acct.bill_payment_allocations',
            'acct.bill_payments', 'acct.credit_note_applications', 'acct.credit_note_items',
            'acct.credit_notes', 'acct.vendor_credit_applications', 'acct.vendor_credit_items',
            'acct.vendor_credits', 'acct.invoice_line_items', 'acct.invoices',
            'acct.bill_line_items', 'acct.bills', 'acct.journal_entries', 'acct.transactions',
            'acct.bank_transactions', 'acct.bank_reconciliations', 'acct.bank_rules',
            'acct.company_bank_accounts', 'acct.customers', 'acct.vendors',
            'acct.posting_template_lines', 'acct.posting_templates',
            'acct.accounting_periods', 'acct.fiscal_years',
            'acct.company_tax_registrations', 'acct.company_tax_settings', 'acct.accounts',
            'auth.company_onboarding', 'auth.company_user',
        ];

        foreach ($tables as $table) {
            try {
                DB::table($table)->where('company_id', $company->id)->delete();
            } catch (\Throwable) {
                // Not every build carries every table, or a company_id on it.
            }
        }

        DB::table('auth.companies')->where('id', $company->id)->delete();
    }
}
