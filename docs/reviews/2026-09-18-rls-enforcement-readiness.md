# Row level security: enforcement readiness

**Date:** 18 September 2026, updated 19 September 2026
**Status:** prepared, **not switched on**, and gated behind `RLS_ENFORCEMENT=on`
so it cannot ride along with an ordinary deploy.

## What is true right now

**Production and development differ, and conflating them caused a near-miss.**

*Production* connects as `haasib_app` — not a superuser, no `BYPASSRLS` — and
that role **owns all 90 tables**. 84 tables have RLS enabled but only 17 are
`FORCE`d, and all 17 are Umrah's. A table's owner bypasses its own policies
unless the table is FORCEd, so the other ~67 are inert: the policies exist and
never run. Isolation there rests on application-level `company_id` scoping.

*Development* connects as the `postgres` superuser, which bypasses RLS
unconditionally — `FORCE` included. So dev cannot reproduce production's
behaviour at all, in either direction.

This matters because the two environments fail differently. Verifying on dev
tells you nothing about what enforcement will do in production.

## Why the failures are dangerous rather than merely noisy

A read without company context returns **nothing**, and reports success. It does
not error. So a missing `set_config` surfaces as an empty screen or an empty
report, not as a stack trace — the same way a genuinely empty table would. A
write without context does error, which is the louder and safer half.

This asymmetry is why the switch should not be made hopefully. An incomplete
rollout produces screens that look fine and are silently blank for some tenants.

## The tenant context guard (development and test only)

`app/Support/Database/TenantContextGuard.php` closes the silent half. It watches
the query stream and raises when a query touches a company-scoped table while
the PostgreSQL session carries no `app.current_company_id` and is not in the
policies' super-admin escape hatch.

**Company-scoped** means: the table has a `company_id` column, has row level
security enabled, and has at least one policy whose expression reads
`app.current_company_id`. That is computed from `pg_policy` at runtime, so it
tracks the schema rather than a hand-maintained list, and it deliberately
excludes `auth.companies` and `auth.company_user` — the two tables you read to find out
*which* company you are in, on every request, before any context exists.
`auth.companies` falls out for having no `company_id` of its own;
`auth.company_user` is named explicitly in `TenantContextGuard::BOOTSTRAP_TABLES`,
because the enforce migration rescopes its policy to read the GUC while also
admitting the caller's own membership rows by `app.current_user_id`.

It keeps a cheap in-process mirror of the session settings so the hot path costs
no round trip, and when the mirror suspects a violation it asks the session
itself before raising. Context set by a path the guard never saw is therefore not
mistaken for no context at all.

**Switching it on and off**

```bash
# off (the default everywhere, and forced off in production)
DB_TENANT_CONTEXT_GUARD=off

# complain in the log and carry on
DB_TENANT_CONTEXT_GUARD=log

# raise, so a violation is a test failure or a stack trace at a desk
DB_TENANT_CONTEXT_GUARD=throw
```

**The test suite runs with it in `throw` mode**, set in `phpunit.xml`, so a
missing context is a test failure. Turn it down for one run, or up for a dev
server, inline:

```bash
DB_TENANT_CONTEXT_GUARD=log php artisan test
DB_TENANT_CONTEXT_GUARD=off php artisan test
DB_TENANT_CONTEXT_GUARD=log php artisan octane:start --server=frankenphp --port=9001
```

A test that reads with **no** context deliberately — to prove the read comes
back empty rather than returning everything — stands the guard down for exactly
that block:

```php
app(TenantContextGuard::class)->ignoring(fn () => /* … */);
```

That is not an escape hatch for application code. Code that legitimately spans
companies uses `CompanyContext::crossCompany()`, which the guard already honours.

`config/database.php` holds `tenant_context_guard.mode` and
`tenant_context_guard.connections` (default: the app's default connection; the
schema-named connections and `pgsql_migrator` are separate sessions and are not
guarded). `AppServiceProvider` forces the mode to `off` when
`app()->environment('production')`, so the guard cannot be switched on there by
an environment variable alone.

**What it catches.** Any read or write reaching a company-scoped table through
Laravel's connection with no company context — including the reads that would
otherwise return an empty result and report success. It found
`DemoSupport::asCompany` clearing the context instead of restoring it, a fault
whose only other symptom was a seeded demo company with no accounting periods.
Switched on over the whole suite it flagged nine more reads, every one of them a
test asserting against a company's rows from outside that company: assertions
that would have passed for the wrong reason, or failed with no explanation.

**What it cannot see.**

