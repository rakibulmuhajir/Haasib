# Road to a million

What stands between Haasib as it is now and Haasib as something you would hand to a paying
customer without flinching. Written 21 September 2026, after a session spent inside the
production box rather than guessing from the repo; updated 24 September, as live station data
started going in.

Everything below was checked on the live server. Where something is an assumption rather
than a reading, it says so.

---

## The three that matter

### 1. Production has no automated backups

**Status: unresolved. Fix this before anything else on this page.** One manual off-box copy
exists: `haasib-pre-eak-delete-20260923-062844.dump`, taken 23 September before a company was
removed and stored on the development PC. Nothing produces another.

Every database backup on the production server is a manual dump someone took before a risky
change. The filenames say it plainly:

```
/home/ubuntu/haasib-backups/pre-rls-cc993f65.dump        19 Sep
/home/ubuntu/haasib-backups/pre-umrah-20260914.dump      14 Sep
/home/ubuntu/db-backups/pre-extend-20260824.sql          24 Aug
/home/ubuntu/backups/haasib-predeploy-20260823-*.sql.gz  23 Aug
```

There is no cron entry and no systemd timer that produces any of them. `archive_mode` is
`off`, so there is no WAL archiving either. There is no `aws` CLI on the box, so nothing is
copied anywhere else. `DB_HOST=127.0.0.1` — Postgres runs on the same instance, on the same
disk as those dumps.

Two consequences, and both are unrecoverable:

- Lose the volume and you lose the database **and every backup of it**, together.
- Short of that, the restore point is whenever a human last happened to think of it.

On 20 September the dev database was destroyed and there was no backup; it was rebuilt from
seeders and anything a seeder cannot reproduce was gone. Production is one comparable
afternoon from the same outcome, except it is the real one.

The database is **24 MB**. This is not an engineering problem, it is an unattended one.

**The server is a Lightsail instance, not EC2** - its role is `AmazonLightsailInstanceRole`,
region `ap-southeast-1`, zone `ap-southeast-1a`. That decides the tooling: Lightsail cannot take
a custom IAM role, and EBS lifecycle policies do not apply. The whole database compresses to
**1.0 MB**. The plan, in three layers:

1. **Lightsail automatic snapshots.** One switch in the console (Instance → Snapshots →
   Automatic snapshots): a daily whole-instance snapshot, seven kept. Covers the database, `.env`,
   uploaded files, nginx, the systemd units and `deploy.sh`. Postgres restores from it the way it
   recovers from a crash, by replaying its log. Five minutes, and **it is not on yet** - it is the
   first thing to do.
2. **A nightly `pg_dump` to S3,** under a dated name, versioning on, 90-day lifecycle. The key on
   the server is an IAM user allowed `s3:PutObject` and nothing else, so neither a mistake nor a
   compromise on the box can delete its own backups. A second disk would not give that: it
   shares the instance's failures, and anything that can write to it can delete from it.
3. **An alert when a backup does not arrive.** The job pings healthchecks.io on success; 25 hours
   of silence sends an email. Otherwise this becomes the next Redis worker - a nightly job that
   stopped months ago, found on the day it is needed.

Plus: keep `APP_KEY` in a password manager. Fortify encrypts two-factor secrets with it, so a
dump restored without it cannot read those columns.

Waiting on the console side - the bucket, the IAM user and the healthchecks check. The server
side (CLI, script, systemd timer, first run, and a restore drill into a scratch database) is an
hour once those exist. Later, with paying customers, a managed database removes the scripts
altogether.

### 2. Nothing watches production

**Status: partially addressed. Logging is fixed; alerting does not exist.**

The Redis queue worker crash-looped from **9 June to 20 September** — three and a half
months. It generated 2,217,563 errors and a 9.19 GB log file. It was found only because the
disk filled and someone went looking for something unrelated.

There is no Sentry, Bugsnag or Flare in `.env`. No uptime check, no disk alert, no error-rate
alert. Logging was fixed on 20 September (`LOG_STACK=daily`, `LOG_LEVEL=info`, 14-day
retention), which makes errors findable. It does not make anyone look at them.

