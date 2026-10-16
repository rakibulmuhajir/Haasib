# Palette Command Catalog Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Every CommandBus action a user may run becomes a palette command, for core first and then for the industry modules, without hand-writing a schema per command.

**Why:** The palette's backend can already execute any of the 118 CommandBus actions (`POST /api/palette/execute` → `CommandController`, keyed `entity.verb`). The frontend only *knows* 26 commands across 7 entities, because `resources/js/palette/schemas.ts` hand-writes each schema. Parsing, autocomplete and help all read that static list, so the other 92 actions can't be typed.

**Architecture:**
- **Server.** The server publishes a **catalog**, built from what each action already declares:
  - the CommandBus key gives the entity and the verb;
  - `rules()` gives the fields, their types and which are required;
  - `permission()` decides who sees the command.
  - Modules own their key prefixes, so a general company never sees fuel commands. An optional `DescribesPalette` interface lets an action improve its description and field hints.
- **Frontend.** The frontend merges the catalog into the grammar at runtime. Hand-written schemas win, because they carry better hints.
- **Search fields.** These resolve through a keyed `PaletteSearchSources` registry. Core registers customers, vendors, invoices, bills, accounts and items; modules register their own.

**Paths** are relative to `build/`.

## Global Constraints

- Same as `2026-10-15-core-independence-general-accounting.md`. In short:
  - no `migrate:fresh`;
  - one test process at a time;
  - Vue is `<script setup lang="ts">`;
  - short copy;
  - core never imports FuelStation or Umrah (the ratchet test `tests/Feature/Modules/CoreIndependenceTest.php` must stay green).
- Don't change how `/api/palette/execute` or `CommandController` authorise or execute. The catalog is discovery only. Execution keeps its own checks.
- The existing 26 hand-written schemas keep working exactly as now.

## Review Focus

1. A general company's catalog contains no `fuel.*` or `calculator.*` command. A fuel company's does.
2. A user without an action's permission doesn't see it in the catalog, and execute still refuses it (403).
3. Actions whose required input is an array (line items, allocations) are listed as `form` commands: they open the page that has the form instead of trying to collect it inline. Nothing tries to type a nested array.
4. The catalog is cached per user and company for the request lifetime only. A permission or module change shows on the next palette open; there is no stale cross-company cache.
5. An action whose `rules()` throws, or needs a company context it doesn't have, is skipped and logged. One bad action never breaks the catalog.

---

## Task 15.1: Rule introspection and catalog (server)

**Files:**
- Create: `app/Support/Palette/RuleIntrospector.php`
- Create: `app/Support/Palette/PaletteCatalog.php`
- Create: `app/Contracts/DescribesPalette.php`
- Create: `app/Http/Controllers/Api/PaletteCatalogController.php`
- Modify: `routes/api.php` (`GET /api/palette/catalog`, middleware `['web','auth','identify.company','throttle:60,1']`, the same as flag-values)
- Modify: `app/Support/Modules/ModuleManifest.php`: add `commandPrefixes(): array` (default `[]`). `FuelStationModule` returns `['fuel.', 'calculator.']`. Check `config/command-bus.php` and the fuel provider's merged keys for any other fuel-owned prefixes. Check Umrah's command keys the same way (`grep -n "umrah" config/command-bus.php`).
- Test: `tests/Feature/Palette/PaletteCatalogTest.php`, `tests/Unit/Palette/RuleIntrospectorTest.php`

**Interfaces:**
```php
interface DescribesPalette {
    /** @return array{description?: string, hidden?: bool, form_url?: string, fields?: array<string, array{hint?: string, inputType?: string, searchEntity?: string}>} */
    public function palette(): array;
}

final class RuleIntrospector {
    /** @return array{fields: list<array{name: string, required: bool, inputType: string, enum?: list<string>, searchEntity?: string, hint?: string}>, complex: bool} */
    public function describe(array $rules): array;
}

final class PaletteCatalog {
    /** @return list<array{key: string, entity: string, verb: string, description: string, query: bool, form: bool, form_url: ?string, args: list<array>, flags: list<array>}> */
    public function for(\App\Models\User $user, ?\App\Models\Company $company): array;
}
```