- Whether the context that *is* set is the **right** company. It answers "is
  there a tenant context", not "is it the correct one". Cross-company leakage
  with a context set is invisible to it; only the policies stop that.
- Raw PDO used outside Laravel's connection, which emits no `QueryExecuted`.
- Queries on connections other than the guarded ones.
- Anything that never reaches the database — a cached response, a query
  short-circuited in PHP.
- The two bootstrap tables above. A genuine leak through `auth.company_user`
  would not be flagged.
- Whether the context that is set belongs to the *service* as well as the
  session. `CompanyContext` keeps its own in-memory company alongside the GUC,
  and code that reads the service (`VisaVendorParty`, for one) can disagree
  with the session the guard inspects.
- Production. By design.

## Running the suite as the application role

The suite normally runs as `postgres`, which proves nothing about enforcement.
To run it as the least-privilege role production connects as, with enforcement
actually applied:

```bash
cd build
DB_USERNAME=haasib_app DB_MIGRATOR_USERNAME=postgres RLS_ENFORCEMENT=on php artisan test
```

- `DB_USERNAME=haasib_app` — the application connection becomes the restricted
  role. It is not a superuser and has no `BYPASSRLS`.
- `DB_MIGRATOR_USERNAME=postgres` — DDL and role creation run on the
  `pgsql_migrator` connection, as they do in an environment where the
  application role does not own the schema. `RefreshApplicationDatabase`
  already migrates on that connection.
- `RLS_ENFORCEMENT=on` — runs the two gated migrations: the one that provisions
  `haasib_app` and its grants, and the one that FORCEs every RLS-enabled table
  and guards the policy expressions.

Both roles must exist locally and be able to log in. Only `haasib` and
`haasib_test` are ever touched.

Two tests skip under the application role — one creates a throwaway database
role, one runs a migration's `up()` in place. Both need privileges production
would never grant the application connection; `requiresPrivilegedDatabaseRole()`
in `tests/Pest.php` is how they say so.

## What the run found

The first run as `haasib_app` with enforcement applied:

```
Tests:    352 failed, 546 passed (3014 assertions)
```

Almost all of them `SQLSTATE[42501] ... new row violates row-level security
policy`. Each one was examined rather than assumed. The split:

| | count | |
|---|---|---|
| **(a) fixture never set company context** | 30 test files | A test that creates a company has to enter it before writing that company's rows, exactly as the application does. `enterCompany()` and `addCompanyMemberRow()` in `tests/Pest.php` give fixtures one way to say that. |
| **(b) application code writing or reading without context** | **5** | These would have broken production. |
| **(c) policy wrong** | 0 | The unguarded `::uuid` casts (`pay.salary_advances`, `pay.salary_advance_recoveries`, `fuel.dip_sticks`, `umrah.operation_views`) are already rewritten by the enforce migration's `guardPolicyExpressions()`, and `RowLevelSecurityTest` asserts no policy is left casting the GUC without a null guard. Nothing further was needed, and no policy was loosened. |

### The five (b) faults — what would have broken production

1. **`app/Http/Controllers/CompanyController.php:118`** — `store()` wrote the
   owner's `auth.company_user` row, the company's RBAC roles and its secondary
   currency while the session was still in whichever company the creator came
   from, or in none at all. **Company creation was the first thing enforcement
   would have broken.** Now wrapped in `CompanyContext::withContext($company, …)`.

2. **`app/Services/CompanyBootstrapService.php:30`** — built a company's chart of
   accounts, default bank and cash accounts, posting templates and first fiscal
   year with no company context at all. Every one of those writes would have been
   refused, leaving a company that exists and cannot be used.

3. **`modules/Umrah/Services/VisaVendorParty.php:51`** — created the supplier
   inside `withContext` and then read it back **outside** it, so `findOrFail`
   reported a row it had just created as missing.

4. **`database/seeders/Demo/DemoSupport.php:393`** — `asCompany()` called
   `clearContext()` on the way out instead of restoring the context the caller
   had. Everything after the first product-setup call ran with no company
   context: reads returned nothing, writes were refused. This is the fault the
   tenant context guard found.

5. **Data migrations, all of them** — a migration that enumerates
   `auth.companies` and backfills their rows reads **zero rows** under
   enforcement and reports a clean run having touched nothing. Nothing in a
   deploy log would show it.
   `AppServiceProvider::runMigrationsAcrossEveryCompany()` now puts the
   migration session into the policies' own super-admin escape hatch for the
   duration of `MigrationsStarted` … `MigrationsEnded`. A migration is
   cross-company work by definition; this is the same hatch console commands and
   seeders already use, not a bypass.