**What to do:** an error tracker, an uptime ping, and a disk-space alert. This is the
difference between finding out in an hour and finding out in September.

Related, found 23 September: Ubuntu's unattended upgrades run at **06:13 UTC - 11:13 in
Pakistan**, the middle of the trading day - and restart Postgres when a library it uses is
patched. It was down for about five seconds and the queue worker logged the only error of the
day. Nothing was lost, but anyone saving in that window gets an error. Move
`apt-daily-upgrade.timer` to 22:00 UTC (3am local).

### 3. The test suite gates nothing

**Status: unresolved.**

`.github/workflows/ci.yml` runs exactly one Pest file — `tests/Unit/RlsMigrationGateTest.php`
— plus `npm run build` and a deploy-rollback script. There are roughly 950 passing tests and
**none of them runs before a deploy.**

Combined with the local policy that tests are opt-in (which is the right policy for a
13-minute suite during development), the practical position is that the suite runs when
somebody remembers. Every deploy on 20–21 September went out on a green CI that had not
executed a single feature test.

Thirteen minutes is a reasonable price for a push to `main`. Running `tests/Feature` in CI
would have caught the 405 below the moment a test for it existed.

---

## Structural, in order of leverage

### The typed route helpers already exist, and the pages ignore them

`resources/js/actions/App/Http/Controllers/*.ts` is generated from the routes and carries the
correct HTTP method for every endpoint. The 405 found during the manual E2E happened because
`useInlineEdit` hardcoded a URL string and called `form.patch()` against a route registered
`PUT`-only — breaking 21 controls across the customer and vendor pages.

Had the call sites used the generated helper, the mismatch would have been a **compile
error**. The generation is already being paid for; the benefit is not being collected.
Migrating call sites is the systemic version of the fix applied by hand in `d8c50466`.

### Audit for other dual-write splits

The opening-balance defect — the form wrote a column, the action posted the ledger, nothing
reconciled them — is a shape, not an incident. Likely neighbours: `current_balance` and
`last_reconciled_balance` on bank accounts, and anywhere a controller does
`Model::create([...$validated])` with a spread.

`CLAUDE.md` already mandates `Bus::dispatch()` over direct writes. Nothing enforces it.

The enforcement pattern already exists in this repo, twice: `ValidationRuleConnectionRoutingTest`
scans the codebase for a bad idiom, and `InlineEditRouteVerbsTest` pins a contract. A third
scan — controllers must not mass-assign financial columns — would make the rule real rather
than aspirational.

### Zero-downtime deploys

`deploy.sh` runs `artisan down` and takes the application offline for the duration. It rolls
back cleanly, which is genuinely good work, but users see maintenance mode on every release.

---

## The core features are being rebuilt inside the modules

Invoicing, billing, customers, vendors, accounts, the general ledger - these are core. They
are the same operations whatever the business is. A fuel station, an umrah operator and a
hotel all invoice somebody, owe somebody, hold somebody's money and close a period. Only the
vocabulary and the screens should differ.

The design was for modules to route that work through the core. What has happened instead is
that each module has grown its own version of whatever it needed, and the versions have
drifted. This is not a security or a boundaries problem. It is repetition, and the cost shows
up in two places: the same bug has to be fixed several times, and the app stops feeling like
one app.

### Where it has already happened

Each of these was verified in the codebase, not inferred.

| Core feature | Core implementation | The module's own | |
|---|---|---|---|
| Vendor statement | `Accounting\VendorStatementService` | `UmrahCoreService::vendorStatement()` | Umrah calls the core service nowhere |
| Cash position | `DashboardService::getCashPosition()` | `Umrah\Dashboard\Widgets\CashPositionWidget` | the core one reported 0.00 against a ledger holding 3,527,862 because it read the bank-feed column; fixed 21 September to read the ledger. The Umrah copy is still separate |
| Investor / partner | `auth.partners` + `PartnerTransaction` | `fuel.investors` | the fuel copy carries its own `total_invested` and `total_commission_earned` running totals |
| Dashboard composition | `WidgetRegistry` + `DashboardWidget` | `FuelDashboardService::getHomeCards()` | one module of four uses the registry |
| Opening balance | `OpeningBalance\SaveAction` | the bank account form's own column write | fixed 21 September; they had disagreed by the entire balance |
| Account validation | one `Rule::exists` idiom | 32 copies across 21 files | all 32 were wrong in the same way |
| Company purge (seeding) | - | `DemoSupport::purgeDemoCompany` and `ScenarioFuelStationSeeder::purge` | both deleted fuel items before tanks and swallowed the error; collapsed into `CompanyPurger` on 23 September |

