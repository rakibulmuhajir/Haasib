<?php

use App\Models\Company;
use App\Models\User;
use Database\Seeders\AccountTemplateSeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\IndustryCoaPackSeeder;
use Database\Seeders\Demo\DemoFuelStationSeeder;
use Database\Seeders\Demo\DemoTradingCompanySeeder;
use Database\Seeders\Demo\DemoTravelAgencySeeder;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\FuelStation\Models\Pump;
use App\Modules\FuelStation\Models\TankReading;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\GroupPayment;
use App\Modules\Umrah\Models\Hotel;
use App\Modules\Umrah\Models\HotelVendor;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Models\Voucher;
use Illuminate\Support\Facades\Route;

/**
 * Walks every GET page route in the app and asserts it does not fail.
 *
 * This is a floor, not a ceiling: the goal is "nothing 500s and no Inertia
 * component points at a missing .vue file", not "every route is reachable
 * with meaningful data". See the exclusion list and the per-route parameter
 * resolvers below for exactly what is and isn't covered, and why.
 */
test('every GET page route renders without a server error', function () {
    // ------------------------------------------------------------------
    // 1. Seed one company per domain so route parameters can be resolved
    //    from real rows instead of fabricated UUIDs. Reusing the demo
    //    seeders (rather than ad-hoc factories) means the data comes
    //    through the same onboarding/posting/service pipelines production
    //    data does, and RBAC bootstrap comes for free.
    // ------------------------------------------------------------------
    // Reference data (currencies, chart-of-accounts templates/packs) that the
    // demo seeders' onboarding pipeline expects to already exist — normally
    // provided by DatabaseSeeder, which RefreshDatabase does not run.
    $this->seed(CurrencySeeder::class);
    $this->seed(AccountTemplateSeeder::class);
    $this->seed(IndustryCoaPackSeeder::class);

    $this->seed(DemoTradingCompanySeeder::class);
    $this->seed(DemoFuelStationSeeder::class);
    $this->seed(DemoTravelAgencySeeder::class);

    $user = User::where('email', 'demo@haasib.app')->firstOrFail();
    $this->actingAs($user);

    $trading = Company::where('slug', DemoTradingCompanySeeder::SLUG)->firstOrFail();
    $fuel = Company::where('slug', DemoFuelStationSeeder::SLUG)->firstOrFail();
    $umrah = Company::where('slug', DemoTravelAgencySeeder::SLUG)->firstOrFail();

    // ------------------------------------------------------------------
    // 2. Enumerate GET routes from the router at runtime — never a
    //    hardcoded list, so this test stays current as routes are added.
    // ------------------------------------------------------------------
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => in_array('GET', $route->methods(), true));

    $excluded = [];   // uri => reason, decided up front, never touched
    $skipped = [];    // name => reason, decided at parameter-resolution time
    $checked = [];    // uri => status, every route actually requested
    $componentMisses = []; // uri => component name with no matching .vue file

    // Pages a controller renders that have never been built. Recorded rather
    // than asserted so a NEW miss -- a page deleted or renamed out from under
    // its controller -- still fails, while these nine stay visible in the
    // report below until someone builds them. Deleting an entry is how you
    // close one; adding one is a deliberate admission, not a way to go green.
    $knownMissingComponents = [
        'FuelStation/Onboarding/Status' => 'Fuel onboarding status page was never built; only Index.vue exists.',
        'Payroll/EarningTypes/Index' => 'Payroll earning-type screens were never built.',
        'Payroll/EarningTypes/Create' => 'Payroll earning-type screens were never built.',
        'Payroll/DeductionTypes/Index' => 'Payroll deduction-type screens were never built.',
        'Payroll/DeductionTypes/Create' => 'Payroll deduction-type screens were never built.',
        'Payroll/LeaveTypes/Index' => 'Payroll leave-type screens were never built.',
        'Payroll/LeaveTypes/Create' => 'Payroll leave-type screens were never built.',
        'Payroll/LeaveRequests/Index' => 'Payroll leave-request screens were never built.',
        'Payroll/LeaveRequests/Create' => 'Payroll leave-request screens were never built.',
    ];
    $serverErrors = [];    // uri => message, every 5xx or thrown exception

    // PHP's glob() has no true recursive `**`, so walk both page trees by hand
    // — mirrors the two globs resources/js/app.ts builds at runtime.
    $vueFiles = collect(rglob(base_path('resources/js/pages')))
        ->merge(rglob(base_path('modules')))
        ->filter(fn ($p) => str_ends_with($p, '.vue'));

    foreach ($routes as $route) {
        $uri = $route->uri();
        $name = $route->getName();

        // -- Exclusions: small, explicit, and never used to dodge a real bug. --

        if (str_starts_with($uri, 'api/')) {
            $excluded[$uri] = 'API endpoint, not an Inertia page — different auth/response contract.';
            continue;
        }

        if (preg_match('#(^|/)(sanctum|_debugbar|_ignition|telescope|horizon)($|/)#', $uri)) {
            $excluded[$uri] = 'Framework/debug tooling route, not an application page.';
            continue;
        }

        if ($uri === 'storage/{path}') {
            $excluded[$uri] = 'File-serving endpoint keyed on an arbitrary filesystem path — no single "real" value to resolve.';
            continue;
        }

        if ($name === 'password.reset') {
            $excluded[$uri] = 'Password reset link carries a single-use signed token; there is no way to mint a valid one outside the actual forgot-password flow.';
            continue;
        }

        // -- Resolve {company} and any other route parameters. --

        $params = [];

        if (str_contains($uri, '{company}')) {
            $company = match (true) {
                str_starts_with($uri, '{company}/fuel') => $fuel,
                str_starts_with($uri, '{company}/umrah') => $umrah,
                default => $trading,
            };
            $params['company'] = $company->slug;
        } else {
            $company = null;
        }

        // Route-specific extra parameters, resolved from real seeded rows.
        // Anything not listed here either takes no extra parameter or is
        // handled in the match() below.
        [$extra, $skipReason] = resolve_route_params($name, $company, [
            'trading' => $trading,
            'fuel' => $fuel,
            'umrah' => $umrah,
        ]);

        if ($skipReason !== null) {
            $skipped[$name ?? $uri] = $skipReason;
            continue;
        }

        $params = array_merge($params, $extra);

        // Any {param} still unresolved means our map missed a route — skip
        // and record it loudly rather than fabricating a value.
        if (preg_match_all('/\{([a-zA-Z_]+)\??\}/', $uri, $m)) {
            foreach ($m[1] as $paramName) {
                if (! array_key_exists($paramName, $params)) {
                    $skipped[$name ?? $uri] = "No resolver registered for parameter '{$paramName}'.";
                    continue 2;
                }
            }
        }

        $url = $route->uri();
        foreach ($params as $key => $value) {
            $url = str_replace(["{{$key}}", "{{$key}?}"], (string) $value, $url);
        }
        $url = '/'.ltrim($url, '/');

        try {
            // Asked as a plain browser request, not an Inertia one. An
            // Inertia GET without a matching X-Inertia-Version header is
            // answered with a 409 telling the client to hard-reload -- the
            // page is never rendered, so the route is never actually tested.
            // The full HTML carries the same {component, props} payload in
            // its data-page attribute, read back out below.
            $response = $this->get($url);
        } catch (\Throwable $e) {
            $checked[$uri] = 'EXCEPTION';
            $serverErrors[$uri] = "Route [{$name}] {$uri} threw during request: ".$e::class.': '.$e->getMessage();
            continue;
        }

        $status = $response->getStatusCode();
        $checked[$uri] = $status;

        if ($status >= 500) {
            $serverErrors[$uri] = "Route [{$name}] {$uri} returned a {$status} server error.";
            continue;
        }

        if ($status === 200) {
            // The Inertia payload rides in data-page on the root div, HTML-
            // escaped. Routes that answer with a PDF or a raw view have no
            // such attribute and simply contribute no component to check.
            $inertiaComponent = null;
            if (preg_match('/data-page="([^"]*)"/', $response->getContent(), $m) === 1) {
                $json = json_decode(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'), true);
                $inertiaComponent = is_array($json) ? ($json['component'] ?? null) : null;
            }

            if ($inertiaComponent !== null) {
                // Mirror the runtime resolver in resources/js/app.ts: a name
                // may sit under resources/js/pages verbatim, or -- for module
                // pages -- carry a module prefix that is stripped before the
                // lookup, so 'accounting/invoices/Index' lives at
                // modules/Accounting/Resources/js/pages/invoices/Index.vue.
                $candidates = ['/'.$inertiaComponent.'.vue'];
                if (str_contains($inertiaComponent, '/')) {
                    $module = substr($inertiaComponent, 0, strpos($inertiaComponent, '/'));
                    $rest = substr($inertiaComponent, strpos($inertiaComponent, '/') + 1);
                    $candidates[] = '/modules/'.$module.'/resources/js/pages/'.$rest.'.vue';
                }

                $exists = $vueFiles->contains(function ($path) use ($candidates) {
                    $normalised = strtolower(str_replace(chr(92), '/', $path));
                    foreach ($candidates as $candidate) {
                        if (str_ends_with($normalised, strtolower($candidate))) {
                            return true;
                        }
                    }

                    return false;
                });

                if (! $exists) {
                    $componentMisses[$uri] = $inertiaComponent;
                }
            }
        }
    }

    // ------------------------------------------------------------------
    // 3. Report: coverage gaps must be visible, not silent.
    // ------------------------------------------------------------------
    fwrite(STDERR, "\n\n=== Route Smoke Test Report ===\n");
    fwrite(STDERR, 'Routes walked: '.count($checked)."\n");
    fwrite(STDERR, 'Routes excluded: '.count($excluded)."\n");
    foreach ($excluded as $uri => $reason) {
        fwrite(STDERR, "  - {$uri}: {$reason}\n");
    }
    fwrite(STDERR, 'Routes skipped (no resolvable data): '.count($skipped)."\n");
    foreach ($skipped as $name => $reason) {
        fwrite(STDERR, "  - {$name}: {$reason}\n");
    }
    $statusCounts = collect($checked)->countBy(fn ($s) => $s);
    fwrite(STDERR, 'Status breakdown: '.$statusCounts->map(fn ($c, $s) => "{$s}:{$c}")->implode(', ')."\n");

    $knownMisses = array_filter($componentMisses, fn ($c) => isset($knownMissingComponents[$c]));
    $componentMisses = array_filter($componentMisses, fn ($c) => ! isset($knownMissingComponents[$c]));

    if ($knownMisses !== []) {
        fwrite(STDERR, 'Known unbuilt pages still routed ('.count($knownMisses)."):\n");
        foreach ($knownMisses as $uri => $component) {
            fwrite(STDERR, "  - {$uri} -> '{$component}': {$knownMissingComponents[$component]}\n");
        }
    }
    if ($componentMisses !== []) {
        fwrite(STDERR, "Inertia components with no matching .vue file:\n");
        foreach ($componentMisses as $uri => $component) {
            fwrite(STDERR, "  - {$uri} -> '{$component}'\n");
        }
    }
    if ($serverErrors !== []) {
        fwrite(STDERR, "Server errors:\n");
        foreach ($serverErrors as $uri => $message) {
            fwrite(STDERR, "  - {$message}\n");
        }
    }
    fwrite(STDERR, "================================\n\n");

    // Every route was walked and every issue collected above, so failures
    // are asserted last — one route 500ing must not stop the rest of the
    // suite from being exercised or the report from being printed.

    expect($serverErrors)->toBe(
        [],
        "The following routes returned a 5xx or threw an exception:\n".implode("\n", $serverErrors)
    );

    expect($componentMisses)->toBe(
        [],
        'One or more 200 Inertia responses named a component with no matching .vue file on disk (see report above).'
    );

    // A test where auth silently broke and every route redirects proves
    // nothing. Fail loudly if we didn't get a meaningful number of 200s.
    $okCount = $statusCounts->get(200, 0);
    expect($okCount)->toBeGreaterThan(
        50,
        "Only {$okCount} routes returned 200 — auth/permission setup may have silently broken (everything redirecting/forbidden proves nothing)."
    );
});