**Rule mapping.** Rules come as pipe strings or arrays of strings and Rule objects.
- **Required.** `required` (or `required_with*`) sets `required: true`; otherwise false.
- **inputType:**
  - `in:a,b`, or an `Illuminate\Validation\Rules\In` read through `(string) $rule` → `select` with `enum`
  - `boolean` → `select` with `enum: ['true','false']`
  - `numeric` or `integer` → `number`
  - `date` or `date_format` → `date`
  - `email` → `email`
  - `uuid`, or an `Exists` rule, on a field named `{x}_id` → `search` with `searchEntity` = `{x}`
    - Strip known suffix words (`deposit_account` → `account`, `ar_account` → `account`, `payment_account` → `account`).
    - Keep `customer`, `vendor`, `invoice`, `bill`, `item`, `employee`, `warehouse`, `account` and `user`.
    - Anything else falls back to `text`.
  - Field named `currency` or `*_currency` → `lookup` with `searchEntity: 'currency'`
  - otherwise → `text`
- **Nested rules.** Keys containing `.` (for example `address.city`) are skipped.
- **Arrays.** A top-level `array` field that is required makes the command `complex: true`. If it is optional, it is skipped.
- **Args and flags.** The first required non-array field becomes the single positional `arg`, matching the hand-written schemas. Every other field becomes a flag.
- **Entity and verb.** `entity` is the key minus its last segment and `verb` is the last segment. For example, `fuel.daily_close.save` gives entity `fuel.daily_close` and verb `save`.
- **query.** True when the verb is `list` or `view`, or the key starts with `report.` (the same rule as `CommandController::isQueryAction`).
- **form.** The same as `complex`. `form_url` comes from `DescribesPalette`, else null.

