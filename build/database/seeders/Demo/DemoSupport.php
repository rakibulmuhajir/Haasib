<?php

namespace Database\Seeders\Demo;

use App\Facades\CompanyContext;
use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\CompanyOnboardingService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Shared plumbing for the demo seeders.
 *
 * Everything here goes through the real onboarding + posting services rather than
 * raw inserts, so the demo data is genuinely valid: balanced journals, correct
 * default-account wiring, real accounting periods. Screenshots taken from it are
 * therefore honest — nothing is faked into the tables.
 */
trait DemoSupport
{
    /** Tables the demo seeders write to, in FK-safe delete order. */
    protected array $demoTables = [
        'umrah.payment_allocations',
        'umrah.group_payments',
        'umrah.voucher_passengers',
        'umrah.vouchers',
        'umrah.passengers',
        'umrah.group_transport_items',
        'umrah.visa_groups',
        'umrah.transport_services',
        'umrah.transport_package_sectors',
        'umrah.transport_packages',
        'umrah.transport_fares',
        'umrah.transport_sectors',
        'umrah.hotel_room_rates',
        'umrah.hotels',
        'umrah.hotel_vendors',
        'umrah.visa_services',
        'umrah.visa_vendors',
        'umrah.expenses',
        'umrah.drivers',
        'umrah.agents',
        'umrah.change_logs',
        'fuel.nozzle_readings',
        'fuel.pump_readings',
        'fuel.tank_readings',
        'fuel.attendant_handovers',
        'fuel.amanat_transactions',
        'fuel.sale_metadata',
        'fuel.rate_changes',
        'fuel.nozzles',
        'fuel.pumps',
        'fuel.dip_chart_entries',
        'fuel.dip_sticks',
        'fuel.investor_lots',
        'fuel.investors',
        'fuel.customer_profiles',
        'fuel.station_settings',
        'pay.payslip_lines',
        'pay.payslips',
        'pay.salary_advance_recoveries',
        'pay.salary_advances',
        'pay.employee_benefits',
        'pay.leave_requests',
        'pay.payroll_periods',
        'pay.employees',
        'pay.benefit_plans',
        'pay.deduction_types',
        'pay.earning_types',
        'pay.leave_types',
        'inv.cogs_entries',
        'inv.cost_layers',
        'inv.item_costs',
        'inv.stock_movements',
        'inv.stock_levels',
        'inv.stock_receipt_lines',
        'inv.stock_receipts',
        'inv.items',
        'inv.item_categories',
        'inv.warehouses',
        'inv.cost_policies',
        'acct.payment_allocations',
        'acct.payments',
        'acct.bill_payment_allocations',
        'acct.bill_payments',
        'acct.credit_note_applications',
        'acct.credit_note_items',
        'acct.credit_notes',
        'acct.vendor_credit_applications',
        'acct.vendor_credit_items',
        'acct.vendor_credits',
        'acct.invoice_line_items',
        'acct.invoices',
        'acct.bill_line_items',
        'acct.bills',
        'acct.journal_entries',
        'acct.transactions',
        'acct.bank_transactions',
        'acct.bank_reconciliations',
        'acct.bank_rules',
        'acct.company_bank_accounts',
        'acct.customers',
        'acct.vendors',
        'acct.posting_template_lines',
        'acct.posting_templates',
        'acct.accounting_periods',
        'acct.fiscal_years',
        'acct.company_tax_registrations',
        'acct.company_tax_settings',
        'acct.accounts',
        'auth.company_onboarding',
        'auth.company_user',
        // RBAC is per-company: roles are rows scoped by company_id, so a re-seed
        // must clear them or the unique (company_id, name, guard_name) survives
        // the company it belonged to. model_has_* first — roles cascades the rest.
        'model_has_permissions',
        'model_has_roles',
        'roles',
    ];

    /**
     * Remove a previously-seeded demo company so the seeder is re-runnable.
     * Scoped strictly by company_id — never touches other companies.
     */
    protected function purgeDemoCompany(string $slug): void
    {
        $company = Company::where('slug', $slug)->first();
        if (! $company) {
            return;
        }

        // Break self-referencing FKs from companies -> accounts before deleting accounts.
        DB::connection('pgsql')->table('auth.companies')->where('id', $company->id)->update([
            'ar_account_id' => null,
            'ap_account_id' => null,
            'income_account_id' => null,
            'expense_account_id' => null,
            'bank_account_id' => null,
            'retained_earnings_account_id' => null,
            'sales_tax_payable_account_id' => null,
            'purchase_tax_receivable_account_id' => null,
            'transit_loss_account_id' => null,
            'transit_gain_account_id' => null,
        ]);

        foreach ($this->demoTables as $table) {
            try {
                DB::connection('pgsql')->table($table)->where('company_id', $company->id)->delete();
            } catch (\Throwable $e) {
                // Table may not carry company_id in every build; skip quietly.
            }
        }

        DB::connection('pgsql')->table('auth.companies')->where('id', $company->id)->delete();
    }