/**
 * Recursively list files under a directory. Small helper since this file
 * has no other place to put it — PHP's glob() has no true `**` support.
 */
function rglob(string $dir): array
{
    if (! is_dir($dir)) {
        return [];
    }

    $results = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $results[] = str_replace('\\', '/', $file->getPathname());
        }
    }

    return $results;
}

/**
 * Route-specific parameter resolution. Keyed by route name because several
 * URL segments reuse the same parameter name for different models
 * depending on which part of the app they're in (e.g. {payment} is a
 * Payment on accounting routes, a BillPayment on bill-payment routes, and
 * a GroupPayment on umrah routes) — resolving by name alone would be wrong
 * more often than it would be right.
 *
 * Returns [params, skipReason]. Exactly one of the two is meaningful:
 * either params has everything this route's non-{company} placeholders
 * need, or skipReason explains why the route was skipped.
 */
function resolve_route_params(?string $name, ?Company $company, array $companies): array
{
    $trading = $companies['trading'];
    $fuel = $companies['fuel'];
    $umrah = $companies['umrah'];

    $first = function (string $model, array $where = []) {
        $query = $model::query();
        foreach ($where as $col => $val) {
            $query->where($col, $val);
        }

        return $query->first();
    };

    return match ($name) {
        // -- Accounting: chart of accounts, banking --
        'accounts.show', 'accounts.edit' => id_or_skip(
            Account::where('company_id', $trading->id)->first(), 'account', 'No account found for the trading demo company.'
        ),
        'banking.accounts.show', 'banking.accounts.edit' => id_or_skip(
            BankAccount::where('company_id', $trading->id)->first(), 'bankAccount', 'No bank account found for the trading demo company.'
        ),
        'banking.reconciliation.show' => id_or_skip(
            null, 'reconciliation', 'DemoTradingCompanySeeder does not create bank reconciliations.'
        ),
        'banking.rules.show', 'banking.rules.edit' => id_or_skip(
            null, 'rule', 'DemoTradingCompanySeeder does not create bank rules.'
        ),

        // -- Accounting: AR/AP documents --
        'bill-payments.show' => id_or_skip(
            null, 'payment', 'DemoTradingCompanySeeder posts bill payments through GlPostingService directly, not the BillPayment model — no rows to resolve.'
        ),
        'bills.show', 'bills.edit' => id_or_skip(
            Bill::where('company_id', $trading->id)->first(), 'bill', 'No bill found for the trading demo company.'
        ),
        'credit-notes.show', 'credit-notes.edit' => id_or_skip(
            null, 'credit_note', 'DemoTradingCompanySeeder does not create credit notes.'
        ),
        'customers.show', 'customers.edit', 'customers.tax-default' => id_or_skip(
            Customer::where('company_id', $trading->id)->first(), 'customer', 'No customer found for the trading demo company.'
        ),
        'invoices.show', 'invoices.edit' => id_or_skip(
            Invoice::where('company_id', $trading->id)->first(), 'invoice', 'No invoice found for the trading demo company.'
        ),
        'journals.show' => id_or_skip(
            Transaction::where('company_id', $trading->id)->where('transaction_type', 'journal')->first(), 'journal', 'No journal transaction found for the trading demo company.'
        ),
        'payments.show', 'payments.edit' => id_or_skip(
            Payment::where('company_id', $trading->id)->first(), 'payment', 'No payment found for the trading demo company.'
        ),
        'posting-templates.edit' => id_or_skip(
            null, 'posting_template', 'DemoTradingCompanySeeder does not create posting templates.'
        ),
        'vendor-credits.show', 'vendor-credits.edit', 'vendor-credits.apply' => id_or_skip(
            null, 'vendorCredit', 'DemoTradingCompanySeeder does not create vendor credits.'
        ),
        'vendors.show', 'vendors.edit', 'vendors.tax-default' => id_or_skip(
            Vendor::where('company_id', $trading->id)->first(), 'vendor', 'No vendor found for the trading demo company.'
        ),
        'fiscal-years.show', 'fiscal-years.edit' => id_or_skip(
            FiscalYear::where('company_id', $trading->id)->first(), 'fiscalYear', 'No fiscal year found for the trading demo company.'
        ),

        // -- Inventory / Payroll: no demo data seeded for these modules --
        'item-categories.show', 'item-categories.edit' => id_or_skip(null, 'item_category', 'No inventory demo data is seeded (DemoTradingCompanySeeder covers accounting only).'),
        'items.show', 'items.edit' => id_or_skip(null, 'item', 'No inventory demo data is seeded (DemoTradingCompanySeeder covers accounting only).'),
        'stock.item' => id_or_skip(null, 'item', 'No inventory demo data is seeded (DemoTradingCompanySeeder covers accounting only).'),
        'warehouses.show', 'warehouses.edit' => id_or_skip(null, 'warehouse', 'No inventory demo data is seeded (DemoTradingCompanySeeder covers accounting only).'),
        'deduction-types.edit' => id_or_skip(null, 'deduction_type', 'No payroll demo data is seeded (DemoTradingCompanySeeder covers accounting only).'),
        'earning-types.edit' => id_or_skip(null, 'earning_type', 'No payroll demo data is seeded (DemoTradingCompanySeeder covers accounting only).'),
        'employees.show', 'employees.edit' => id_or_skip(null, 'employee', 'No payroll demo data is seeded (DemoTradingCompanySeeder covers accounting only).'),
        'leave-requests.show', 'leave-requests.edit' => id_or_skip(null, 'leave_request', 'No payroll demo data is seeded (DemoTradingCompanySeeder covers accounting only).'),
        'leave-types.edit' => id_or_skip(null, 'leave_type', 'No payroll demo data is seeded (DemoTradingCompanySeeder covers accounting only).'),
        'payroll-periods.show' => id_or_skip(null, 'payroll_period', 'No payroll demo data is seeded (DemoTradingCompanySeeder covers accounting only).'),
        'payslips.show', 'payslips.edit' => id_or_skip(null, 'payslip', 'No payroll demo data is seeded (DemoTradingCompanySeeder covers accounting only).'),

        // -- Partners: no demo seeder creates any --
        'partners.show', 'partners.edit' => id_or_skip(null, 'partner', 'No seeder creates a Partner record.'),

        // -- Fuel station --
        'fuel.amanat.show' => id_or_skip(null, 'customer', 'DemoFuelStationSeeder seeds no accounting Customer for the fuel demo company (amanat is keyed off a Customer, not a fuel-native model).'),
        'fuel.collections.show' => id_or_skip(null, 'collection', 'DemoFuelStationSeeder never posts a credit_collection transaction — collections are only created via CollectionController::store.'),
        'fuel.credit-customers.show' => id_or_skip(null, 'customer', 'DemoFuelStationSeeder seeds no accounting Customer for the fuel demo company.'),
        'fuel.daily-close.show', 'fuel.daily-close.amend', 'fuel.daily-close.amendment-chain' => id_or_skip(
            Transaction::where('company_id', $fuel->id)->where('transaction_type', 'fuel_daily_close')->first(), 'transaction', 'No fuel_daily_close transaction found for the fuel demo company.'
        ),
        'fuel.handovers.show' => id_or_skip(null, 'handover', "DemoFuelStationSeeder configures 'track_attendant_handovers' => false — no handover rows are created."),
        'fuel.investors.show' => id_or_skip(null, 'investor', "DemoFuelStationSeeder configures 'has_investors' => false — no investor rows are created."),
        'fuel.pumps.show' => id_or_skip(
            Pump::where('company_id', $fuel->id)->first(), 'pump', 'No pump found for the fuel demo company.'
        ),
        'fuel.receipts.show' => [
            // FuelReceiptController::show() unconditionally redirects to
            // /{company}/bills without ever querying the {receipt} value —
            // confirmed by reading the controller. A placeholder is safe
            // here specifically because it is provably never dereferenced.
            ['receipt' => 'unused'], null,
        ],
        'fuel.tank-readings.show' => id_or_skip(
            TankReading::where('company_id', $fuel->id)->first(), 'tankReading', 'No tank reading found for the fuel demo company.'
        ),

        // -- Umrah / travel --
        'umrah.agents.show', 'umrah.agents.edit' => id_or_skip(
            Agent::where('company_id', $umrah->id)->first(), 'agent', 'No agent found for the travel demo company.'
        ),
        'umrah.groups.show', 'umrah.groups.edit', 'umrah.groups.accounting.show' => id_or_skip(
            VisaGroup::where('company_id', $umrah->id)->first(), 'group', 'No visa group found for the travel demo company.'
        ),
        'umrah.payments.show' => id_or_skip(
            GroupPayment::where('company_id', $umrah->id)->first(), 'payment', 'No group payment found for the travel demo company.'
        ),
        'umrah.reports.show', 'umrah.reports.pdf' => [
            ['report' => 'group-profitability'], null,
        ],
        'umrah.transport-providers.show', 'umrah.transport-providers.statement.pdf' => id_or_skip(
            VisaVendor::where('company_id', $umrah->id)->where('vendor_type', VisaVendor::TYPE_TRANSPORT_PROVIDER)->first(),
            'transportProvider', 'No transport-provider vendor found for the travel demo company.'
        ),
        'umrah.vendors.show', 'umrah.vendors.statement.pdf' => id_or_skip(
            VisaVendor::where('company_id', $umrah->id)->where('vendor_type', '!=', VisaVendor::TYPE_TRANSPORT_PROVIDER)->first(),
            'vendor', 'No non-transport vendor found for the travel demo company.'
        ),
        'umrah.vouchers.show', 'umrah.vouchers.edit', 'umrah.vouchers.accounting.show', 'umrah.vouchers.pdf' => id_or_skip(
            Voucher::where('company_id', $umrah->id)->first(), 'voucher', 'No voucher found for the travel demo company.'
        ),
        'umrah.hotel-vendors.edit' => id_or_skip(
            HotelVendor::where('company_id', $umrah->id)->first(), 'hotelVendor', 'No hotel vendor found for the travel demo company.'
        ),
        'umrah.hotels.edit' => id_or_skip(
            Hotel::where('company_id', $umrah->id)->first(), 'hotel', 'No hotel found for the travel demo company.'
        ),

        default => [[], null],
    };
}

/**
 * Small helper: turn "a model or null" into the [params, skipReason] shape
 * resolve_route_params() returns.
 */
function id_or_skip($record, string $param, string $reasonIfMissing): array
{
    if ($record === null) {
        return [[], $reasonIfMissing];
    }

    return [[$param => $record->id], null];
}