### Two deliberate behaviour changes

- **A company a user does not belong to now answers 404, not 403.** Under the
  rescoped `companies_select_policy` the company is not visible to a non-member,
  so `IdentifyCompany` cannot find it and aborts before RBAC is consulted. This
  is stricter — it stops leaking the existence of a company — and the affected
  tests assert "refused" rather than a specific code, because which one you get
  depends on whether enforcement is applied to the database under test.
- **`RowLevelSecurityTest` skips unless enforcement is applied.** Without the
  gated migrations `haasib_app` holds no grants at all, so those tests would be
  measuring the absence of a `GRANT` rather than the presence of isolation.

## Proof of isolation

`tests/Feature/Security/RowLevelSecurityTest.php` connects as `haasib_app` (via
`SET LOCAL ROLE`, inside the test's own transaction) and asserts, against real
rows:

- with context set to company A, `acct.customers` returns A's rows and not B's,
  and the same query with context set to B returns B's and not A's;
- with **no** context, the same table returns **zero rows and no error** — the
  GUC reads as the empty string, which is the case that used to raise
  `invalid input syntax for type uuid`;
- a write naming another company's `company_id` is refused;
- a company is visible to its own members and to nobody else;
- every RLS-enabled table also FORCEs it, and no policy casts the tenant GUC
  without a null guard.

## How to perform the switch

Production is already connected as `haasib_app`, so **no `.env` role change is
needed** — the switch is the `FORCE` itself.

1. Confirm the suite passes as `haasib_app` on the test database, with the exact
   command above.
2. On a staging copy of production data, run the migration with
   `RLS_ENFORCEMENT=on` and exercise the app: **create a company**, post a close,
   take a payment, record a bill, run a report that should have rows. Writes fail
   loudly; the reads are what need eyes on them.
3. Then production, in a maintenance window, with the rollback below to hand.

`RLS_ENFORCEMENT` stays unset everywhere by default. Setting it is the
deliberate act.

The two opt-in migrations use Laravel's `shouldRun()` hook, so an ordinary
deployment with enforcement off leaves them **pending**, rather than recording
a successful no-op. After staging verification, clear cached configuration and
run `RLS_ENFORCEMENT=on php artisan migrate --force` with the intended migration
credentials. Clearing configuration matters because the gate reads the environment;
Laravel does not load `.env` when configuration is cached. Rebuild production
caches afterwards. The normal deploy script already clears caches before migrating.

**If an earlier deployment already ran the old gates:** inspect `migrate:status`
and the actual role grants, policies and FORCE flags first. Changing the flag
does not rerun a migration already recorded as applied. If a migration is
confirmed to have been recorded without applying its changes, repair only that
specific migration-history entry under a controlled maintenance procedure before
activation. Do not run a blanket rollback or delete migration history blindly:
the enforcement migration's `down()` removes FORCE from all forced tables,
including pre-existing Umrah enforcement. This code change does not automatically
modify existing migration history.

## Where it stands

```
# php artisan test                                  (as postgres, guard on)
Tests:    7 skipped, 901 passed (5494 assertions)

# DB_USERNAME=haasib_app DB_MIGRATOR_USERNAME=postgres RLS_ENFORCEMENT=on php artisan test
Tests:    2 skipped, 906 passed (5510 assertions)

# npm run test:js
Test Files: 3 passed
Tests:      21 passed
```

The seven skips are the five `RowLevelSecurityTest` cases, which need enforcement
applied, and the two that need a privileged database role.

The PHP results above are from the saved verification logs (`v_super.txt` and
`w_app.txt`, 19 September); JavaScript was rerun successfully on 19 September.
After the deployment fixes, focused migration-gate and deployment rollback tests
also cover opting in after a disabled run and failures after the asset swap.

## Rollback

`ALTER TABLE <each> NO FORCE ROW LEVEL SECURITY` returns every table to
owner-bypass, which is exactly today's behaviour. The enforce migration's
`down()` does this for every forced table. No data changes, and the policies
themselves can stay in place.

## What has been done

| | |
|---|---|
| `34d9173a` | Provisions `haasib_app` where it does not exist — not a superuser, no `BYPASSRLS`. |
| `b914d0d0` | Carries company context into console commands, seeders and queued jobs. |
| `06b8e6d4` | Converted ~112 `exists:` validation rules off the schema-named connections. |
| `c250a70d` | The same for 16 `unique:` rules. |
| *(this branch)* | The tenant context guard; the five (b) fixes; the fixture work; the running instructions above. |
