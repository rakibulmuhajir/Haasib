# Company Timezone Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans. Steps use checkbox (`- [ ]`) syntax.

**Goal:** "Today" means today in the company's own timezone everywhere the server decides a date: validation, default report dates, home figures and default document dates. Between 00:00 and 05:00 in Karachi, today is no longer yesterday.

**Background:** `config('app.timezone')` is `UTC`. Every company row already has `auth.companies.timezone` (`Asia/Karachi` for the PK companies, because onboarding sets it), but nothing reads it.

The walkthrough at 00:00–05:00 Karachi found these symptoms:
- payment forms reject the browser's date with "must be a date before or equal to today";
- the trial balance, balance sheet and aging reports default to yesterday and leave out today's entries;
- the home page's "This month" shows 0.

**Architecture:** Keep the app clock in UTC. Timestamps such as `created_at` stay UTC, and so do queues and commands. Add a company clock that gives the *date* in the company's timezone, and route every server-side "today as a date" through it. One sweep covers about 88 `now()->toDateString()`-style call sites, 13 `before_or_equal:today` rules and 5 raw SQL `current_date` uses.

Don't change the global timezone, and don't call `date_default_timezone_set` per request. Doing so would mix UTC and local values in timestamp columns.

**Paths** are relative to `build/`.

## Global Constraints

- The usual rules apply: never run `migrate:fresh`, run one test process at a time, keep `CoreIndependenceTest` green, and keep UI copy short.
- No data migration is needed for the PK companies; they already store `Asia/Karachi`. A company whose `timezone` is null or invalid falls back to `config('app.timezone')`, so it behaves as today. `scenario-mehran-fuel` has `UTC`. Set `Asia/Karachi` in `ScenarioFuelStationSeeder` if it creates the company, so the scenario matches real use.
- The browser's `localToday()` is already local, so the frontend needs no change for form defaults. Check the frontend only where it trusts a server-sent "today".

## Review Focus

1. **Midnight boundary.** At 2026-10-16 02:00 Asia/Karachi (2026-10-15 21:00 UTC), the following must all hold:
   - validation accepts a payment dated 2026-10-16;
   - the trial balance's default `as_of` is 2026-10-16;
   - the home page's "This month" covers October;
   - a `before_or_equal:today` rule rejects 2026-10-17.
2. **Fallback.** A company with a null or invalid timezone string doesn't crash. It uses the app timezone.
3. **No company context** (console commands, global pages): the clock falls back to the app timezone and doesn't throw.
4. **Raw SQL `current_date` and `now()::date`.** Pass the company date as a binding instead. The DB session runs in UTC.
5. **Month-end and daily-close logic** that computes "yesterday" or "this month". Memory note: one morning dip closes yesterday; day D is closed the next morning. It must use the company date, so the morning close lands on the right day.

## Task T1: CompanyClock

**Files:**
- Create: `app/Support/CompanyClock.php`
- Modify: `app/Providers/AppServiceProvider.php` (bind as scoped)
- Test: `tests/Feature/CompanyClockTest.php`

**Interfaces:**
```php
final class CompanyClock {
    /** The company's timezone, else app timezone (null/invalid-safe). */
    public function timezone(?Company $company = null): string;   // default: current company (CurrentCompany / CompanyContext)
    /** Now, in the company's timezone (Carbon, for date maths). */
    public function now(?Company $company = null): \Carbon\CarbonImmutable;
    /** 'Y-m-d' for today in the company's timezone. */
    public function today(?Company $company = null): string;
}
```
Also add a global helper `company_today(): string` in an existing helpers file (check `composer.json` autoload `files`; if there's none, skip the helper and use the class).

- [ ] **Tests.** Use `Carbon::setTestNow('2026-10-15 21:00:00 UTC')`:
  - a company with `Asia/Karachi` gives `today()` = `2026-10-16`;
  - a company with `UTC` gives `2026-10-15`;
  - a null timezone and `'Not/AZone'` both give `2026-10-15`;
  - no company context gives `2026-10-15`.
- [ ] Implement, then run `php artisan test tests/Feature/CompanyClockTest.php`, then commit.

## Task T2: Sweep the server-side "today"

- [ ] **Inventory.** List every site with:
  ```bash
  grep -rnE "now\(\)->(toDateString|format\('Y-m-d'\)|startOfMonth|endOfMonth|startOfDay|subDay|addDay)|today\(\)|Carbon(Immutable)?::(today|now)\(\)|before_or_equal:today|after_or_equal:today|'today'|current_date|now\(\)::date" app modules --include=*.php
  ```
  Exclude `Database/Migrations`. Classify each site:
  - **(a) Calendar date for business data.** Examples: a document date default, a validation bound, a report `as_of`/from/to default, "this month", aging buckets, daily-close business date, overdue checks. These change to `CompanyClock`.
  - **(b) Real instant.** Examples: `created_at`, `voided_at`, `posted_at`, locks, token expiry, audit timestamps, logging, rate limits. These stay as they are.

  Put the classified list in the commit message or a short comment block in the test.
- [ ] **Validation rules.** Replace `'before_or_equal:today'` with `'before_or_equal:'.app(CompanyClock::class)->today()`, and the same for `after_or_equal`. For rules defined in a static `ruleSet()`, compute the date inside the method. Make sure rules built at boot (cached) aren't frozen; they shouldn't be.
- [ ] **Raw SQL `current_date`.** Bind `?` with the company date (the aging buckets in `CompanyController::renderShow` and any others).
- [ ] **Home figures.** `GeneralHomeFigures::for(..., $today)`: callers pass `CompanyClock::now()`.
- [ ] **Fuel module.** Sweep `modules/FuelStation` and `modules/Umrah` the same way. They are in scope because the problem is the same, but they use `CompanyClock` from `app/`, which is allowed. Be careful with the daily close: read `DailyCloseService` and any "business_date defaults to today/yesterday" logic. The morning-dip rule (closing day D on the morning of D+1) must keep working, now in local time.
- [ ] **Tests.** Write a feature test pinned at `2026-10-15 21:00 UTC` for a Karachi company:
  - storing a payment dated 2026-10-16 passes validation;
  - the trial balance page's default `as_of` prop is 2026-10-16;
  - `GeneralHomeFigures` this-month covers October;
  - a payment dated 2026-10-17 is rejected.

  Then run `tests/Feature/Accounting`, `tests/Feature/FuelStation` and `tests/Feature/Modules`, one process at a time, and add `tests/Feature/Umrah` at the end. The baseline is 0 failures everywhere. Any test that pinned a date assuming UTC may need `Carbon::setTestNow`. Fix the test only when it is genuinely date-coupled, and explain each one.
- [ ] **Browser check.** The dev server runs in real time. If it's between 00:00 and 05:00 Karachi when you run, open `/walkthrough-general-co/reports/trial-balance` and check the default date is the Karachi date. Otherwise rely on the pinned tests and say so.
- [ ] Commit per area (core, fuel, umrah), with each message ending with the Co-Authored-By line.
