# Test Commands

Run everything from `D:\projects\haasib\build`.

> ## Never run `migrate:fresh`, and never trust `--env=testing`
>
> There is **no `.env.testing` file**, so `php artisan <anything> --env=testing` falls back
> to `.env`, where `DB_DATABASE=haasib` — the **dev** database.
>
> On 2026-09-20 `php artisan migrate:fresh --force --env=testing` was run in the belief it
> targeted `haasib_test`. It destroyed the dev database's data. There was no backup; it was
> rebuilt from the demo seeders, and anything a seeder cannot reproduce was lost.
>
> `migrate:fresh` is also broken here whatever the database: it drops only the `public`
> schema, leaves `auth`, `acct`, `inv`, `tax`, `pay`, `fuel`, `umrah` standing, then dies on
> `relation "item_categories" already exists`. **That error is what a half-destroyed
> database looks like — do not read it as harmless.**
>
> The suite migrates itself and `phpunit.xml` pins `DB_DATABASE=haasib_test`. Just run
> `php artisan test …`. To reset the dev database, use **Rebuilding the dev database** at the
> bottom of this file — after taking a dump.

> **Never run two test processes at once.** They share the `haasib_test` database and
> collide with `relation "…" already exists`. Finish one before starting the next.

---

## The two that matter most

```powershell
# Frontend: type errors and a real build (~5 min, no database)
npx vue-tsc --noEmit -p tsconfig.json
npm run build

# Accounting module (~90 s) — reports, statements, expenses, banking
php artisan test tests/Feature/Accounting
```

---

## By module

```powershell
php artisan test tests/Feature/Accounting      # ~90 s
php artisan test tests/Feature/FuelStation     # ~100 s
php artisan test tests/Unit                    # ~20 s
php artisan test tests/Feature/Payroll
php artisan test tests/Feature/Inventory
php artisan test tests/Feature/Security
php artisan test tests/Feature/Settings
php artisan test tests/Feature/Auth
php artisan test tests/Feature/Umrah           # slow; has hung before, see Known Issues
```

## Whole suite

```powershell
php artisan test                               # ~13 min
```

---

## A single file or a single test

Some suites define shared fixtures in one file and use them from another, so **running a
single file can fail with `Call to undefined function …Fixture()`**. When that happens,
run the whole directory instead — Pest only loads the sibling fixtures at directory scope.

```powershell
# One file
php artisan test tests/Feature/Accounting/ReceivablesAgingTest.php

# One test by name (quote it; substring match)
php artisan test tests/Feature/Accounting --filter="the worst debt is listed first"

# FuelStation fixtures live in DailyCloseCreditSalesTest.php — run the directory
php artisan test tests/Feature/FuelStation --filter="discount"
```

The opening-balance fixtures are the exception: they were moved out to
`tests/Feature/Accounting/OpeningBalanceFixtures.php`, which both files `require_once`, so
those two **do** run on their own.

---

## Bank account opening balances (2026-09-21)

The bank account form used to write `acct.company_bank_accounts.opening_balance` and post
nothing to the ledger. It now dispatches `opening_balance.set_account`, which merges one
account into the current opening set and delegates to `SaveAction` — the single write path.

Run both files: the second one's fixtures were moved, so it is part of the change.

```powershell
php artisan test tests/Feature/Accounting/BankAccountOpeningBalanceTest.php tests/Feature/Accounting/OpeningBalancesTest.php
```

Or the whole module, which also covers banking and reconciliation:

```powershell
php artisan test tests/Feature/Accounting        # ~90 s
```

Route verbs for the inline editor (`useInlineEdit` PATCHes; customers and vendors were
registered `PUT` only, so every inline field returned 405):

```powershell
php artisan test tests/Feature/Routing/InlineEditRouteVerbsTest.php
```

Frontend, for the two bank account pages:

```powershell
npx vue-tsc --noEmit -p tsconfig.json
```

