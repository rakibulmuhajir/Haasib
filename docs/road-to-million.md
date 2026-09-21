# Road to a million

What stands between Haasib as it is now and Haasib as something you would hand to a paying
customer without flinching. Written 21 September 2026, after a session spent inside the
production box rather than guessing from the repo.

Everything below was checked on the live server. Where something is an assumption rather
than a reading, it says so.

---

## The three that matter

### 1. Production has no automated backups

**Status: unresolved. Fix this before anything else on this page.**

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

**What to do:** nightly `pg_dump`, 14 days of retention, and an off-host copy to versioned
object storage. Roughly twenty minutes of work for a one-day RPO. Turn on WAL archiving
afterwards if you want minutes instead of a day.

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

1. Backups. The only item where waiting has an unrecoverable downside.
2. Error tracking and alerting.
3. `tests/Feature` in CI.
4. Route helpers at the call sites.
5. Dual-write audit and the enforcing scan.
6. Palette baseline, accessibility, empty states.
7. Zero-downtime deploys, custom error pages.
