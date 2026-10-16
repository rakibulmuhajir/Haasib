# Core Independence + General Accounting Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove every fuel- and umrah-specific branch from the core modules (Accounting, Inventory, Payroll and the `app/` layer), have FuelStation and Umrah add their extras through extension points, then make the `other` industry a real general-accounting product: a proper chart of accounts, a general home page and opening balances that cover a general business.

**Architecture:** Two kinds of extension points, both registered from a module's service provider `boot()`:
1. **Hooks** for things core asks a module ("may this invoice change?", "what default account?"). Each hook is a small keyed static registry, the pattern `DocumentDateLock`, `SalesDiscountAccountResolver` and `ClearingPaymentAccounts` already use.
2. **Laravel events** for things core tells a module ("invoice voided", "opening stock changed"). They are dispatched synchronously, inside the caller's DB transaction.

App-shell concerns (home page, onboarding, shared props, settings menu, permission display) go through a `ModuleManifest` that each module registers in a `ModuleRegistry`. Core reads data-driven lists from the server (`enabledModules`, receipt modes, statement kinds, opening sections). Inline module UI goes into named `<ModuleSlot>`s.

**Tech Stack:** Laravel 12 + Pest 4, PostgreSQL (multi-schema, RLS), Inertia + Vue 3 `<script setup lang="ts">`, Shadcn-vue.

**Spec:** No separate spec. This plan carries the design. Research notes are in the "Coupling inventory" section below.

All paths below are relative to `build/` unless they start with `docs/`.

## Global Constraints

- **Behaviour is identical for existing fuel and umrah companies.** Every refactor moves code; it does not change what it does. If a site cannot be moved without a behaviour change, stop and report it.
- Never run `migrate:fresh`, `migrate:refresh`, `db:wipe` or anything with `--env=testing` (CLAUDE.md).
- Tests are opt-in. Write them. Run them only when the user says so, and then only the touched folder: `php artisan test tests/Feature/{Folder}`. Never run two test processes at once.
- Cheap gates are allowed without asking: `php -l`, `php artisan route:list`, `grep`, `npx vue-tsc --noEmit`. `npm run build` (~3 min) runs once, at the end of Task 9.
- Commit after each task (memory: parallel agents clobber uncommitted work). Commit messages end with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- No production data edits. The only data change in this plan is template rows in `acct.industry_coa_templates` (Task 11), done by migration because `deploy.sh` never runs seeders.
- Fuel-owned columns that live in core tables (`acct.invoices.is_direct_delivery`, `inv.items.fuel_category`, `inv.warehouses` tank fields) **stay where they are**. Moving them is a production data migration with no user benefit. Core stops *showing* them unless the module is on.
- RBAC config (`config/permissions.php`, `config/role-permissions.php`, `app/Constants/Permissions.php`) and `config/dashboards.php` stay as they are. They hold strings that are synced to the DB, and moving them risks live roles.
- PHP: UUID models, FormRequests, CommandBus, `app(CurrentCompany::class)`. Vue: Shadcn components, `<script setup lang="ts">`, Inertia forms (CLAUDE.md).
- UI copy is a few words (memory: short UI copy).
- Delegation (memory): Opus writes Task 1 and Task 2 itself, because they set the pattern. Sonnet subagents do Tasks 3–9 and 11–13 from this plan, and Opus reviews each one with grep, not by trusting the agent's report.

## Review Focus

1. **A fuel company whose `settings.modules` lacks `fuel_station`** but whose `industry_code`/`industry` says fuel must still be treated as fuel everywhere. `hasModule()` keeps the old OR semantics. Pinned in Task 1.
2. **Hooks registered twice.** Static state outlives the app instance (Octane, and every Pest test re-boots providers). Registration is keyed, so a second `boot()` replaces the hook instead of running it twice. Example: temporary fuel stock reversed twice on one void. Pinned in Task 2.
3. **A general company that happens to have account 2200** must see no amanat anywhere: not in opening balances, the payment "received as" option, statements or quick-add customer. Today the `other` pack's 2200 is *Accrued Expenses*, and `hasAmanat` turns on from code 2200 alone. Pinned in Tasks 3, 4 and 5.
4. **A listener that throws during invoice void** must roll back the whole void. The event dispatches inside the action's transaction, and the listener is not queued and not after-commit. Pinned in Task 2.
5. **Routes unchanged.** Same names, URIs and middleware before and after Task 8. Moving the fuel route include must not double-register or reorder against `/{company}/…` catch-alls. Pinned by the Task 0 and Task 8 `route:list` diff.

---

## Coupling inventory (research, 2026-10-15)

PHP sites in core that name a module (comments excluded). Each row lists the task that removes it:

| Site | What it does | Task |
|---|---|---|
| `modules/Accounting/Models/Invoice.php:44,49` | `DailyCloseCreditSaleService::assertMutable` on saving/deleting | 2 |
| `modules/Accounting/Models/Invoice.php:171` | `saleMetadata()` relation to `FuelStation\Models\SaleMetadata` | 2 |
| `modules/Accounting/Actions/Invoice/UpdateAction.php:73-77` | assertMutable + temporary-fuel-stock check | 2 |
| `modules/Accounting/Actions/Invoice/VoidAction.php:44,93` | assertMutable + `TemporaryFuelStockService::reverse` | 2 |
| `modules/Accounting/Http/Controllers/BillController.php:221-234,255-266` | fuel default line account 1200 | 2 |
| `modules/Accounting/Http/Controllers/InvoiceController.php:124,253` | `isFuelStation` prop for the direct-delivery card | 9 (prop removed), 1 (check) |
| `modules/Accounting/Http/Controllers/CustomerController.php:420-424` | fuel redirect after save | 2 |
| `modules/Accounting/Services/ConsolidatedInvoiceService.php:136-140` | "Fuel/Litres" column labels | 2 |
| `modules/Accounting/Http/Controllers/PaymentController.php:13,32-49,152,164,226-260` | amanat receipt mode | 3 |
| `modules/Accounting/Http/Controllers/StatementReportController.php:17,46,58-71,114,126,152,401-417` | amanat statement kind | 4 |
| `modules/Accounting/Actions/OpeningBalance/SaveAction.php:23-24,74-76,132,210,313,487-547,691-722` | amanat opening section | 5 |
| `modules/Accounting/Actions/OpeningBalance/ViewAction.php:18,81,115,134` | amanat opening rows | 5 |
| `modules/Accounting/Services/OpeningBalanceAccounts.php:26` | `amanat` → code 2200 | 5 |
| `app/Http/Middleware/HandleInertiaRequests.php:210-214` | `hasAmanat` = code 2200 exists (**leaks into general companies**) | 5 |
| `modules/Accounting/Services/CompanyOnboardingService.php:88-94,245-250` | enable modules by industry; fuel auto accounts | 7 |
| `modules/Inventory/Services/ItemPriceService.php:6-8,29,35,121-130,170` | fuel rate history, daily-close lock, lubricant cost | 6 |
| `modules/Inventory/Services/OpeningStockService.php:5,62` / `StockAdjustmentService.php:139` | `OpeningStockLedgerService` sync | 6 |
| `modules/Inventory/Models/Warehouse.php:91` | `dipStick()` relation | 6 |
| `modules/Inventory/Http/Controllers/ItemController.php:9,203-207` | `FuelProductAccountMapper` defaults | 6 |
| `modules/Inventory/Http/Controllers/StockController.php:228,309-322` | links to fuel documents | 6 |
| `modules/Payroll/Http/Controllers/SalaryAdvanceController.php:78-80` | `usesDailyClose` | 6 |
| `app/Actions/Company/UpdateModulesAction.php:35` | inventory required by fuel | 6 |
| `app/Http/Controllers/CompanyController.php:135,271-335,379-384,578-580,632-633` | home routing, fuel home, flags | 7 |
| `app/Http/Controllers/CompaniesPageController.php:102-107` | open-company URL | 7 |
| `app/Http/Controllers/CompanyOnboardingController.php:17-18,30-31,41,83-119,160,208,301` | fuel onboarding redirects + installer | 7 |
| `app/Services/CompanyBootstrapService.php:13,101-117` | fuel installer, umrah enable | 7 |
| `app/Http/Middleware/HandleInertiaRequests.php:8,161-176` | `fuelNavigation` prop | 7 |
| `app/Services/SettingsMenu.php:21,44-69` | station settings sections | 7 |
| `app/Services/UserPermissionPresenter.php:24-63` | umrah permission display | 7 |
| `app/Services/PartnerProfitShareService.php:13-14,165` | fuel profit statement, daily-close lock | 7 |
| `app/Console/Commands/{RecostFuelCloses,FuelMonthEndValuation,FuelLubricantCost,LockMonthlyDailyCloses}.php`, `routes/console.php:14` | fuel commands + schedule | 8 |
| `config/command-bus.php:5-12,146-147` | fuel command-bus keys | 8 |
| `routes/web.php:558` | `require` fuel routes (the provider also loads them) | 8 |

Frontend sites outside the module folders: `resources/js/components/ProductQuickAddDialog.vue` (used only by FuelStation), `resources/js/components/dashboard/widgets/index.ts` + 9 umrah widget components, `resources/js/composables/useNavGroups.ts`, `resources/js/components/AppHeader.vue`, `resources/js/pages/company/Settings.vue:211-227`. Inside core module pages: `invoices/Create.vue:467`, `invoices/Edit.vue:427`, `payments/Create.vue`, `reports/Statement.vue:165-169,278`, `opening-balances/Index.vue`, `resources/js/components/forms/QuickAddModal.vue:271`, plus fuel wording in `bill-payments/*`, `credit-notes/Show.vue:550`, `customers/Create.vue:115`, `customers/PeriodDiscount.vue:162,249`, `posting-templates/{Create,Edit}.vue`, `vendors/Create.vue:137`, `Inventory/.../items/Show.vue`, `stock/{Index,Adjustment}.vue`, `warehouses/{Create,Index}.vue`. All of these are Task 9.