### What is being done right, for contrast

The data layer often does link back, and that is worth saying because it shows the intent
survived in places:

- `umrah.expenses` carries `transaction_id` and `expense_account_id`, so an umrah expense is a
  real posting in the general ledger, not an off-books record.
- `umrah.visa_vendors` carries `vendor_id`, so a visa vendor *is* an `acct.vendors` row with
  extra columns.

So the pattern is not that modules refuse to use the core. It is that they link to the core's
**tables** and then rebuild the core's **services and screens** on top. That is exactly the
layer where the repetition hurts most, because that is the layer the user sees.

### Why this is a product problem, not only an engineering one

A vendor in Haasib currently has more than one page, more than one statement, and more than
one balance depending on which module you came in through. Somebody who learns the vendor
screen in Accounting has not learned the vendor screen in Umrah. The word is the same, the
behaviour is not, and the application reads as several applications sharing a login.

Cohesion is not a coat of paint over that. It is the consequence of there being one
implementation to present.

### The rule this needs: the Core Contract

A module may add **vocabulary**, **workflow** and **screens**. It may not add a second
implementation of a core noun or a core calculation.

The name follows the repo's own vocabulary - `docs/contracts/` for schemas,
`frontend-experience-contract.md` for interaction - so "that breaks the Core Contract" is a
sentence people can say in review. The enforcing scan is `CoreContractTest`.

Concretely: a module that needs a vendor statement calls the vendor statement service and
presents the result its own way. It does not compute one. A module that needs a customer
extends the customer with its own profile table - the way `fuel.customer_profiles` already
does - rather than growing a parallel party.

### How to close it without a rewrite

1. **Name the core surface explicitly.** Customers, vendors, accounts, invoices, bills,
   payments, credit notes, journals, statements, aging. Write the list down; it does not
   exist anywhere today, which is most of why the boundary keeps being crossed by accident.
2. **Give each one address on the `CommandBus`.** The mechanism already exists and already
   carries validation and permission: `dispatch()` runs the action's own `rules()` and
   `permission()` before `handle()`. Accounting registers 80 actions this way, FuelStation 6,
   Payroll 1. The gap is adoption, not machinery.
3. **Collapse the duplicates opportunistically, not as a project.** Every time a bug is fixed
   in one of the pairs above, that is the moment its duplicate folds into the core. The
   opening balance fix is the worked example: two screens, one command, and the second copy of
   the figure demoted from a rival truth to a derived one.
4. **Enforce it with a scan.** The repo already does this twice -
   `ValidationRuleConnectionRoutingTest` and `InlineEditRouteVerbsTest` fail the build on a
   forbidden idiom. A third should fail when a module composes a journal line, writes a
   financial column directly, or reimplements a named core calculation.

Point 4 is what makes the rest hold. `CLAUDE.md` already says `new Service()` should be
`Bus::dispatch()`. Nothing enforces it, so the rule has been quietly losing for months.

---

## Fuel module: known limits going into live data

Collected while preparing the first real station's September backfill. None blocks it; each is
a thing somebody will hit.

### A rate-change day is never marked in the history

The "Rate change" marker on the close history appears only when a day was *split* at a
midnight reading. Pakistani stations close the register at midnight on a rate-change night,
so each register day is already all old rate or all new rate and nothing is ever split - the
marker will never show. Fix: mark any close with a `fuel.rate_changes` row effective on its
business date. Display only; no pricing logic changes.