**Catalog filtering.** For each key in `app(CommandBus::class)->registered()`:
1. Skip it if a module owns its prefix (`commandPrefixes`) and that module isn't `isEnabledFor($company)`.
2. Instantiate the handler through the container. Skip it if `permission()` is non-null and `! $user->hasCompanyPermission(...)`. Mirror `CommandController::shouldSkipPermissionCheck` for the global commands: read the `GLOBAL_COMMANDS` and `COMPANY_TARGET_COMMANDS` constants (make them public if needed; don't duplicate them).
3. Skip it if `DescribesPalette::palette()['hidden']` is set.
4. Wrap each action in `try`/`catch (\Throwable)`. On failure, `Log::warning('palette_catalog_skip', [...])` and continue.

**Description.** Use `DescribesPalette` when present. Otherwise use `Str::headline($verb).' '.Str::headline(last segment of entity)`, for example "Create Vendor".

- [ ] **Step 1: Write the unit tests for `RuleIntrospector`.** Cover the cases below.
  - Vendor create's real `rules()`: `name` required text arg, `vendor_type` select with all `Vendor::TYPES` keys, `payment_terms` number, `address.*` skipped, `email` email.
  - `AmanatMovementAction::ruleSet()`: `customer_id` search/customer required, `kind` select deposit/withdraw, `business_date` date, `amount` number.
  - A required `line_items|array` → `complex: true`.
- [ ] **Step 2: Write the feature tests for the catalog endpoint.**
  - A general company owner's catalog has `vendor.create`, `invoice.create` and `payment.create`, and no key starting `fuel.` or `calculator.`.
  - A fuel company owner's has `fuel.amanat.movement`.
  - A user with a role lacking `vendor.create`'s permission doesn't see it.
  - Each entry has the documented keys.
  - Use the company/user/role fixture style of `tests/Feature/Modules/PaymentReceiptModesTest.php`.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** `php artisan test tests/Unit/Palette tests/Feature/Palette tests/Feature/Modules`.
- [ ] **Step 5: Commit.**

## Task 15.2: Search sources (server)

**Files:**
- Create: `app/Support/Palette/PaletteSearchSources.php`, a keyed static registry. `register(string $entity, callable $search)`, where `$search = fn (string $companyId, string $query): array<int, array{value: string, label: string, meta?: string, icon?: string}>`.
- Modify: `app/Http/Controllers/Api/PaletteFlagController.php::searchEntity`. After its existing `match` (keep `customer` and `invoice` as they are), fall back to `PaletteSearchSources`.
- Modify: `app/Providers/AppServiceProvider.php`, which registers the core sources. A small `app/Support/Palette/CoreSearchSources.php` holds the closures:
  - `vendor` (name / vendor_number)
  - `bill` (bill_number)
  - `account` (code / name, active only)
  - `item` (sku / name)
  - `employee` (first / last name, employee_number)
  - `warehouse` (code / name)
  - `user` (already handled globally; leave it)

  Mirror `searchCustomers()` for shape, limit 10 and ilike.
- Modify: `FuelStationServiceProvider`. Register `pump` (fuel pumps) and `tank` (warehouses with `warehouse_type = 'tank'`) if any fuel action uses `pump_id` or `tank_id`; check with `grep -rn "pump_id\|tank_id" modules/FuelStation/Actions`.
- Test: `tests/Feature/Palette/PaletteSearchSourcesTest.php`. Vendor search finds by name within the company only (a second company's vendor doesn't leak). An unknown entity returns `[]`.

- [ ] Steps: test, implement, run `php artisan test tests/Feature/Palette`, commit.

## Task 15.3: Frontend merges the catalog

**Files:**
- Modify: `resources/js/palette/schemas.ts`
  - Export `registerCatalogSchemas(entries: CatalogEntry[]): void`. It adds every entry whose `entity.verb` isn't already hand-written, converting to `CommandSchema` (`args`, `flags`, and the `FieldDef`s map 1:1).
  - Keep a module-level `catalogMeta: Record<string, {description: string; query: boolean; form: boolean; form_url: string | null}>`.
- Modify: `resources/js/palette/grammar.ts`. `GRAMMAR` and `COMMAND_DESCRIPTIONS` stay exported `const` objects, but `rebuildGrammar()` mutates them in place: clear the keys, refill from `buildGrammar()` and `buildCommandDescriptions()`. That way every module that imported them sees the new entries. Call it at the end of `registerCatalogSchemas`. Descriptions for catalog entries come from `catalogMeta`.
- Modify: `resources/js/composables/palette/useCommandPalette.ts`.
  - On first open, and whenever the current company slug changes, fetch `/api/palette/catalog` with the same fetch and headers the file already uses for `/api/palette/flag-values`, then call `registerCatalogSchemas`.
  - Failure is silent; the 26 hand-written commands still work.
- Modify: `resources/js/components/palette/CommandPalette.vue`. A command whose meta has `form: true` doesn't execute inline. On submit it navigates with `router.visit(form_url)` when present; otherwise it prints one short line, "Open the page to fill this in."
- Entity shortcuts: none are added automatically. Dotted entities (`fuel.daily_close`) are typed as-is, and autocomplete lists them.
- Test: `resources/js/palette/__tests__/catalog.test.ts` (vitest; check the existing vitest config's include pattern and put it where it'll run):
  - `registerCatalogSchemas` adds a new entity to `GRAMMAR`;
  - it doesn't overwrite a hand-written `customer.create`;
  - `getSchema('vendor','create')` returns the catalog schema.

- [ ] Steps: test, implement, `npx vitest run`, `npx vue-tsc --noEmit -p tsconfig.json`, commit.

## Task 15.4: Describe the important commands

Add `DescribesPalette` to the actions people will actually type. For each, give a one-line description, field hints a few words long, and `form_url` for the complex ones.
- **Core:** `invoice.create` and `bill.create` (complex: form_url `/{company}/invoices/create` and `/{company}/bills/create`; the slug comes from `CompanyContext`), `vendor.*`, `bill.*`, `expense.*`, `journal.*`, `account.*`, `credit_note.*`, `bill_payment.*`, `fiscal_year.*`, `accounting_period.*`, `partner.*`.
- **Then FuelStation:** `fuel.amanat.movement`, `fuel.direct_sale`, `fuel.daily_close.save` (form_url to the daily close page), `fuel.daily_close.expense`, `fuel.settings.update` (hidden: it's a settings blob), `calculator.*`.

Test: extend `PaletteCatalogTest` to cover these. `invoice.create` is `form: true` with a form_url ending `/invoices/create`, and `fuel.settings.update` is absent.

- [ ] Steps: implement, run `php artisan test tests/Feature/Palette tests/Feature/Modules`, then a browser check. Open the palette on a fuel company, type `vendor create Acme`, and see the vendor created. Type `fuel.amanat` and see the movement command offered. Commit.
