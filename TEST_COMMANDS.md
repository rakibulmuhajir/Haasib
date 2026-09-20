# Test Commands

Run everything from `D:\projects\haasib\build`.

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