The same condition hides `fallback_liters`: when no reading was taken at the change, the day is
priced at one rate, and the count of litres valued that way is recorded but shown nowhere.

### Ten row lists in the daily close form are keyed by position

Adding a second expense row put the bank-deposit amount into the first row's field. Rows keyed
by index let a reused input keep another row's value. Fixed 22 September for expenses and bank
deposits; **ten** other lists in `DailyClose/Create.vue` still use `:key="index"`. Less exposed
now that most entries go through their own pages, but the fix is mechanical.

### 31 files still start forms on the UTC date

`new Date().toISOString().slice(0, 10)` is the UTC date, and Pakistan is UTC+5, so between
midnight and 5am local time a form starts on yesterday. The five main entry forms were moved to
local time on 23 September, and now also remember the last date used in the tab; **31 files**
elsewhere still use the UTC pattern.

### A totaliser that rolls over blocks the close

Since 22 September a closing meter below its opening one is refused - it had silently booked
zero litres, which lost 375 L of diesel on day 13 of the scenario run. A meter that genuinely
rolls past its last digit also reads low and is refused too. Rare on these meters, and loud
rather than silent, but it will stop a station's close until rollover is handled.

**The refusal itself has no test.** Everything else deployed that week does.

### Payroll cannot be back-dated

`PayrollDashboardController::runMonthly()` builds the period from `now()`, and the close picks
payslips up by `approved_at`, which is stamped at approval. A station closing yesterday's
register cannot post yesterday's wages, and a backfill cannot run September payroll at all -
salaries go in as dated expenses instead.

### A close records its business date, not when its readings were taken

Normally that is enough. When a station moves its sign-off - midnight on a rate-change night,
leaving one day at 16 hours and the next at 32 - nothing in the data says the period was short
or long, and per-day figures quietly compare unequal periods. Record the reading time on the
close so an odd-length day is visible; the arithmetic, which is meter-based, is already right.

---

## Look and feel

### Burn down the palette baseline

`scripts/palette-baseline.json` grandfathers every existing violation: hardcoded colours,
hand-rolled tables, money rendered as plain text. The ratchet correctly stops new ones. The
existing ones are exactly what makes a screen look homemade next to a finished one — and
because it is a counted list, it is a finishable job.

### Accessibility

`FormField.vue` was built because inputs lacked generated ids and wired labels. That fixed one
component; most forms still do not use it. Labels, `focus-visible` states, and keyboard paths
through dialogs are what make software feel solid rather than fragile.

### Empty, loading and error states

The single strongest "is this finished" signal. A table with no rows should say something
useful, not render blank.

### Custom error pages

`resources/views/errors/` does not exist, so an authenticated user hitting a 500 gets
Laravel's stock page. Guests are unaffected — unknown URLs redirect to login.

---

## What is already good

Worth stating, because the gap is not where it usually is.

The advisory locking and generation retirement in `OpeningBalance\SaveAction`, the
`protect_locked_opening` trigger, the RLS policies — that is careful, senior work, better
than most of what ships. Production RLS was verified on 21 September and **is** genuinely
enforced, which is the thing one would normally expect to find broken.

The gap is not craft in the code. The code is carefully defended. The operation around it is
not defended at all.

---

## Order of work

1. Backups: Lightsail snapshots today, then the S3 dump and its alert. The only item where
   waiting has an unrecoverable downside - and live data is now going in.
2. Error tracking and alerting, and moving unattended upgrades to 3am.
3. `tests/Feature` in CI.
4. Fuel limits live data will reach first: the rate-change marker, the ten position-keyed
   lists, the UTC date defaults, and a test for the meter refusal.
5. Route helpers at the call sites.
6. Dual-write audit and `CoreContractTest`.
7. The core surface written down, and the next duplicate collapsed into it.
8. Palette baseline, accessibility, empty states.
9. Zero-downtime deploys, custom error pages, reading times on the close, payroll back-dating,
   meter rollover.

Items 6 and 7 are the same work seen from two sides: the audit finds the duplicates, the
core is where they go.