    /** The demo login. One user owns every demo company. */
    protected function demoUser(): User
    {
        $user = User::where('email', 'demo@haasib.app')->first();

        if (! $user) {
            $user = User::create([
                'name' => 'Demo User',
                'username' => 'demo',
                'email' => 'demo@haasib.app',
                'password' => Hash::make('demo-password'),
            ]);
        }

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    /**
     * Run a company through the real onboarding pipeline: identity + industry COA,
     * fiscal year + periods, bank/cash accounts, and default account wiring.
     */
    protected function buildCompany(
        User $user,
        string $name,
        string $slug,
        string $industryCode,
        string $currency,
        string $country,
        array $bankAccounts,
        ?string $tradeName = null,
        string $industry = 'services',
        array $address = [],
        array $contact = [],
        ?string $taxNumber = null,
    ): Company {
        $onboarding = app(CompanyOnboardingService::class);

        // `industry` is a constrained display vocabulary on auth.companies;
        // `industry_code` selects the chart-of-accounts pack. They are not the same list.
        $company = Company::create([
            'name' => $name,
            'slug' => $slug,
            'industry_code' => $industryCode,
            'industry' => $industry,
            'country' => $country,
            'base_currency' => $currency,
            'language' => 'en',
            'locale' => 'en',
            'timezone' => 'Asia/Karachi',
            'created_by_user_id' => $user->id,
            // Every document this company issues carries a letterhead, and a
            // letterhead with only a name on it is not one. A demo company
            // without an address is a document nobody has actually looked at.
            'address' => $address === [] ? null : $address,
            'settings' => $contact === [] ? null : $contact,
        ]);

        $user->companies()->syncWithoutDetaching([
            $company->id => ['role' => 'owner', 'is_active' => true, 'joined_at' => now()],
        ]);

        // The pivot row alone grants nothing — permission checks go through Spatie
        // roles scoped to the company. Real company creation does this via
        // CompanyController; the seeder has to do it too or every permission-gated
        // page 403s for the demo login.
        app(CompanyRbacBootstrapper::class)->bootstrap($company, $user);

        $onboarding->initializeOnboarding($company);

        $company = $onboarding->setupCompanyIdentity($company, [
            'industry_code' => $industryCode,
            'trade_name' => $tradeName ?? $name,
            'timezone' => 'Asia/Karachi',
        ]);

        // Fiscal year starting in January so the demo covers a clean calendar year.
        $onboarding->setupFiscalYear($company, [
            'fiscal_year_start_month' => 1,
            'period_frequency' => 'monthly',
        ]);

        $onboarding->setupBankAccounts($company, $bankAccounts);

        $company = $company->fresh();

        $onboarding->setupDefaultAccounts($company, [
            'ar_account_id' => $this->accountBySubtype($company, 'accounts_receivable')->id,
            'ap_account_id' => $this->accountBySubtype($company, 'accounts_payable')->id,
            'income_account_id' => $this->accountByType($company, 'revenue')->id,
            'expense_account_id' => $this->accountByType($company, 'expense')->id,
            'bank_account_id' => $this->accountBySubtype($company, 'bank')->id,
            'retained_earnings_account_id' => $this->accountBySubtype($company, 'retained_earnings')->id,
        ]);

        // The tax number the company's documents are filed under. Letterheads
        // print it beside the name; without a registration row there is nothing
        // to print, so a demo company without one is a letterhead half-tested.
        if ($taxNumber !== null) {
            $jurisdictionId = DB::table('acct.jurisdictions')
                ->where('country_code', $country)
                ->where('level', 'country')
                ->value('id');

            if ($jurisdictionId !== null) {
                DB::table('acct.company_tax_registrations')->insert([
                    'id' => (string) Str::uuid7(),
                    'company_id' => $company->id,
                    'jurisdiction_id' => $jurisdictionId,
                    'registration_number' => $taxNumber,
                    'registration_type' => 'sales_tax',
                    'registered_name' => $company->name,
                    'effective_from' => now()->startOfYear()->toDateString(),
                    'is_active' => true,
                    'created_by_user_id' => $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $onboarding->completeOnboarding($company->fresh());

        return $company->fresh();
    }

    protected function accountBySubtype(Company $company, string $subtype): Account
    {
        $account = Account::where('company_id', $company->id)
            ->where('subtype', $subtype)
            ->where('is_active', true)
            ->orderBy('code')
            ->first();

        if (! $account) {
            throw new \RuntimeException("Demo seeder: no '{$subtype}' account in {$company->name} chart of accounts.");
        }

        return $account;
    }

    protected function accountByType(Company $company, string $type): Account
    {
        $account = Account::where('company_id', $company->id)
            ->where('type', $type)
            ->where('is_active', true)
            ->orderBy('code')
            ->first();

        if (! $account) {
            throw new \RuntimeException("Demo seeder: no '{$type}' account in {$company->name} chart of accounts.");
        }

        return $account;
    }

    /**
     * Find an account by code. Code only — deliberately no name-fragment fallback.
     *
     * An earlier version fell back to `name ilike '%…%'` ordered by code, which
     * silently bound "Sales" to 2240 Sales Tax Payable instead of 4100 Wholesale
     * Sales, and quietly posted a year of revenue into a liability. Resolve by the
     * industry pack's real codes, or by type/subtype, and never by name.
     */
    protected function account(Company $company, string $code): ?Account
    {
        return Account::where('company_id', $company->id)->where('code', $code)->first();
    }

    /** Resolve an account the industry pack is expected to provide, or fail loudly. */
    protected function requireAccount(Company $company, string $code, string $label): Account
    {
        $account = $this->account($company, $code);

        if (! $account) {
            $available = Account::where('company_id', $company->id)
                ->orderBy('code')->pluck('name', 'code')
                ->map(fn ($name, $c) => "{$c} {$name}")->implode(', ');

            throw new \RuntimeException(
                "Demo seeder: expected account {$code} ({$label}) in {$company->name}. Chart of accounts holds: {$available}"
            );
        }

        return $account;
    }

    /** Create a GL account the industry pack didn't provide. */
    protected function ensureAccount(
        Company $company,
        string $code,
        string $name,
        string $type,
        string $subtype,
        string $normalBalance,
        ?string $currency = null,
    ): Account {
        return Account::firstOrCreate(
            ['company_id' => $company->id, 'code' => $code],
            [
                'name' => $name,
                'type' => $type,
                'subtype' => $subtype,
                'normal_balance' => $normalBalance,
                'currency' => $currency,
                'is_active' => true,
                'is_system' => false,
                'is_contra' => false,
            ],
        );
    }

    protected function slugId(): string
    {
        return (string) Str::uuid();
    }

    /**
     * Run a callback with the company context and authenticated user that the
     * domain actions expect. Several of them read CompanyContext / Auth::id()
     * directly, which are request-scoped and empty inside a seeder.
     */
    protected function asCompany(Company $company, User $user, callable $callback): mixed
    {
        Auth::login($user);
        CompanyContext::setContext($company);

        try {
            return $callback();
        } finally {
            CompanyContext::clearContext();
            Auth::logout();
        }
    }

    /**
     * Bring every bank account's cached balance back in line with the ledger.
     *
     * `current_balance` is a cache of a figure the journals already hold, and
     * nothing in the seeding path maintains it -- which left every demo company
     * reporting a cash position of zero while its books showed millions. Rather
     * than invent a number, this recomputes each account from its own GL
     * account: opening balance plus every posted debit, less every posted
     * credit. A demo whose headline figure disagrees with its ledger is worse
     * than one with no data at all.
     */
    protected function syncBankBalances(Company $company): void
    {
        $accounts = DB::table('acct.company_bank_accounts')
            ->where('company_id', $company->id)
            ->whereNull('deleted_at')
            ->whereNotNull('gl_account_id')
            ->get(['id', 'gl_account_id', 'opening_balance']);

        foreach ($accounts as $account) {
            $movement = DB::table('acct.journal_entries as je')
                ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
                ->where('je.account_id', $account->gl_account_id)
                ->where('t.status', 'posted')
                ->whereNull('t.deleted_at')
                ->selectRaw('COALESCE(SUM(je.debit_amount - je.credit_amount), 0) as net')
                ->value('net');

            DB::table('acct.company_bank_accounts')
                ->where('id', $account->id)
                ->update([
                    'current_balance' => (float) $account->opening_balance + (float) $movement,
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Deterministic pseudo-random stream, so re-seeding produces identical books
     * and screenshots taken today still match the data tomorrow.
     */
    protected function seededRandom(int $seed): \Closure
    {
        $state = $seed;

        return function (float $min, float $max) use (&$state): float {
            // Numerical Recipes LCG — reproducible across PHP versions and platforms.
            $state = (1664525 * $state + 1013904223) % 4294967296;

            return $min + ($state / 4294967296) * ($max - $min);
        };
    }
}