**Found along the way. Out of scope, so the user can decide:**
- **Code 2210 means two things.** `OpeningBalanceAccounts` reads 2210 as *partner deposits*, while `PayrollPostingService` falls back to code 2210 as *salaries payable* when no account matches by name. A company whose 2210 is partner deposits and has no "Salary Payable"-named account would post payroll to partner deposits. Task 11 avoids this for new general companies. Existing fuel companies need a separate check.
- **The current `other` pack has 6200 = "Rent and Utilities".** Payroll looks for salary expense by name (`Salaries & Wages`; the pack says "Salaries and Wages", which doesn't match) and then by code `6200`. So payroll at existing `other` companies books salaries to Rent. Task 11 fixes the template for new companies. Existing companies are repaired only if the user chooses to (`RepairChartOfAccounts`).

---

## Task 0: Baselines

**Files:**
- Create: `docs/superpowers/plans/baselines/2026-10-15-routes-before.json` (generated)

- [ ] **Step 1: Snapshot routes**

```bash
cd build && php artisan route:list --json > ../docs/superpowers/plans/baselines/2026-10-15-routes-before.json
```
Expected: the file is written and is non-empty.

- [ ] **Step 2: Record the coupling count (the ratchet's starting number)**

```bash
cd build && grep -rnE "Modules.(FuelStation|Umrah)" modules/Accounting modules/Inventory modules/Payroll modules/Tax app --include=*.php | grep -vE ":\s*(\*|//)" | wc -l
```
Expected: about 45. Write the number into the commit message.

- [ ] **Step 3: Ask the user for a read-only production check** (Review Focus 1). The user runs it themselves, because memory says production tinker needs `app.is_super_admin`:

```bash
php artisan tinker --execute="DB::statement(\"select set_config('app.is_super_admin','true',false)\"); foreach (DB::select(\"select slug, industry, industry_code, settings->'modules' m from auth.companies\") as \$r) echo json_encode(\$r).PHP_EOL;"
```
List any company whose module flag disagrees with its industry. `hasModule()` keeps them working either way. The list only tells us whether the four "widened" sites in Task 1 matter.

- [ ] **Step 4: Commit**

```bash
git add docs/superpowers/plans && git commit -m "Add core-independence plan and route baseline"
```

---

## Task 1: Module registry, `hasModule()`, `enabledModules` (Opus writes this)

**Files:**
- Create: `app/Support/Modules/ModuleManifest.php`
- Create: `app/Support/Modules/ModuleRegistry.php`
- Create: `modules/FuelStation/FuelStationModule.php`
- Create: `modules/Umrah/UmrahModule.php`
- Modify: `app/Providers/AppServiceProvider.php` (bind the registry singleton)
- Modify: `app/Models/Company.php` (add `hasModule()`, `capable()`)
- Modify: `modules/FuelStation/Providers/FuelStationServiceProvider.php`, `modules/Umrah/Providers/UmrahServiceProvider.php` (register the manifests)
- Modify: `app/Http/Middleware/HandleInertiaRequests.php` (shared `enabledModules`)
- Modify (sweep): every three-way check listed in Step 6
- Test: `tests/Feature/Modules/ModuleRegistryTest.php`

**Interfaces:**
- Produces: `App\Support\Modules\ModuleManifest`, `ModuleRegistry::register(ModuleManifest)`, `ModuleRegistry::get(string): ?ModuleManifest`, `ModuleRegistry::all(): array<string, ModuleManifest>`, `ModuleRegistry::enabledFor(Company): array<string, ModuleManifest>`, `Company::hasModule(string): bool`, `Company::capable(string $capability): bool`, and the Inertia shared prop `enabledModules: string[]`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Modules/ModuleRegistryTest.php

use App\Models\Company;
use App\Models\User;
use App\Support\Modules\ModuleRegistry;

function moduleCompany(array $attrs): Company
{
    $user = User::factory()->create();

    return Company::create(array_merge([
        'name' => 'Mod Co', 'slug' => 'mod-co-'.str()->random(8), 'owner_id' => $user->id, 'base_currency' => 'PKR',
    ], $attrs));
}

test('fuel and umrah register manifests', function () {
    $registry = app(ModuleRegistry::class);
    expect($registry->get('fuel_station'))->not->toBeNull()
        ->and($registry->get('umrah'))->not->toBeNull()
        ->and($registry->get('umrah')->industryCodes())->toBe(['umrah', 'travel']);
});

test('hasModule keeps the old OR semantics: flag, industry_code or industry', function () {
    expect(moduleCompany(['industry_code' => 'fuel_station'])->hasModule('fuel_station'))->toBeTrue()
        ->and(moduleCompany(['industry' => 'fuel_station'])->hasModule('fuel_station'))->toBeTrue()
        ->and(moduleCompany(['settings' => ['modules' => ['fuel_station' => true]]])->hasModule('fuel_station'))->toBeTrue()
        // An explicit false never overrode the industry before; it still doesn't.
        ->and(moduleCompany(['industry_code' => 'fuel_station', 'settings' => ['modules' => ['fuel_station' => false]]])->hasModule('fuel_station'))->toBeTrue()
        ->and(moduleCompany(['industry_code' => 'travel'])->hasModule('umrah'))->toBeTrue()
        ->and(moduleCompany(['industry_code' => 'other'])->hasModule('fuel_station'))->toBeFalse()
        ->and(moduleCompany(['industry_code' => 'other'])->hasModule('umrah'))->toBeFalse();
});

test('capabilities come from enabled manifests only', function () {
    expect(moduleCompany(['industry_code' => 'fuel_station'])->capable('daily_close'))->toBeTrue()
        ->and(moduleCompany(['industry_code' => 'other'])->capable('daily_close'))->toBeFalse();
});

test('a general company shares no enabled modules', function () {
    $company = moduleCompany(['industry_code' => 'other']);
    expect(array_keys(app(ModuleRegistry::class)->enabledFor($company)))->toBe([]);
});
```

- [ ] **Step 2: Write `ModuleManifest`**

```php
<?php
// app/Support/Modules/ModuleManifest.php

namespace App\Support\Modules;

use App\Models\Company;

/**
 * What an industry module tells the app shell about itself. Core never names a
 * module; it asks the registry for the manifests enabled for a company and reads
 * these answers. Every method has a "nothing to add" default, so a manifest
 * overrides only what it uses. Later tasks add methods here; each one keeps a
 * null/empty default.
 */
abstract class ModuleManifest
{
    /** settings.modules key, e.g. 'fuel_station'. */
    abstract public function key(): string;

    /** Industry codes that imply this module even when settings.modules lacks it. */
    public function industryCodes(): array
    {
        return [];
    }

    /** Core modules that must stay on while this one is on, e.g. ['inventory']. */
    public function requires(): array
    {
        return [];
    }

    /** Module keys switched off when this one is switched on by industry. */
    public function conflicts(): array
    {
        return [];
    }

    /** Behaviours core may ask about without naming the module, e.g. 'daily_close'. */
    public function capabilities(): array
    {
        return [];
    }

    /** Wording overrides, e.g. ['consolidated.item' => 'Fuel']. */
    public function labels(): array
    {
        return [];
    }

    public function isEnabledFor(Company $company): bool
    {
        if ($company->isModuleEnabled($this->key())) {
            return true;
        }
        $codes = $this->industryCodes();

        return in_array($company->industry_code, $codes, true) || in_array($company->industry, $codes, true);
    }
}
```

- [ ] **Step 3: Write `ModuleRegistry`**

```php
<?php
// app/Support/Modules/ModuleRegistry.php

namespace App\Support\Modules;

use App\Models\Company;

/**
 * Keyed registry of industry modules. Modules register from their service
 * provider's boot(); registering the same key again replaces the entry, so a
 * re-booted app (Octane, tests) never holds duplicates. Bound as a singleton.
 */
class ModuleRegistry
{
    /** @var array<string, ModuleManifest> */
    private array $modules = [];

    public function register(ModuleManifest $manifest): void
    {
        $this->modules[$manifest->key()] = $manifest;
    }

    public function get(string $key): ?ModuleManifest
    {
        return $this->modules[$key] ?? null;
    }

    /** @return array<string, ModuleManifest> */
    public function all(): array
    {
        return $this->modules;
    }

    /** @return array<string, ModuleManifest> */
    public function enabledFor(Company $company): array
    {
        return array_filter($this->modules, fn (ModuleManifest $m) => $m->isEnabledFor($company));
    }

    /** The first enabled module's wording for $key, else $default. */
    public function label(Company $company, string $key, string $default): string
    {
        foreach ($this->enabledFor($company) as $manifest) {
            if (isset($manifest->labels()[$key])) {
                return $manifest->labels()[$key];
            }
        }

        return $default;
    }
}
```

In `AppServiceProvider::register()`, next to the existing `WidgetRegistry` singleton binding, add:
```php
$this->app->singleton(\App\Support\Modules\ModuleRegistry::class);
```

- [ ] **Step 4: Add the methods to `Company`** (directly after `isModuleEnabled`)

```php
/**
 * Whether an industry module is on for this company: its settings flag, or an
 * industry that implies it. Use this, never industry_code comparisons.
 */
public function hasModule(string $moduleKey): bool
{
    return app(\App\Support\Modules\ModuleRegistry::class)->get($moduleKey)?->isEnabledFor($this) ?? $this->isModuleEnabled($moduleKey);
}

/** Whether any enabled module declares $capability, e.g. 'daily_close'. */
public function capable(string $capability): bool
{
    foreach (app(\App\Support\Modules\ModuleRegistry::class)->enabledFor($this) as $manifest) {
        if (in_array($capability, $manifest->capabilities(), true)) {
            return true;
        }
    }

    return false;
}
```

- [ ] **Step 5: The two manifests, and register them**

```php
<?php
// modules/FuelStation/FuelStationModule.php

namespace App\Modules\FuelStation;

use App\Support\Modules\ModuleManifest;

class FuelStationModule extends ModuleManifest
{
    public function key(): string { return 'fuel_station'; }
    public function industryCodes(): array { return ['fuel_station']; }
    public function requires(): array { return ['inventory']; }
    public function capabilities(): array { return ['daily_close']; }
    public function labels(): array { return ['consolidated.item' => 'Fuel', 'consolidated.quantity' => 'Litres']; }
}
```

```php
<?php
// modules/Umrah/UmrahModule.php

namespace App\Modules\Umrah;

use App\Support\Modules\ModuleManifest;

class UmrahModule extends ModuleManifest
{
    public function key(): string { return 'umrah'; }
    public function industryCodes(): array { return ['umrah', 'travel']; }
    public function conflicts(): array { return ['fuel_station']; }
}
```

In each provider's `boot()`, as the first line:
```php
$this->app->make(\App\Support\Modules\ModuleRegistry::class)->register(new \App\Modules\FuelStation\FuelStationModule);
// Umrah: register(new \App\Modules\Umrah\UmrahModule)
```

Check `ConsolidatedInvoiceService.php:131-140` before relying on the labels: copy the exact two labels it uses into `labels()` if they differ from the ones above.

- [ ] **Step 6: Share `enabledModules`**

In `HandleInertiaRequests::share()`, next to `fuelNavigation`:
```php
'enabledModules' => function () {
    $company = app(CurrentCompany::class)->get();

    return $company ? array_keys(app(\App\Support\Modules\ModuleRegistry::class)->enabledFor($company)) : [];
},
```

- [ ] **Step 7: Sweep the three-way checks to `hasModule()`.** This is mechanical and keeps behaviour. Replace each of these with `$company->hasModule('fuel_station')` or `$company->hasModule('umrah')`:
  - `BillController.php:221-223, 255-257`
  - `CustomerController.php:420-421`
  - `InvoiceController.php:124-126, 253-255`
  - `PaymentController.php:38-40`
  - `SalaryAdvanceController.php:78-80`
  - `CompanyController.php:273-278, 379-381, 578-580`
  - `HandleInertiaRequests.php:170-172`
  - `CompaniesPageController.php:102-107`
  - `UserPermissionPresenter.php:24-25, 47-48`
  - `PartnerProfitShareService.php:165` (was `industry` OR flag, which **widens** to industry_code too)
  - `ItemController.php:203` (was `industry_code` only, which **widens** to flag/industry)
  - `ConsolidatedInvoiceService.php:136` and `SettingsMenu.php:21` (flag only, which **widens** to industry)

  The four sites marked "widens" only differ for rows the Task 0 Step 3 check flags. Mention them in the commit message.

  Verify:
  ```bash
  cd build && grep -rnE "industry_code\s*===?\s*'(fuel_station|umrah|travel)'|industry\s*===?\s*'fuel_station'|\['umrah', 'travel'\]" modules/Accounting modules/Inventory modules/Payroll app --include=*.php
  ```
  Expected hits are only the onboarding/bootstrap sites Task 7 moves: `CompanyController.php:135`, `CompanyOnboardingController`, `CompanyBootstrapService`, `CompanyOnboardingService`, `RequireIndustry` (keep; it matches route params).

- [ ] **Step 8: Lint, then commit**

```bash
cd build && php -l app/Support/Modules/ModuleRegistry.php && php -l app/Models/Company.php
git add -A && git commit -m "Add module registry and Company::hasModule; route industry checks through it"
```
Run `php artisan test tests/Feature/Modules` only if the user approves.

---

## Task 2: Accounting hooks: invoice guards, void event, bill defaults, customer page (Opus writes this)

**Files:**
- Create: `app/Support/Extensions/HasHooks.php`
- Create: `modules/Accounting/Extensions/InvoiceMutationGuard.php`
- Create: `modules/Accounting/Extensions/BillDefaults.php`
- Create: `modules/Accounting/Extensions/CustomerPages.php`
- Create: `modules/Accounting/Events/InvoiceVoided.php`
- Create: `modules/FuelStation/Listeners/ReverseTemporaryFuelStock.php`
- Modify: `modules/Accounting/Models/Invoice.php:44,49,168-172`
- Modify: `modules/Accounting/Actions/Invoice/UpdateAction.php:73-77`
- Modify: `modules/Accounting/Actions/Invoice/VoidAction.php:44,93`
- Modify: `modules/Accounting/Http/Controllers/BillController.php:220-235,255-266`
- Modify: `modules/Accounting/Http/Controllers/CustomerController.php:419-425`
- Modify: `modules/Accounting/Services/ConsolidatedInvoiceService.php:136-140`
- Modify: `modules/FuelStation/Providers/FuelStationServiceProvider.php`
- Test: `tests/Feature/Modules/AccountingHooksTest.php`

**Interfaces:**
- Consumes: `Company::hasModule`, `ModuleRegistry::label` (Task 1)
- Produces:
  - `HasHooks` trait: `extend(string $key, callable $hook)`, `flushHooks()`, `hooks(): array`
  - `InvoiceMutationGuard::assert(Invoice $invoice, string $operation): void`, where `$operation` is one of `'save' | 'delete' | 'update' | 'void'`. Hook signature: `fn (Invoice $invoice, string $operation): void` (throws to block).
  - `InvoiceVoided` event: `public Invoice $invoice, public string $reversalDate, public ?string $userId`
  - `BillDefaults::lineAccountId(Company $company): ?string`. Hook: `fn (Company): ?string`; the first non-null wins.
  - `CustomerPages::afterSaveUrl(Company $company, Customer $customer): ?string`. Hook: `fn (Company, Customer): ?string`.

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/Modules/AccountingHooksTest.php

use App\Modules\Accounting\Events\InvoiceVoided;
use App\Modules\Accounting\Extensions\BillDefaults;
use App\Modules\Accounting\Extensions\InvoiceMutationGuard;
use App\Modules\Accounting\Models\Invoice;
use Illuminate\Support\Facades\Event;

test('re-registering a hook under the same key replaces it', function () {
    $calls = 0;
    InvoiceMutationGuard::extend('t', function () use (&$calls) { $calls++; });
    InvoiceMutationGuard::extend('t', function () use (&$calls) { $calls++; });
    app(InvoiceMutationGuard::class)->assert(new Invoice, 'update');
    expect($calls)->toBe(1);
    InvoiceMutationGuard::extend('t', fn () => null);
});

test('fuel registers its invoice guard, bill default and void listener', function () {
    expect(array_keys(InvoiceMutationGuard::hooks()))->toContain('fuel_station')
        ->and(array_keys(BillDefaults::hooks()))->toContain('fuel_station')
        ->and(Event::hasListeners(InvoiceVoided::class))->toBeTrue();
});

test('core accounting no longer imports FuelStation classes', function () {
    $hits = shell_exec('grep -rlE "Modules.FuelStation" '.base_path('modules/Accounting/Models/Invoice.php').' '.base_path('modules/Accounting/Actions/Invoice').' '.base_path('modules/Accounting/Http/Controllers/BillController.php').' '.base_path('modules/Accounting/Http/Controllers/CustomerController.php'));
    expect(trim((string) $hits))->toBe('');
});
```

Add one behaviour test to the existing temporary-stock suite. Find it with `grep -rl "TemporaryFuelStock" tests/Feature/FuelStation`. Use that file's fixture as it stands, then void the invoice and assert the temporary stock is reversed **exactly once**: count the reversing `inv.stock_movements` rows for the invoice and assert 1. This pins Review Focus 2.

Add one rollback test (Review Focus 4) to `AccountingHooksTest.php`, using the `statementFixture()` style from `tests/Feature/Accounting/CustomerStatementTest.php` to build a company and a sent invoice:

```php
test('a throwing void listener rolls the whole void back', function () {
    $f = statementFixture(); // copy the fixture function into this file under the name hooksFixture()
    // ... create + send an invoice exactly as CustomerStatementTest does ...
    Event::listen(InvoiceVoided::class, fn () => throw new RuntimeException('boom'));
    expect(fn () => app(\App\Services\CompanyContextService::class)->withContext($f['company'],
        fn () => app(\App\Services\CommandBus::class)->dispatch('invoice.void', ['id' => $invoiceId, 'reason' => 'x'], $f['user'], true)))
        ->toThrow(RuntimeException::class);
    expect(Invoice::find($invoiceId)->status)->not->toBe('void');
});
```

- [ ] **Step 2: The trait**

```php
<?php
// app/Support/Extensions/HasHooks.php

namespace App\Support\Extensions;

/**
 * A keyed static hook list for a core extension point. A module registers from
 * its provider's boot(); the key (normally the module key) makes registration
 * idempotent, because providers boot again on every app instance (Octane, each
 * test) while static state survives.
 */
trait HasHooks
{
    /** @var array<string, callable> */
    private static array $hooks = [];

    public static function extend(string $key, callable $hook): void
    {
        self::$hooks[$key] = $hook;
    }

    public static function flushHooks(): void
    {
        self::$hooks = [];
    }

    /** @return array<string, callable> */
    public static function hooks(): array
    {
        return self::$hooks;
    }
}
```

- [ ] **Step 3: The three hook classes**

```php
<?php
// modules/Accounting/Extensions/InvoiceMutationGuard.php

namespace App\Modules\Accounting\Extensions;

use App\Modules\Accounting\Models\Invoice;
use App\Support\Extensions\HasHooks;

/**
 * Lets a module veto a change to an invoice it has built on (e.g. a fuel daily
 * close's credit sale). Hooks throw to block; $operation is 'save', 'delete',
 * 'update' or 'void'.
 */
class InvoiceMutationGuard
{
    use HasHooks;

    public function assert(Invoice $invoice, string $operation): void
    {
        foreach (self::hooks() as $hook) {
            $hook($invoice, $operation);
        }
    }
}
```

```php
<?php
// modules/Accounting/Extensions/BillDefaults.php

namespace App\Modules\Accounting\Extensions;

use App\Models\Company;
use App\Support\Extensions\HasHooks;

/** The bill form's default line account, when a module has one (first answer wins). */
class BillDefaults
{
    use HasHooks;

    public function lineAccountId(Company $company): ?string
    {
        foreach (self::hooks() as $hook) {
            if (($id = $hook($company)) !== null) {
                return $id;
            }
        }

        return null;
    }
}
```

```php
<?php
// modules/Accounting/Extensions/CustomerPages.php

namespace App\Modules\Accounting\Extensions;

use App\Models\Company;
use App\Modules\Accounting\Models\Customer;
use App\Support\Extensions\HasHooks;

/** Where a saved customer returns to, when a module keeps its own customer page. */
class CustomerPages
{
    use HasHooks;

    public function afterSaveUrl(Company $company, Customer $customer): ?string
    {
        foreach (self::hooks() as $hook) {
            if (($url = $hook($company, $customer)) !== null) {
                return $url;
            }
        }

        return null;
    }
}
```

```php
<?php
// modules/Accounting/Events/InvoiceVoided.php

namespace App\Modules\Accounting\Events;

use App\Modules\Accounting\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired inside VoidAction's transaction, before the invoice row is marked void.
 * Listeners run synchronously; a throw rolls the void back. Never make a
 * listener queued or ShouldDispatchAfterCommit.
 */
class InvoiceVoided
{
    use Dispatchable;

    public function __construct(public Invoice $invoice, public string $reversalDate, public ?string $userId) {}
}
```

- [ ] **Step 4: Rewire the core sites**

`Invoice.php` saving (line 44), inside the existing `if (...)`, keeping the condition untouched:
```php
app(\App\Modules\Accounting\Extensions\InvoiceMutationGuard::class)->assert($invoice, 'save');
```
deleting (line 49):
```php
app(\App\Modules\Accounting\Extensions\InvoiceMutationGuard::class)->assert($invoice, 'delete');
```
Delete the `saleMetadata()` method (lines 168-172). The relation moves to FuelStation in Step 5. Check callers first: `grep -rn "saleMetadata" modules app resources`. Every caller must be in FuelStation or work through a dynamic relation of the same name.

`UpdateAction.php:73-77`: replace the `assertMutable` line **and** the temporary-stock `if` block with:
```php
app(\App\Modules\Accounting\Extensions\InvoiceMutationGuard::class)->assert($invoice, 'update');
```

`VoidAction.php:44` becomes `->assert($invoice, 'void');`. `VoidAction.php:93` becomes:
```php
\App\Modules\Accounting\Events\InvoiceVoided::dispatch($invoice, $params['reversal_date'] ?? now()->toDateString(), auth()->id());
```

`BillController.php`: in both places, replace the `$isFuelStation = …; $defaultExpenseAccountId = $isFuelStation ? (...) : null;` block with:
```php
$defaultExpenseAccountId = app(\App\Modules\Accounting\Extensions\BillDefaults::class)->lineAccountId($company);
```

`CustomerController.php:419-425`: replace the fuel block with:
```php
if ($url = app(\App\Modules\Accounting\Extensions\CustomerPages::class)->afterSaveUrl($company, $customer)) {
    return redirect($url)->with(/* the same flash the fuel branch used — copy it verbatim */);
}
```

`ConsolidatedInvoiceService.php:136-140`: replace the `$fuel` test with:
```php
$registry = app(\App\Support\Modules\ModuleRegistry::class);
// 'item' => $registry->label($company, 'consolidated.item', 'Item'),
// 'quantity' => $registry->label($company, 'consolidated.quantity', 'Qty'),
```
Keep the other keys of that array exactly as they are.

- [ ] **Step 5: Register the fuel side** (in `FuelStationServiceProvider::boot()`)

```php
use App\Modules\Accounting\Events\InvoiceVoided;
use App\Modules\Accounting\Extensions\{BillDefaults, CustomerPages, InvoiceMutationGuard};
use App\Modules\Accounting\Models\{Account, Invoice};
use App\Modules\FuelStation\Services\{DailyCloseCreditSaleService, TemporaryFuelStockService};
use Illuminate\Support\Facades\Event;

InvoiceMutationGuard::extend('fuel_station', function (Invoice $invoice, string $operation): void {
    app(DailyCloseCreditSaleService::class)->assertMutable($invoice);

    if ($operation === 'update' && \App\Modules\Inventory\Models\StockMovement::where('company_id', $invoice->company_id)
        ->where('reference_type', 'acct.invoices')->where('reference_id', $invoice->id)->where('movement_type', 'sale')
        ->whereIn('warehouse_id', \App\Modules\Inventory\Models\Warehouse::where('company_id', $invoice->company_id)->where('code', TemporaryFuelStockService::CODE)->select('id'))
        ->exists()) {
        throw new \RuntimeException('This sale issued temporary fuel stock. Cancel it and record the corrected sale so quantity and cost stay together.');
    }
});

Event::listen(InvoiceVoided::class, \App\Modules\FuelStation\Listeners\ReverseTemporaryFuelStock::class);

BillDefaults::extend('fuel_station', function ($company): ?string {
    if (! $company->hasModule('fuel_station')) {
        return null;
    }

    return Account::where('company_id', $company->id)->where('code', '1200')->where('is_active', true)->value('id')
        ?? Account::where('company_id', $company->id)->where('subtype', 'inventory')->where('is_active', true)->orderBy('code')->value('id');
});

CustomerPages::extend('fuel_station', fn ($company, $customer): ?string => $company->hasModule('fuel_station')
    ? route('fuel.credit-customers.show', ['company' => $company->slug, 'customer' => $customer->id])
    : null);

Invoice::resolveRelationUsing('saleMetadata', fn (Invoice $invoice) => $invoice->hasOne(\App\Modules\FuelStation\Models\SaleMetadata::class, 'invoice_id'));
```

Notes:
- `assertMutable` ran for **every** company before this change. It stays ungated here, which keeps behaviour identical (it is a no-op for invoices no close references).
- `Event::listen` is **not** idempotent. Wrap the listen in `if (! Event::hasListeners(InvoiceVoided::class))`, or register the listener in `register()` with `$this->app->booted(...)` guarded the same way. The "exactly once" test pins this.

```php
<?php
// modules/FuelStation/Listeners/ReverseTemporaryFuelStock.php

namespace App\Modules\FuelStation\Listeners;

use App\Modules\Accounting\Events\InvoiceVoided;
use App\Modules\FuelStation\Services\TemporaryFuelStockService;

class ReverseTemporaryFuelStock
{
    public function handle(InvoiceVoided $event): void
    {
        app(TemporaryFuelStockService::class)->reverse($event->invoice, $event->reversalDate, $event->userId);
    }
}
```

- [ ] **Step 6: Gate on grep, then commit**

```bash
cd build && grep -rnE "Modules.FuelStation" modules/Accounting/Models modules/Accounting/Actions/Invoice modules/Accounting/Http/Controllers/BillController.php modules/Accounting/Http/Controllers/CustomerController.php modules/Accounting/Services/ConsolidatedInvoiceService.php
```
Expected: no output.
```bash
git add -A && git commit -m "Move invoice guards, void side effect, bill default and customer redirect behind Accounting hooks"
```
Tests (on approval): `php artisan test tests/Feature/Modules`, then the FuelStation file that covers temporary stock and the daily-close credit sale lock.

---

## Task 3: Payment receipt modes (amanat)

**Files:**
- Create: `modules/Accounting/Extensions/PaymentReceiptModes.php`
- Create: `modules/FuelStation/Http/AmanatReceiptMode.php`
- Modify: `modules/Accounting/Http/Controllers/PaymentController.php:13,26-49,152,164-166,221-end of storeAmanat`
- Modify: `modules/Accounting/Http/Requests/StorePaymentRequest.php` (the `received_as` rule)
- Modify: `modules/Accounting/Resources/js/pages/payments/Create.vue` (the `amanat` prop becomes `receiptModes`)
- Modify: `FuelStationServiceProvider::boot()`
- Test: `tests/Feature/Modules/PaymentReceiptModesTest.php`

**Interfaces:**
- Consumes: `HasHooks` (Task 2)
- Produces: `PaymentReceiptModes::register(string $key, string $label, callable $available, callable $store)`, where `$available = fn (Company, ?User): bool` and `$store = fn (StorePaymentRequest, Company, array $validated): RedirectResponse`. Also `PaymentReceiptModes::availableFor(Company, ?User): array<int, array{key: string, label: string}>` and `PaymentReceiptModes::handler(string $key): ?callable`. The page prop is `receiptModes: {key, label}[]`. `invoices` is implicit and always first in the UI.

- [ ] **Step 1: Failing tests** (Review Focus 3)

```php
test('a general company with account 2200 is offered no amanat mode', function () {
    // company with industry_code 'other' and an active 2200 account (name it 'Accrued Expenses')
    // GET payments.create as its owner → assertInertia(fn ($p) => $p->where('receiptModes', []))
});

test('a fuel company user with the amanat permission gets the amanat mode', function () {
    // industry_code 'fuel_station', user with the permission AmanatMovementAction::permission() returns
    // → receiptModes = [['key' => 'amanat', 'label' => 'Amanat']]
});

test('posting received_as=amanat for a general company is rejected', function () {
    // POST payments.store with received_as=amanat → session error on received_as
});
```
Write these out in full. Copy company/user/permission setup from an existing test that grants a company permission: `grep -rln "hasCompanyPermission\|givePermissionTo" tests/Feature | head`.

- [ ] **Step 2: Registry**

```php
<?php
// modules/Accounting/Extensions/PaymentReceiptModes.php

namespace App\Modules\Accounting\Extensions;

use App\Models\Company;

/**
 * Other ways to record money received from a customer than "against invoices",
 * contributed by modules (e.g. FuelStation's amanat deposit). Keyed by mode.
 */
class PaymentReceiptModes
{
    /** @var array<string, array{label: string, available: callable, store: callable}> */
    private static array $modes = [];

    public static function register(string $key, string $label, callable $available, callable $store): void
    {
        self::$modes[$key] = ['label' => $label, 'available' => $available, 'store' => $store];
    }

    public static function flush(): void
    {
        self::$modes = [];
    }

    /** @return array<int, array{key: string, label: string}> */
    public function availableFor(Company $company, $user): array
    {
        $out = [];
        foreach (self::$modes as $key => $mode) {
            if (($mode['available'])($company, $user)) {
                $out[] = ['key' => $key, 'label' => $mode['label']];
            }
        }

        return $out;
    }

    public function handler(Company $company, $user, string $key): ?callable
    {
        $mode = self::$modes[$key] ?? null;

        return $mode && ($mode['available'])($company, $user) ? $mode['store'] : null;
    }
}
```

- [ ] **Step 3: Move `storeAmanat` and `amanatAvailable` into `AmanatReceiptMode`.** Copy the bodies verbatim from `PaymentController` into two public methods: `available(Company $company, $user): bool`, which keeps the `hasModule('fuel_station')` test plus the permission check, and `store(StorePaymentRequest $request, Company $company, array $validated): RedirectResponse`. Register it in the fuel provider:

```php
$amanat = new \App\Modules\FuelStation\Http\AmanatReceiptMode;
\App\Modules\Accounting\Extensions\PaymentReceiptModes::register('amanat', 'Amanat', [$amanat, 'available'], [$amanat, 'store']);
```

- [ ] **Step 4: Core controller.** Delete `amanatAvailable`, `storeAmanat` and the `AmanatMovementAction` import. In `create()`, replace `'amanat' => …` with:

```php
'receiptModes' => app(\App\Modules\Accounting\Extensions\PaymentReceiptModes::class)->availableFor($company, $request->user()),
```

In `store()`, replace the amanat branch with:

```php
$mode = $validated['received_as'] ?? 'invoices';
if ($mode !== 'invoices') {
    $handler = app(\App\Modules\Accounting\Extensions\PaymentReceiptModes::class)->handler($company, $request->user(), $mode);
    if (! $handler) {
        throw ValidationException::withMessages(['received_as' => 'This option is not available.']);
    }

    return $handler($request, $company, $validated);
}
```

In `StorePaymentRequest`, the `received_as` rule becomes `['nullable', 'string', 'max:40']`. Membership is checked by `handler()` above.

- [ ] **Step 5: `payments/Create.vue`.** Find every use of `props.amanat` (`grep -n "amanat" modules/Accounting/Resources/js/pages/payments/Create.vue`). Replace the prop with `receiptModes: { key: string; label: string }[]`. Show the "received as" choice only when `receiptModes.length > 0`. The options are `[{ key: 'invoices', label: 'Against invoices' }, ...receiptModes]`. Fuel copy in this file (line 376, "Held for the customer and used up against fuel.") becomes a per-mode hint. Add `hint?: string` to the registry tuple and pass it from the fuel registration. Don't hardcode it.

- [ ] **Step 6: Commit**

```bash
cd build && grep -n "amanat\|Amanat" modules/Accounting/Http/Controllers/PaymentController.php   # expect: no output
git add -A && git commit -m "Make payment receipt modes an extension point; move amanat receipt to FuelStation"
```

---

## Task 4: Statement kinds (amanat) and statement links (stock)

**Files:**
- Create: `modules/Accounting/Extensions/StatementKinds.php`
- Create: `modules/FuelStation/Reports/AmanatStatementKind.php`
- Modify: `modules/Accounting/Http/Controllers/StatementReportController.php` (lines 17, 46, 56-71, 114, 126, 152, 401-417)
- Modify: `modules/Accounting/Http/Requests/StatementReportRequest.php` (the `kind` rule)
- Modify: `modules/Accounting/Resources/js/pages/reports/Statement.vue:165-169,278` and the amanat tab
- Test: `tests/Feature/Modules/StatementKindsTest.php`

**Interfaces:**
- Produces: the `StatementKind` interface:

```php
interface StatementKind {
    public function key(): string;                                   // 'amanat'
    public function label(): string;                                 // 'Amanat'
    public function available(Company $company): bool;
    /** Parties to list; each has id + name (+ customer_number when relevant). */
    public function options(Company $company): \Illuminate\Support\Collection;
    /** Customer ids to drop from the Customer kind's list (holders who aren't credit customers). */
    public function hiddenCustomerIds(Company $company): array;
    /** @return array{0: array, 1: array, 2: ?string} [statement, columns, resolvedId] — same shape amanatStatement() returns today. */
    public function statement(\Illuminate\Support\Collection $options, ?string $id, string $from, string $to, bool $includeTrail): array;
}
```

  Also: the `StatementKinds` registry with `register(StatementKind)`, `availableFor(Company): array<string, StatementKind>` and `links(Company): array<int, array{key: string, label: string, href: string}>`; and `registerLink(string $key, string $label, callable $available, callable $href)`, where `$href = fn (Company, string $from, string $to): string`. Page props: `extraKinds: {key, label}[]` and `kindLinks: {key, label, href}[]`.

- [ ] **Step 1: Failing tests.** For a general company: GET the statement page → `extraKinds` is `[]` and `kindLinks` is `[]`, and `?kind=amanat` returns a validation error. For a fuel company: `extraKinds` contains `amanat`, `kindLinks` contains `stock`, and `?kind=amanat&id=all` renders 200. Write them in full, with the fixture style from `CustomerStatementTest.php`.

- [ ] **Step 2: Move the amanat code.** `AmanatStatementKind::options()` holds the `fuel.customer_profiles` holder query (lines 58-61, filtered to holders). `hiddenCustomerIds()` returns the ids that are holders and *not* credit customers. `statement()` is the body of `amanatStatement()`, verbatim.

- [ ] **Step 3: Core controller.**
  - Line 46's list becomes the core kinds plus `array_keys($kinds)`.
  - Customers become `$allCustomers->reject(fn ($c) => in_array($c->id, $hidden, true))`, where `$hidden` merges `hiddenCustomerIds()` across the available kinds.
  - Both `match` expressions get `default => $kinds[$kind]->statement(...)` for registered keys. Bank stays the final default: check `isset($kinds[$kind])` before the `match`.
  - `options` merges `[$key => $kind->options($company)]`.
  - Add `extraKinds` and `kindLinks`.
  - In `StatementReportRequest`, the `kind` rule becomes `Rule::in([...core, ...array_keys(app(StatementKinds::class)->availableFor($company))])`.
  - The fuel provider registers the stock link: `registerLink('stock', 'Stock', fn ($c) => $c->hasModule('fuel_station'), fn ($c, $from, $to) => "/{$c->slug}/fuel/reports/stock-statement?start_date={$from}&end_date={$to}")`.

- [ ] **Step 4: `Statement.vue`.** Remove `isFuelStation` (line 165) and the hardcoded stock redirect (169). Render `<TabsTrigger v-for="k in extraKinds" :value="k.key">{{ k.label }}</TabsTrigger>` after the core tabs. Kind links become tabs that `router.visit(link.href)` on select. Make the amanat tab content generic: it uses the same party-picker as Customer, driven by `options[kind]`. Read the existing amanat branch of the template and make it a `v-else` for "any extra kind".

- [ ] **Step 5: Commit**

```bash
cd build && grep -n "amanat\|Amanat\|fuel" modules/Accounting/Http/Controllers/StatementReportController.php   # expect: no output
git add -A && git commit -m "Make statement kinds and links extension points; move amanat statement to FuelStation"
```

---

## Task 5: Opening-balance sections (amanat), plus the 2200 leak fix

**Files:**
- Create: `modules/Accounting/Extensions/OpeningBalanceSection.php` (interface)
- Create: `modules/Accounting/Extensions/OpeningBalanceSections.php` (registry)
- Create: `modules/FuelStation/OpeningBalances/AmanatOpeningSection.php`
- Modify: `modules/Accounting/Actions/OpeningBalance/SaveAction.php` (lines 23-24, 74-76, 132, 210, 313, 487-547, 691-722)
- Modify: `modules/Accounting/Actions/OpeningBalance/ViewAction.php` (lines 18, 81, 115, 134)
- Modify: `modules/Accounting/Services/OpeningBalanceAccounts.php:26` (remove `amanat`)
- Modify: `app/Http/Middleware/HandleInertiaRequests.php:210-214` (`hasAmanat` becomes `sections`)
- Modify: `resources/js/components/forms/QuickAddModal.vue:271`
- Modify: `modules/Accounting/Http/Controllers/CustomerController.php` quick-add (lines ~586-597, 617-635)
- Modify: `modules/Accounting/Resources/js/pages/opening-balances/Index.vue`
- Test: `tests/Feature/Modules/OpeningBalanceSectionsTest.php`

**Interfaces:**
```php
interface OpeningBalanceSection {
    public function key(): string;            // request/payload key, also the error key: 'amanat'
    public function label(): string;          // 'Amanat depositors'
    public function party(): string;          // 'customer' — rows carry {party}_id / {party}_name
    public function side(): string;           // 'liability' | 'asset'
    public function available(Company $company): bool;
    public function rules(): array;           // e.g. ['amanat' => 'nullable|array', 'amanat.*.customer_id' => [...], 'amanat.*.amount' => 'required|numeric|gt:0']
    /** Append journal lines and queued side effects, exactly as addAmanatLines() does today. */
    public function addLines(string $companyId, array $params, array &$lines, array &$pending): void;
    /** Throw when re-entering would orphan drawn-down balances; return state for undo(). */
    public function prepareReentry(string $companyId, array $entryIds): mixed;
    public function undo(string $companyId, mixed $state): void;
    /** Rows for ViewAction: [{customer_id, customer_name, amount}] (keys follow party()). */
    public function rows(string $companyId, array $entryIds): array;
}
```
- `OpeningBalanceSections`: `register(OpeningBalanceSection)` (keyed), `availableFor(Company): array<string, OpeningBalanceSection>`, `all(): array<string, OpeningBalanceSection>`.
- `opening.sections` prop: `{key, label, party, side}[]`.
- The shared `auth.openingBalance` (or wherever `hasAmanat` lives; check the parent key at `HandleInertiaRequests.php:~196`) gets `sections: string[]` in place of `hasAmanat`.

- [ ] **Step 1: Failing tests** (Review Focus 3)

```php
test('a general company with a 2200 account gets no amanat opening section', function () {
    // industry_code 'other' + active 2200 'Accrued Expenses'
    // GET opening-balances.index → opening.sections == [] and shared openingBalance.sections == []
    // dispatch opening_balance.save with an 'amanat' row → it is ignored or rejected (assert no journal line credits 2200)
});

test('a fuel company still saves, views and re-enters opening amanat exactly as before', function () {
    // Copy the existing amanat opening-balance test (grep -rln "amanat" tests/Feature | xargs grep -l "opening") and keep its assertions.
});
```

- [ ] **Step 2: Move the code.** `AmanatOpeningSection` takes:
  - `addAmanatLines` (SaveAction 691-722), verbatim. It resolves the 2200 account itself; that code moves here from `OpeningBalanceAccounts`.
  - The drawn-down check (487-514) as `prepareReentry`.
  - The undo loop (544-547) as `undo`.
  - The ViewAction query (81) as `rows`.
  - `available()` = `$company->hasModule('fuel_station')`.

- [ ] **Step 3: Generic core.**
  - SaveAction: validation rules merge `rules()` from available sections.
  - The two `addAmanatLines` calls become a loop over available sections.
  - The `set_party` map at line 313 gets each section's `key() => party().'_id'`.
  - The re-entry check and undo loop over **all** registered sections (`all()`, not `availableFor`), so a company that later loses the module can still re-enter without orphaning.
  - ViewAction: the rows for each available section go in `rows[key]`. Liabilities add sections whose `side()` is `liability`. Add `sections`.

- [ ] **Step 4: Frontend.**
  - `opening-balances/Index.vue`: replace the hardcoded amanat section (line ~375) with a `v-for` over `opening.sections`. Each section gets the same party-row editor the amanat block uses today, with the party picker chosen by `section.party`. `form[section.key]` holds the rows, so the payload shape `amanat: [{customer_id, amount}]` is unchanged.
  - The `liabilities` and `rowCount` computeds sum the sections dynamically.
  - `QuickAddModal.vue:271`: `openingBalance?.hasAmanat` becomes `openingBalance?.sections?.includes('amanat')`. The field is still labelled per section, so read the label from the server instead of hardcoding it.

- [ ] **Step 5: Commit**

```bash
cd build && grep -rn "Amanat\|'2200'" modules/Accounting/Actions/OpeningBalance modules/Accounting/Services/OpeningBalanceAccounts.php app/Http/Middleware/HandleInertiaRequests.php   # expect: no output
git add -A && git commit -m "Make opening-balance sections an extension point; stop amanat leaking into general companies"
```

---

## Task 6: Inventory and Payroll hooks

**Files:**
- Create: `modules/Inventory/Events/OpeningStockChanged.php`
- Create: `modules/Inventory/Extensions/ItemPriceHistory.php`
- Create: `modules/Inventory/Extensions/ItemAccountDefaults.php`
- Create: `modules/Inventory/Extensions/StockReferenceLinks.php`
- Create: `modules/FuelStation/Listeners/SyncOpeningStockLedger.php`
- Modify: `modules/Inventory/Services/{ItemPriceService,OpeningStockService,StockAdjustmentService}.php`, `modules/Inventory/Models/Warehouse.php:89-92`, `modules/Inventory/Http/Controllers/{ItemController,StockController,WarehouseController}.php`
- Modify: `modules/Payroll/Http/Controllers/SalaryAdvanceController.php:78-80`
- Modify: `app/Actions/Company/UpdateModulesAction.php:35-38`
- Modify: `FuelStationServiceProvider::boot()`
- Test: `tests/Feature/Modules/InventoryHooksTest.php`

**Interfaces:**
- `OpeningStockChanged(string $companyId, ?string $userId, bool $quiet)`. `quiet=true` replaces `syncQuietly`, `false` replaces `sync`.
- `ItemPriceHistory`, with three hook kinds keyed by module:
  - `extendTimeline(string $key, callable $fn)`, where `$fn = fn (Item $item, string $companyId, string $companySlug): ?array` returns `['replaces_sale_prices' => bool, 'rows' => array]` or null.
  - `extendGuard(string $key, callable $fn)`, where `$fn = fn (Item $item, string $companyId, string $date): void` throws.
  - `extendCorrectionTypes(string $key, array $transactionTypes)` covers the lubricant-cost lookup at `ItemPriceService.php:170`.
- `ItemAccountDefaults::for(Company $company, array $data, ?string $userId): ?array` returns `['asset_account_id' => …, 'income_account_id' => …, 'cogs_account_id' => …]` or null. Use the key names `ItemController` sets today.
- `StockReferenceLinks::for(?string $referenceType, ?string $transactionType, $movement, string $base): ?array{label: string, href: ?string}`.

- [ ] **Step 1: Failing tests.**
  1. Saving opening stock for a general company dispatches `OpeningStockChanged` once (`Event::fake([OpeningStockChanged::class])`).
  2. A fuel item's price timeline still contains a `fuel_rate` row. Reuse the fixture from the item-price test: `grep -rln "fuel_rate\|priceHistory" tests/Feature`.
  3. `UpdateModulesAction` still refuses to disable inventory for a fuel company, and the message is unchanged.
  4. `grep` for `Modules.FuelStation` under `modules/Inventory` and `modules/Payroll` returns nothing.

- [ ] **Step 2: Rewire.**
  - `OpeningStockService.php:62` and `StockAdjustmentService.php:139` dispatch the event. The fuel listener calls the same service method as before. It stays **ungated**, because it ran for every company before; check that `OpeningStockLedgerService` no-ops for companies with no fuel items, and if it doesn't, keep it ungated anyway and note it.
  - `ItemPriceService`: drop the constructor's `DailyCloseLockService`.
    - Line 35's `fuel_category` refusal and the month-lock check move into a fuel `extendGuard`; copy the message text verbatim.
    - Lines 121-130 move into a fuel `extendTimeline`.
    - Line 170's `LubricantCostService::TYPE` comes from `extendCorrectionTypes`.
  - `ItemController.php:203-207`: call `ItemAccountDefaults::for(...)` when `$stocked && empty($data['asset_account_id'])`. Fuel registers the mapper call behind `hasModule('fuel_station')`. That widens from `industry_code` only; Task 1 notes the widening.
  - `StockController.php:228,309-322`: the fuel reference branches become a fuel `StockReferenceLinks` hook. Line 228 checks `fuel.daily_close` for a different purpose; read it. If it's a guard, it becomes a hook returning a reason.
  - `Warehouse.php:89-92`: delete `dipStick()`. The fuel provider adds `Warehouse::resolveRelationUsing('dipStick', fn ($w) => $w->belongsTo(\App\Modules\FuelStation\Models\DipStick::class))`. `convertToLiters` stays and keeps working through the dynamic relation.
  - `WarehouseController.php:84-99`: query `fuelItems` only when `$company->hasModule('fuel_station')`; otherwise pass `[]`.
  - `SalaryAdvanceController.php:78-80` becomes `$usesDailyClose = $company->capable('daily_close');`.
  - `UpdateModulesAction.php:35`: loop over `ModuleRegistry::enabledFor($company)`. If a manifest `requires()` contains `'inventory'` and inventory is being disabled, throw the same message, built with the module's display name. Add `name(): string` to `ModuleManifest` (default `ucfirst(str_replace('_', ' ', $this->key()))`); FuelStationModule returns `'Fuel Station'`, so the message stays byte-identical.

- [ ] **Step 3: Commit**

```bash
cd build && grep -rnE "Modules.(FuelStation|Umrah)|fuel_station" modules/Inventory modules/Payroll app/Actions --include=*.php   # expect: no output
git add -A && git commit -m "Move fuel behaviour in Inventory and Payroll behind hooks and events"
```

---

## Task 7: App shell through the manifest

**Files:**
- Modify: `app/Support/Modules/ModuleManifest.php` (add the methods below)
- Modify: `modules/FuelStation/FuelStationModule.php`, `modules/Umrah/UmrahModule.php`
- Create: `modules/FuelStation/Http/FuelHome.php` (body moved from `CompanyController`)
- Modify: `app/Http/Controllers/{CompanyController,CompaniesPageController,CompanyOnboardingController}.php`
- Modify: `app/Services/{CompanyBootstrapService,SettingsMenu,UserPermissionPresenter,PartnerProfitShareService}.php`
- Modify: `modules/Accounting/Services/CompanyOnboardingService.php:88-94,245-250`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php:8,161-176`
- Test: `tests/Feature/Modules/ModuleShellTest.php`

**Interfaces:** methods added to `ModuleManifest`, each with the default shown:
```php
public function name(): string                                              // (added in Task 6)
public function homeUrl(Company $c): ?string { return null; }               // after create / companies page
public function homeResponse(Request $r, Company $c): mixed { return null; } // CompanyController::show; null = core home
public function onboardingUrl(Company $c): ?string { return null; }         // onboarding redirects
public function prepare(Company $c, ?string $userId): void {}               // installer + defaults at bootstrap/identity step
public function afterBankAccounts(Company $c, array $syncIds): bool { return false; } // true = it completed 'default-accounts'
public function sharedProps(): array { return []; }                         // ['fuelNavigation' => fn (Company, User) => mixed]
public function settingsSections(Company $c, User $u, callable $section, callable $item, callable $can): array { return []; }
public function ownsPermissions(): bool { return false; }                   // umrah: show only own + payroll
public function permissionPrefixes(): array { return []; }
public function permissionCapabilities(Company $c, string $role, callable $has): ?array { return null; }
public function netProfit(Company $c, string $from, string $to): ?float { return null; }
```

- [ ] **Step 1: Failing tests.**
  1. A general company's `/{slug}` renders `company/Show`; a fuel company's renders `FuelStation/Home/Index`; an umrah company's redirects to `umrah.dashboard`.
  2. The shared `fuelNavigation` is null for a general company.
  3. `SettingsMenu` for a general company contains no section titled "Station settings".
  4. Creating a company with `industry_code=travel` enables `umrah` and disables `fuel_station`, as `CompanyBootstrapService:114-116` does today.

- [ ] **Step 2: Move each branch**, keeping its body verbatim:
  - `CompanyController.php:271-335` moves to `FuelHome::render(Request, Company)`. `FuelStationModule::homeResponse` calls it.
  - Umrah's `homeResponse` returns `redirect()->route('umrah.dashboard', …)`.
  - `CompanyController::renderShow` starts with: `foreach ($registry->enabledFor($company) as $m) { if (($r = $m->homeResponse($request, $company)) !== null) return $r; }`.
  - Lines 135-136, `CompaniesPageController.php:102-107` and `CompanyController.php:578-633` (`isFuelStation`/`isUmrah` props) use `homeUrl()` and `enabledModules`. Check the readers of the `isFuelStation`/`isUmrah` props in `company/Settings.vue` and `company/Show.vue`; they move to `usePage().props.enabledModules`.
  - Onboarding:
    - `CompanyOnboardingController.php:41,113-119,160,208,301` use `onboardingUrl()`.
    - Lines 83-110 use `prepare()`.
    - `CompanyBootstrapService.php:101-117` uses `prepare()` plus enable/conflicts.
    - `CompanyOnboardingService.php:88-94` becomes `ModuleRegistry::applyIndustry(Company, string $code)`: enable every manifest whose `industryCodes()` contains the code, then disable each of its `conflicts()`.
    - `CompanyOnboardingService.php:245-250` loops `afterBankAccounts()`. When one returns true, run the two `$onboarding` step calls that line 248-249 runs today.
  - `HandleInertiaRequests`: delete the `fuelNavigation` closure and the import. For every manifest in `all()`, and every `name => fn` in its `sharedProps()`, share `name => fn () => ($c = current company) && $user && $m->isEnabledFor($c) ? $fn($c, $user) : null`. The key stays present and null for other companies, which keeps the frontend types intact.
  - `SettingsMenu.php:44-69` moves into `FuelStationModule::settingsSections`, verbatim, using the passed `$section`, `$item` and `$can` closures. Core appends the results where the fuel sections sit today (keep the order: station settings and equipment where they were, Help at the end). Add an `order` key if needed.
  - `UserPermissionPresenter`: `belongsToEnabledModule` → if any enabled manifest `ownsPermissions()`, allow only its `permissionPrefixes()` plus payroll. Otherwise reject the prefixes of every **disabled** manifest. That is broader than today's "reject umrah only": a general company's users stop seeing fuel permissions too. This is a display-only change; call it out in the commit. `capabilities()` (lines 47-63) moves to `UmrahModule::permissionCapabilities`.
  - `PartnerProfitShareService.php:165` asks each enabled manifest's `netProfit()`, falling back to the ledger P&L. Find the `DailyCloseLockService` usage in the same file (`grep -n DailyCloseLock app/Services/PartnerProfitShareService.php`). It is a lock check, so route it through `DocumentDateLock` if the semantics match (day lock). If not, add `ModuleManifest::periodLockReason(Company, string $from, string $to): ?string`.

- [ ] **Step 3: Commit**

```bash
cd build && grep -rnE "Modules.(FuelStation|Umrah)|'fuel_station'|'umrah'|'travel'" app modules/Accounting --include=*.php | grep -v "app/Console"   # expect: RequireIndustry route usage only
git add -A && git commit -m "Route home, onboarding, shared props, settings menu and permission display through module manifests"
```

---

## Task 8: Fuel commands, schedule, command-bus keys, routes

**Files:**
- Move: `app/Console/Commands/{RecostFuelCloses,FuelMonthEndValuation,FuelLubricantCost,LockMonthlyDailyCloses}.php` → `modules/FuelStation/Console/` (namespace `App\Modules\FuelStation\Console`)
- Modify: `routes/console.php:14` (remove; schedule from the provider)
- Modify: `config/command-bus.php:5-12,146-147` (remove; merge from the provider)
- Modify: `routes/web.php:557-558` (remove the `require` only if Step 1 shows the provider registers the same routes)
- Modify: `FuelStationServiceProvider`

- [ ] **Step 1: Find out whether fuel routes are registered twice.**
  ```bash
  cd build && php artisan route:list --json | php -r '$r=json_decode(stream_get_contents(STDIN),true); $c=array_count_values(array_map(fn($x)=>$x["method"]." ".$x["uri"],$r)); print_r(array_filter($c,fn($n)=>$n>1));'
  ```
  If fuel URIs appear twice, the `require` in `web.php` is redundant: remove it. If they don't, Laravel is deduplicating by name. Still remove the `require` and keep the provider's `Route::middleware('web')->group(...)`. If route matching order matters, Step 4 shows it.

- [ ] **Step 2: Commands and schedule.** Move the four files and fix their namespaces. Then, in the provider:
  ```php
  if ($this->app->runningInConsole()) {
      $this->commands([
          \App\Modules\FuelStation\Console\RecostFuelCloses::class,
          \App\Modules\FuelStation\Console\FuelMonthEndValuation::class,
          \App\Modules\FuelStation\Console\FuelLubricantCost::class,
          \App\Modules\FuelStation\Console\LockMonthlyDailyCloses::class,
      ]);
      $this->callAfterResolving(\Illuminate\Console\Scheduling\Schedule::class, fn ($s) => $s->command('fuel:lock-month-closes')->monthlyOn(3, '02:00'));
  }
  ```
  Command signatures stay unchanged. Check `bootstrap/app.php` for `withCommands([...])` paths that auto-discover `app/Console/Commands`: moved commands must not be discovered twice.

- [ ] **Step 3: Command-bus keys.** In the provider's `register()`:
  ```php
  $this->app->booting(fn () => config(['command-bus' => array_merge(config('command-bus', []), [
      'fuel.settings.update' => \App\Modules\FuelStation\Actions\UpdateStationSettingsAction::class,
      // … the other 9 keys, copied verbatim from config/command-bus.php:5-12,146-147
  ])]));
  ```
  Open `tests/Feature/CommandBusMapTest.php` before doing this. If it reads `config/command-bus.php` as a file rather than through `config()`, update it to read the merged config.

- [ ] **Step 4: Gate on the route diff** (Review Focus 5)
  ```bash
  cd build && php artisan route:list --json > ../docs/superpowers/plans/baselines/2026-10-15-routes-after.json
  php -r '$a=json_decode(file_get_contents("../docs/superpowers/plans/baselines/2026-10-15-routes-before.json"),true);$b=json_decode(file_get_contents("../docs/superpowers/plans/baselines/2026-10-15-routes-after.json"),true);$k=fn($x)=>$x["method"]." ".$x["uri"]." ".$x["name"]." ".implode(",",(array)$x["middleware"]);$A=array_map($k,$a);$B=array_map($k,$b);print_r(array_diff($A,$B));print_r(array_diff($B,$A));echo "order-equal: ".(array_values(array_intersect($A,$B))===array_values(array_intersect($B,$A))?"yes":"no").PHP_EOL;'
  php artisan schedule:list | grep fuel
  php artisan list fuel
  ```
  Expected: both diffs empty, `order-equal: yes`, the fuel schedule is listed, and all four fuel commands are listed.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "Register fuel commands, schedule, command-bus keys and routes from the FuelStation provider"
```

---

## Task 9: Frontend: module slots, nav, widgets, copy

**Files:**
- Create: `resources/js/extensions/slots.ts`, `resources/js/extensions/index.ts`, `resources/js/components/ModuleSlot.vue`, `resources/js/composables/useModules.ts`
- Create: `modules/FuelStation/Resources/js/extensions.ts`, `modules/FuelStation/Resources/js/components/DirectDeliveryOption.vue`
- Create: `modules/Umrah/Resources/js/extensions.ts`
- Move: `resources/js/components/ProductQuickAddDialog.vue` → `modules/FuelStation/Resources/js/components/ProductQuickAddDialog.vue` (update its one importer)
- Move: the 9 umrah widget components in `resources/js/components/dashboard/widgets/` → `modules/Umrah/Resources/js/dashboard/widgets/`
- Modify: `resources/js/app.ts`, `resources/js/components/dashboard/widgets/index.ts`, `resources/js/composables/useNavGroups.ts`, `resources/js/components/AppHeader.vue`, `resources/js/pages/company/Settings.vue:211-227`, `modules/Accounting/Resources/js/pages/invoices/{Create,Edit}.vue`, `modules/Accounting/Http/Controllers/InvoiceController.php` (drop the `isFuelStation` props)
- Modify (copy/gating): the files listed under "Inside core module pages" in the coupling inventory

**Interfaces:**
- `registerSlot(name: string, entry: { module: string; component: Component; order?: number }): void`
- `slotEntries(name: string): SlotEntry[]`
- `registerWidget(key: string, component: Component): void`, exported from `widgets/index.ts`, with `widgetMap` kept as the read API
- `useModules(): { enabled: ComputedRef<string[]>; has: (key: string) => boolean }`
- `<ModuleSlot name="…" v-bind="anyProps" />`
- Slot names this task creates: `invoice.form.options`

- [ ] **Step 1: Registry and component**

```ts
// resources/js/extensions/slots.ts
import type { Component } from 'vue'

export interface SlotEntry { module: string; component: Component; order?: number }

const slots: Record<string, SlotEntry[]> = {}

/** A module adds UI to a named place in a core page. Re-registering a module's entry replaces it. */
export function registerSlot(name: string, entry: SlotEntry): void {
    const list = (slots[name] ??= []).filter((e) => e.module !== entry.module)
    list.push(entry)
    slots[name] = list.sort((a, b) => (a.order ?? 0) - (b.order ?? 0))
}

export function slotEntries(name: string): SlotEntry[] {
    return slots[name] ?? []
}
```

```ts
// resources/js/composables/useModules.ts
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

export function useModules() {
    const page = usePage()
    const enabled = computed<string[]>(() => (page.props.enabledModules as string[] | undefined) ?? [])

    return { enabled, has: (key: string) => enabled.value.includes(key) }
}
```

```vue
<!-- resources/js/components/ModuleSlot.vue -->
<script setup lang="ts">
import { computed } from 'vue'
import { useModules } from '@/composables/useModules'
import { slotEntries } from '@/extensions/slots'

defineOptions({ inheritAttrs: false })
const props = defineProps<{ name: string }>()
const { has } = useModules()
const entries = computed(() => slotEntries(props.name).filter((e) => has(e.module)))
</script>

<template>
    <component :is="e.component" v-for="e in entries" :key="e.module" v-bind="$attrs" />
</template>
```

```ts
// resources/js/extensions/index.ts — the one place core imports module UI (composition root, like navigation/registry.ts)
import '../../../modules/FuelStation/Resources/js/extensions'
import '../../../modules/Umrah/Resources/js/extensions'
```
Add `import './extensions'` to `resources/js/app.ts` directly after the CSS import.

- [ ] **Step 2: Direct delivery moves into a slot.** Cut the `<Card v-if="props.isFuelStation">…</Card>` block from `invoices/Create.vue:467-478` and `Edit.vue:427-438` into `DirectDeliveryOption.vue`, which takes `form` as a prop. The slot contract is that `form` is the page's shared Inertia form object, so mutating its fields is intended. Put `// eslint-disable-next-line vue/no-mutating-props` on the `v-model` line, with that reason. In both pages, put `<ModuleSlot name="invoice.form.options" :form="form" />` where the card was. Remove the `isFuelStation` prop from both pages and from `InvoiceController` (lines 124-126, 253-255). Then:
```ts
// modules/FuelStation/Resources/js/extensions.ts
import { registerSlot } from '@/extensions/slots'
import DirectDeliveryOption from './components/DirectDeliveryOption.vue'

registerSlot('invoice.form.options', { module: 'fuel_station', component: DirectDeliveryOption })
```

- [ ] **Step 3: Widgets.** In `widgets/index.ts`, keep `widgetMap` but start it empty for umrah keys, and add `export function registerWidget(key, component) { widgetMap[key] = component }`. Move the 9 umrah entries (with their `defineAsyncComponent` imports pointing at the new paths) into `modules/Umrah/Resources/js/extensions.ts` as `registerWidget(...)` calls. Check that `DashboardGrid` reads `widgetMap` at render time, not at import time. If it copies the map at import, change it to read on render.

- [ ] **Step 4: Nav and header.** `useNavGroups.ts:54-60`: `isFuelStationCompany = has('fuel_station')` and `isUmrahCompany = has('umrah')`, via `useModules`. The server's `enabledModules` already applies the industry fallback, so the industry computed (lines 45-52) can go once nothing else reads it (`grep -n "industry" resources/js/composables/useNavGroups.ts`). `AppHeader.vue:73,363` keeps using `isFuelStationCompany` from the composable; no change needed beyond the source of the flag. `company/Settings.vue:211-227`: the permission-group labels for module prefixes come from a new shared prop `modulePermissionGroups: Record<prefix, label>`, built from the manifests (`name()` + `permissionPrefixes()`). Add `permissionPrefixes()` to FuelStationModule: `['fuel', 'fuel_rate', 'fuel_product', 'fuel_sale', …]`, copied verbatim from `Settings.vue:213-226`.

- [ ] **Step 5: Gate core-page wording and inputs.** Each one follows the rule: fuel inputs are shown only when `has('fuel_station')`, and fuel wording becomes neutral.
  - `bill-payments/{Create,Edit}.vue`: the `fuel_card` option is listed only when `has('fuel_station')`. `Index.vue`'s label switch stays, because old rows must still render.
  - `vendors/Create.vue:137`: the hint is shown only for fuel. `Vendor::TYPES` is filtered in the controller that passes it: the fuel types are offered only to fuel companies, and a vendor whose saved type is a fuel type still shows its label.
  - `customers/Create.vue:115` (umrah agent hint): only when `has('umrah')`.
  - `customers/PeriodDiscount.vue:162`: the "per litre" option only when `has('fuel_station')`. Line 249 becomes "original invoices and quantities stay unchanged."
  - `credit-notes/Show.vue:550` "fuel sales:" becomes "sales:".
  - `posting-templates/{Create,Edit}.vue`: the hint becomes "For stock purchases, use inventory accounts on the bill lines; EXPENSE is only a fallback."
  - Inventory `stock/Adjustment.vue:140` becomes "Use this for corrections. Use bills to record purchases."
  - `stock/Index.vue:218`: the tank-readings button only when `has('fuel_station')`.
  - `items/Show.vue:385`: the "Fuel prices" button stays (it shows only when `priceHistory.is_fuel`).
  - `warehouses/Create.vue`: the `tank` type option only when `has('fuel_station')`.
  - `ConsolidatedInvoiceForm.vue:5`: comment only, leave it.

- [ ] **Step 6: Gate**

```bash
cd build && grep -rnE "isFuelStation|fuel|Fuel|umrah|Umrah" modules/Accounting/Resources/js | grep -vE "has\('fuel_station'\)|has\('umrah'\)|fuel_card'|// " ; npx vue-tsc --noEmit ; npm run build
```
Expected: grep shows only the `bill-payments/Index.vue` label case. `vue-tsc` and the build pass.

- [ ] **Step 7: Commit**

```bash
git add -A && git commit -m "Add module slots and move fuel/umrah UI out of core pages"
```

---

## Task 10: Independence ratchet

**Files:**
- Create: `tests/Feature/Modules/CoreIndependenceTest.php`

- [ ] **Step 1: Write the test**

```php
<?php
// tests/Feature/Modules/CoreIndependenceTest.php

arch('core modules never import industry modules')
    ->expect(['App\Modules\Accounting', 'App\Modules\Inventory', 'App\Modules\Payroll', 'App\Modules\Tax', 'App\Http', 'App\Services', 'App\Models', 'App\Actions', 'App\Support'])
    ->not->toUse(['App\Modules\FuelStation', 'App\Modules\Umrah']);

test('core PHP names no industry outside the allowlist', function () {
    // Strings that remain on purpose: RequireIndustry matches route params; RBAC/dashboard config is synced data.
    $allow = ['app/Http/Middleware/RequireIndustry.php', 'app/Constants/Permissions.php', 'app/Support/Database/SchemaAwarePresenceVerifier.php', 'modules/Accounting/Models/Vendor.php'];
    $out = shell_exec('cd '.base_path().' && grep -rlE "fuel_station|\'umrah\'|\'travel\'|amanat" modules/Accounting modules/Inventory modules/Payroll modules/Tax app --include=*.php');
    $files = array_values(array_diff(array_filter(explode("\n", (string) $out)), $allow));
    expect($files)->toBe([]);
});
```
`Vendor.php` keeps its `TYPE_FUEL_*` constants, because saved rows use them; Task 9 filters what's offered. Check `SchemaAwarePresenceVerifier` before adding it to the allowlist: if it only lists schema names (`fuel.`), keep it.

- [ ] **Step 2:** Run the grep from the second test by hand. Expected: only allowlisted files. Anything else is a missed site, so go back to the owning task.

- [ ] **Step 3: Commit**

```bash
git add -A && git commit -m "Add core-independence architecture ratchet"
```

---

## Task 11: General chart of accounts (part 2)

**Files:**
- Create: `database/migrations/2026_10_15_000001_replace_general_coa_pack.php`
- Modify: `database/seeders/IndustryCoaPackSeeder.php` (`seedOther`, the pack name on line 39)
- Modify: `config/company-industries.php` (the `other` entry's name)
- Test: `tests/Feature/Accounting/GeneralCoaPackTest.php`

The new pack. Codes match what core looks up, so no core lookup creates a stray account and none collides:

| Code | Name | type / subtype | Notes |
|---|---|---|---|
| 1000 | Bank Account | asset / bank | |
| 1050 | Cash on Hand | asset / cash | `OpeningBalanceAccounts` cash |
| 1070 | Undeposited Funds | asset / cash | |
| 1100 | Accounts Receivable | asset / accounts_receivable | system `ar_control` |
| 1150 | Employee Advances | asset / other_current_asset | payroll + opening `employee_advances` |
| 1170 | Prepaid Expenses | asset / other_current_asset | not 1160 (the provisioner's supplier-claims code) |
| 1190 | Input Tax Recoverable | asset / other_current_asset | |
| 1300 | Inventory | asset / inventory | |
| 1500 | Furniture & Equipment | asset / fixed_asset | |
| 1510 | Accumulated Depreciation – Equipment | asset / fixed_asset, contra, credit | |
| 1520 | Vehicles | asset / fixed_asset | |
| 1530 | Accumulated Depreciation – Vehicles | asset / fixed_asset, contra, credit | |
| 2100 | Accounts Payable | liability / accounts_payable | system `ap_control` |
| 2150 | Accrued Expenses | liability / other_current_liability | moved off 2200 |
| 2210 | Partner Deposits | liability / other_current_liability | opening `partner_deposits` |
| 2211 | Salary Payable | liability / other_current_liability | payroll matches by **name** first |
| 2221 | Payroll Deductions Payable | liability / other_current_liability | payroll name match |
| 2300 | Sales Tax Payable | liability / other_current_liability | |
| 2500 | Loans Payable | liability / long_term_liability (check that the subtype exists: `grep -rn "long_term_liability" modules/Accounting`; else other_current_liability) | |
| 3000 | Owner's Capital | equity / equity (check the subtype list) | |
| 3050 | Owner's Drawings | equity / equity, debit | |
| 3080 | Opening Balance Equity | equity / equity | `OpeningBalanceAccounts::EQUITY_CODE`; copy type/subtype from `ensureEquity()` |
| 3100 | Retained Earnings | equity / retained_earnings | system `retained_earnings` |
| 4000 | Sales | revenue / revenue | system `primary_revenue` |
| 4100 | Service Income | revenue / revenue | |
| 4210 | Sales Discounts | revenue / revenue, contra, debit | passes `SalesDiscountAccountResolver::valid()` |
| 4900 | Other Income | other_income / other_income | |
| 5100 | Cost of Goods Sold | cogs / cogs | system `primary_cogs` (check the identifier: `grep -rn "primary_cogs\|'cogs'" modules/Accounting/Services`) |
| 6000 | General Expenses | expense / expense | system `primary_expense` |
| 6100 | Rent | expense / expense | |
| 6110 | Utilities | expense / expense | |
| 6200 | Salaries & Wages | expense / expense | payroll name + code |
| 6210 | Payroll Taxes | expense / expense | payroll employer expense |
| 6220 | Employee Benefits | expense / expense | |
| 6300 | Office Supplies | expense / expense | |
| 6400 | Bank Charges | expense / expense | |
| 6500 | Depreciation | expense / expense | |
| 6600 | Professional Fees | expense / expense | |
| 6700 | Repairs & Maintenance | expense / expense | |
| 6800 | Transport | expense / expense | |
| 6850 | Telephone & Internet | expense / expense | |
| 6900 | Other Expenses | other_expense / other_expense | |
| 7050 | Inventory Gain | other_income / other_income | the provisioner finds it by code instead of creating "Transit Gain" |
| 8060 | Inventory Shrinkage | other_expense / other_expense | the provisioner finds it by code instead of creating "Transit Loss" |

No 2200 in this pack (Review Focus 3).

- [ ] **Step 1: Failing test**

```php
<?php
// tests/Feature/Accounting/GeneralCoaPackTest.php
// Build the company through the real onboarding path. Copy the setup from
// tests/Feature/Accounting/CompanyOnboardingCoaFailureTest.php, with industry_code 'other'.

test('a new general company gets the general chart and core lookups land on it', function () {
    // $company = …onboarded with 'other'
    $codes = \App\Modules\Accounting\Models\Account::where('company_id', $company->id)->pluck('name', 'code');
    expect($codes)->not->toHaveKey('2200')
        ->and($codes['6200'])->toBe('Salaries & Wages')
        ->and($codes['7050'])->toBe('Inventory Gain')
        ->and($codes['8060'])->toBe('Inventory Shrinkage');
    expect(app(\App\Modules\Accounting\Services\SalesDiscountAccountResolver::class)->resolve($company)->code)->toBe('4210');
    expect(app(\App\Modules\Accounting\Services\OpeningBalanceAccounts::class)->resolve($company->id, false)['equity'])->not->toBeNull();
});

test('the general pack leaves other packs and existing companies untouched', function () {
    // Snapshot acct.accounts for a pre-existing company + the fuel pack templates, run the migration's up(), assert equal.
});
```

- [ ] **Step 2: Migration.** Follow the precedent in `database/migrations/2026_08_23_000001_add_ticket_accounts_to_existing_companies.php` for style and the templates-only insert. `up()` does three things:
  1. Find the `other` pack id in `acct.industry_coa_packs` by `code = 'other'`.
  2. In one transaction, delete that pack's rows from `acct.industry_coa_templates` and insert the table above (`sort_order = index * 10`, timestamps).
  3. Update the pack's `name` to `General business`.

  It does **not** touch `acct.accounts`. `down()` restores the previous 13 rows, copied from the current `seedOther`, and the name `Other`. Print the resolved DB name at the top of `up()` with `$this->command?->info(DB::connection()->getDatabaseName())`, if that is the convention in the precedent.

- [ ] **Step 3: Seeder parity.** Replace `seedOther`'s array with the same rows, so a fresh seed matches the migration. Pack name on line 39: `General business`. In `config/company-industries.php`, set `name` to `General business` and keep the description. `CompanyIndustryOptionsTest` checks codes only; confirm that.

- [ ] **Step 4: Commit**

```bash
git add -A && git commit -m "Replace the general (other) COA pack with a full small-business chart"
```
Tell the user: existing `other` companies are unchanged. If they want an existing one brought up to date, that is a deliberate run of `RepairChartOfAccounts` on that company, which they decide on.

---

## Task 12: "Other balances" opening section (part 3)

A general business starts with balances the opening page can't enter today: fixed assets, loans, prepaid expenses, owner's capital. Add a core section, `accounts`, for any active balance-sheet account that isn't already covered by a party section.

**Files:**
- Modify: `modules/Accounting/Actions/OpeningBalance/{SaveAction,ViewAction}.php`
- Modify: `modules/Accounting/Resources/js/pages/opening-balances/Index.vue`
- Test: `tests/Feature/Accounting/OpeningOtherBalancesTest.php`

**Interfaces:**
- Payload: `accounts: [{ account_id: uuid, amount: number }]`. Rules: `account_id` exists in `acct.accounts` for the company, `type in (asset, liability, equity)`, subtype not in `(bank, cash, accounts_receivable, accounts_payable)`, and the code is not the 3080 equity; `amount > 0`.
- The posting side follows the account's `normal_balance`: debit-normal → debit the account and credit 3080; credit-normal → the reverse, the same way the existing sections balance against equity.
- View: `rows.accounts: [{account_id, code, name, amount, normal_balance}]`, and the totals add them to assets or liabilities by `normal_balance`.
- `accountOptions` prop: the allowed accounts.

- [ ] **Step 1: Failing tests.**
  1. Entering 500,000 against 1500 Furniture & Equipment and 200,000 against 2500 Loans Payable posts debit 1500 / credit 3080 and debit 3080 / credit 2500 in the opening journal.
  2. ViewAction shows both.
  3. Entering it twice (re-entry) replaces it rather than doubling it.
  4. Posting an AR or bank account in `accounts` is rejected with an error on `accounts.0.account_id`.

  Build on the existing opening-balance test fixture (`grep -rln "opening_balance.save" tests/Feature`).

- [ ] **Step 2: Implement** in SaveAction, beside the other sections, using the same `$lines` and `$pending` collection and the same re-entry path. No new tables: rows are read back from the opening journal's lines for these accounts.

- [ ] **Step 3: UI.** Add a collapsible "Other balances" card to `Index.vue`, after Partners: rows of account select plus amount, using the existing row-editor components. The label is short: "Other balances". Use no hint, or a few words at most ("Assets, loans, capital").

- [ ] **Step 4: Commit**

```bash
git add -A && git commit -m "Let opening balances take any balance-sheet account"
```

---

## Task 13: General home figures (part 3)

`company/Show` is the home page of a general company, and it ships a literal `'expenses_mtd_placeholder' => 'Not implemented yet'` (`CompanyController.php:~611`). Fill in the money figures a general owner needs.

**Files:**
- Create: `modules/Accounting/Services/GeneralHomeFigures.php`
- Modify: `app/Http/Controllers/CompanyController.php` (`renderShow` financials)
- Modify: `resources/js/pages/company/Show.vue` (the `Financials` type and the cards)
- Test: `tests/Feature/Accounting/GeneralHomeFiguresTest.php`

**Interfaces:**
- `GeneralHomeFigures::for(Company $company, Carbon $today): array{cash_and_bank: float, ap_outstanding: float, ap_overdue: float, revenue_mtd: float, expenses_mtd: float, profit_mtd: float}`. Revenue, expenses and profit come from `ProfitLossReportService::run($companyId, monthStart, today)`, the same service `PartnerProfitShareService` uses. Cash and bank is the sum of GL balances of active `bank`/`cash` accounts, using the same balance source as the balance sheet (`BalanceSheetReportService`; read it and reuse its per-account balance query). AP comes from bills, mirroring how `renderShow` computes the AR figures.

- [ ] **Step 1: Failing test.** One invoice of 10,000 sent this month, one bill of 4,000 due yesterday, one cash payment received of 3,000. Expected: `revenue_mtd = 10000`, `expenses_mtd` equals the bill's expense amount, `ap_overdue = 4000`, and `cash_and_bank = 3000`.

- [ ] **Step 2: Implement.** Merge the figures into `financials` and delete `expenses_mtd_placeholder`. In `Show.vue`, render four cards using existing card components and the design system (`docs/ledger-design-system.md`; read it first): Cash & bank, To collect (AR, existing), To pay (AP), This month (profit, with revenue and expenses beneath). Each card links to its report (balance sheet, receivables aging, payables aging, P&L).

- [ ] **Step 3: Commit**

```bash
git add -A && git commit -m "Show real money figures on the general company home"
```

---

## Task 14: End-to-end walkthrough (report, don't fix)

- [ ] In the dev app (`http://localhost:9001`), create a company with "General business" and go through one month in the browser:
  1. Onboarding
  2. Opening balances, including "Other balances"
  3. Customer and supplier
  4. Two invoices and one bill
  5. A payment received and a bill paid
  6. An expense and a manual journal
  7. Bank reconciliation
  8. P&L, balance sheet, trial balance and both agings
  9. Closing the period

  Record a GIF (`general_company_month.gif`).
- [ ] Write the findings as a list. For each: the screen, what looked wrong or fuel-flavoured, and severity. Add it to the end of this plan under "Walkthrough findings". Don't fix anything in this task; the user picks.

---

## Self-review notes

- Coverage: part 1 (independence) is Tasks 1–10, part 2 (COA) is Task 11, part 3 (general gaps) is Tasks 12–14. Every row in the coupling inventory maps to a task.
- Review Focus mapping: 1 → Task 1 test, 2 → Task 2 "exactly once" test, 3 → Tasks 3/4/5 tests, 4 → Task 2 rollback test, 5 → Task 8 diff.
- Names used across tasks: `hasModule`, `capable`, `ModuleRegistry::{register,get,all,enabledFor,label,applyIndustry}`, `ModuleManifest::{key,name,industryCodes,requires,conflicts,capabilities,labels,homeUrl,homeResponse,onboardingUrl,prepare,afterBankAccounts,sharedProps,settingsSections,ownsPermissions,permissionPrefixes,permissionCapabilities,netProfit}`, `HasHooks::{extend,flushHooks,hooks}`, the `enabledModules` prop, `registerSlot`/`slotEntries`/`registerWidget`, `useModules().has`.
