# RLS bypass by the application's database role

Dated 16 September 2026. No application code changed by this note (review finding #5 from the Daily Close locking/correction review).

## Finding

The application connects to Postgres as the `postgres` superuser (see `config/database.php` / the `DB_USERNAME` used in this environment). PostgreSQL superusers bypass row-level security unconditionally, regardless of `ENABLE ROW LEVEL SECURITY` or `FORCE ROW LEVEL SECURITY` on a table. This means:

- `fuel.daily_close_activity` (`modules/FuelStation/Database/Migrations/2026_09_15_220000_audit_post_close_activity.php`)
- `fuel.daily_close_drafts` (`modules/FuelStation/Database/Migrations/2026_09_15_200000_daily_close_drafts.php`)
- `fuel.daily_close_reading_corrections` (new table, `modules/FuelStation/Database/Migrations/2026_09_16_*_daily_close_reading_corrections.php`)

all carry a `USING (company_id = current_setting('app.current_company_id', true)::uuid OR current_setting('app.is_super_admin', true) = 'true')` policy with `FORCE ROW LEVEL SECURITY` set, but every one of those policies is currently inert: the connecting role never has RLS evaluated against it in the first place. Tenant isolation on these tables is, today, enforced entirely by application code (`CurrentCompany`, `identify.company` middleware, and `where('company_id', ...)` scoping in the models/services), not by the database.

This is not a defect introduced by this branch — it is the existing posture of the whole schema (see `production-rls-not-enforced` in project memory: 67 tables already have policies that never run). It is called out here because this branch adds three more tables with the same pattern, and because Task A's advisory-lock design and Task B's correction trail both assume company-scoped writes are trustworthy audit evidence; that assumption currently rests on application code, not the database.

## What changes if the app role is made non-superuser

If/when the app is switched to a dedicated non-superuser role (a deployment decision, not made on this branch):

1. `FORCE ROW LEVEL SECURITY` starts actually applying. Every INSERT into `fuel.daily_close_activity`, `fuel.daily_close_drafts`, `fuel.daily_close_reading_corrections`, and every other RLS-protected table, will require `app.current_company_id` to already be set to the row's own `company_id` (or `app.is_super_admin` to be `'true'`) in the current session/transaction, or the write is silently excluded by the `USING` policy (for INSERT this manifests as a policy violation error, since there is no separate `WITH CHECK` — Postgres uses `USING` for both when none is given).

2. The mechanism that sets `app.current_company_id` today is `SELECT set_config('app.current_company_id', ?, false)`, called from (`grep -rn current_company_id app modules`):
   - `app/Services/CompanyContextService.php` — the primary place this is set for a normal authenticated HTTP request, driven by the `identify.company` middleware resolving `CurrentCompany`.
   - Individual controllers that set it directly as a fallback/override: `modules/Accounting/Http/Controllers/PostingTemplateController.php`, `modules/FuelStation/Http/Controllers/DailyCloseController.php`, `modules/FuelStation/Http/Controllers/FuelStationOnboardingController.php`, `modules/Payroll/Http/Controllers/{EmployeeController,PayrollDashboardController,PayslipController,SalaryReportController,SalaryAdvanceController}.php`.
   - `modules/FuelStation/Services/DailyCloseService.php` and `modules/Payroll/Services/PayrollPostingService.php` also set it directly around specific operations.
   - `modules/Umrah/Http/Requests/StoreTicketBookingRequest.php`.
   - Every test fixture in this branch (and most existing feature tests) sets it manually via `DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id])` before acting, because Artisan/test runs are not routed through the HTTP middleware.

3. Paths that do **not** go through the HTTP middleware stack — and therefore do not get `app.current_company_id` set today unless they set it themselves — are the ones that would start failing writes to these audited tables the moment the app role loses superuser: **artisan console commands**, **queue jobs/listeners**, and **database seeders**, none of which currently set this config value as a matter of framework wiring (only the handful of places above set it explicitly, and only when they specifically need it). Anything that inserts/updates a row on an RLS-protected table from one of those contexts — for example a scheduled job that posts a correction, reverses a transaction, or seeds fixture data outside a request — must explicitly call `set_config('app.current_company_id', ...)` (or run `current_setting('app.is_super_admin', true) = 'true'`) before doing so, or the write will be rejected once RLS is actually enforced.

## Recommendation

This is a deployment/infrastructure decision for the user, not a code change on this branch:

- Before removing superuser from the app's Postgres role, audit every console command, job, and seeder that writes to an RLS-protected table and make sure it sets `app.current_company_id` (or the `is_super_admin` escape hatch) itself, the same way `CompanyContextService` does for HTTP requests.
- Until then, `FORCE ROW LEVEL SECURITY` on the tables listed above (and the other 67 already in that state) is documentation of intent, not an enforced control.


## Follow-up: audit behavior under enforced RLS

The Daily Close source trigger now scopes its audit work to the source company,
using invoker privileges and restoring the caller's company GUC before source-row
RLS is evaluated. A nonempty mismatched context is rejected. Regression tests
create a NOSUPERUSER/NOBYPASSRLS role and prove missing-context audit capture on an
unguarded source, audit read isolation, cross-company rejection, context restoration,
and source RLS remaining enforced. No SECURITY DEFINER or privilege bypass was added.

Deployment still needs a restricted application role to enforce all existing RLS
policies. This change does not alter production credentials or roles.