> Pre-existing `vue-tsc` errors in `partners/*`, `company/Show.vue` and
> `onboarding/BankAccounts.vue` are not from this change. The `bank-accounts` pages are clean.

---

## Other checks

```powershell
# Design-system ratchet: hardcoded colours, hand-rolled tables, money as text
node scripts/lint-palette.mjs
node scripts/lint-palette.mjs --report     # shows which files

# PHP syntax only, instant
php -l path\to\File.php

# Code style
npm run lint
npm run format:check
```

---

## Known issues

- **`tests/Feature/Umrah` has hung**, running well past its own timeout and producing no
  output. It has also completed normally in ~9 min. If it hangs, kill it — it is unrelated
  to recent work.
- **Pre-existing `vue-tsc` errors: 9, in 5 files** — `pages/company/Show.vue`,
  `pages/onboarding/BankAccounts.vue`, `pages/partners/{Create,Edit,Show}.vue`. These are
  not from recent work. A clean run means *no new files* in that list.
- **Pre-existing palette ratchet failure: 7 hardcoded utilities**, all in Umrah pages.
  It fails on a clean checkout too. A clean run means the count has not gone **up**.
- **`php artisan migrate:fresh` is broken** — it drops only the `public` schema and then
  fails on `relation "item_categories" already exists`. Tests migrate themselves; don't
  reach for `migrate:fresh`.
- **Baseline whole-suite result: 8 failed, 929 passed.** Some failures live in modules
  untouched by recent work (Umrah, Payroll).

---

## Fuel station scenario

A controlled two weeks at a fixed station, every figure decided in advance so each day's expected
result is known before the software is asked.

```powershell
# Build the station (idempotent — purges and rebuilds its own company)
php artisan db:seed --force --class='Database\Seeders\ScenarioFuelStationSeeder'

# The same week through the service layer — 10 assertions, all passing
php artisan test tests/Feature/FuelStation/SevenDayOperationsTest.php
```

Login `scenario@haasib.test` / `scenario-password`, company `scenario-mehran-fuel`.

Step-by-step instructions for driving it through the UI, with the expected outcome after
each day, are in **`docs/fuel-station-e2e.md`** — two weeks, 1–14 March 2026. Week 1 covers
trading, the rate change and a planted cash shortage; week 2 adds payments received, paying
the supplier, and payroll.

---

## Rebuilding the dev database

Destroys all dev data. **Take a dump first**: `pg_dump -U postgres haasib > haasib.sql`

`migrate:fresh` cannot do this correctly (it leaves every non-public schema behind), so drop
the schemas explicitly:

```powershell
php artisan tinker --execute="
  \$db = DB::connection()->getDatabaseName();
  if (\$db !== 'haasib') { echo 'ABORT: '.\$db; exit(1); }
  foreach (['auth','acct','inv','tax','pay','fuel','hsp','crm','audit','umrah'] as \$s) {
    DB::statement('DROP SCHEMA IF EXISTS '.\$s.' CASCADE');
  }
  DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
  DB::statement('CREATE SCHEMA public');
"
php artisan migrate --force
php artisan db:seed --force
php artisan db:seed --force --class='Database\Seeders\DemoDataSeeder'
```

That leaves three companies under one login — **demo@haasib.app / demo-password**:

| Company | Industry | What it carries |
|---|---|---|
| Meridian Trading Co. | retail | GL, AR/AP, 5 customers, 4 vendors, overheads |
| Crescent Fuel Station | energy | 3 tanks, 4 pumps, 7 nozzles, 30 posted daily closes, settlements |
| Bab-al-Salam Travel | services | 3 agents, 3 groups, approved vouchers, agent advances |

`php artisan db:seed` on its own adds `test@example.com`, which belongs to **no** company —
logging in as that user shows an empty company switcher and an empty menu, because every
module's `nav.ts` begins `if (!slug) return []`.
