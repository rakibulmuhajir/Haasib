# Where to pick up — 2026-08-24

Written at the end of the session that deployed the party-model refactor to
production. Everything below is verified unless marked otherwise.

---

## Where things stand

Production is `91faf107`, live at https://haasib.app, all migrations applied.

Today's deploy carried eleven commits, the last four of which matter here:

| SHA | What |
|---|---|
| `2a2491de` | Store an agent as the customer it already was |
| `6d095b32` | Store an umrah vendor as the supplier it already was |
| `ef46dda5` | Say what an umrah vendor's type is a type of (`vendor_type` → `service_type`) |
| `85d9a586` | Let a transport fare be saved at all |
| `91faf107` | Let a backfill see the rows it is meant to fill |

Production data after the migrations: **14 agents → 14 distinct customers**
(all typed `agent`), **5 visa vendors → 5 distinct vendors**, no nulls either
side. `umrah.visa_vendors.service_type` renamed, duplicate `name` / `phone` /
`email` / `logo_url` columns dropped from both tables.

Local test suite: **319 passed / 1382 assertions**. That is the baseline — if a
fresh session sees a different number, something regressed.

### Two things fixed after the deploy

- `APP_URL` was `localhost` (no scheme), so every absolute `url()` / `route()`
  was malformed. Now `https://haasib.app`, config re-cached, verified.
- The production `.env` was `.env.example` with a production block appended, so
  four keys were defined twice. The live values were the later ones, which meant
  a **shadowed `APP_DEBUG=true` was sitting at line 4** — any reorder or dedupe
  of that file would have flipped production into debug. Duplicates removed.
  Backup at `~/db-backups/env-20260824.bak` on the server.

Still open, deliberately: `LOG_LEVEL=debug` on production. Noise, not
correctness. Flip to `warning` when convenient.

---

## The recommendation: deepen, don't widen

One client is testing, with Luna doing QA. The constraint is not features, it is
confidence — there is no CI, no JS test infrastructure, and a party-model
refactor that went live on real production data today. A new module or submodule
multiplies untested surface against a team of one tester.

Do these in order.

### 1. Verify today's refactor against the client's real data

Highest value available, because it is new, live, and touches money.

Luna should walk the **agent-linked booking path end to end**. That path was
completely broken before this change: the form disabled the Buyer field because
`umrah.agents.customer_id` was null on every row, and then the request rejected
the submission with "The customer id field is required." Fourteen production
agents now have customers for the first time.

Also worth a pass: anywhere an agent or umrah vendor name is displayed, since
those four fields now resolve through a relation rather than a local column.
`VisaVendor` appends `name` / `email` / `phone` / `logo_url` from the extended
`acct.vendors` row — **any query that narrows selected columns must keep
`vendor_id`**, or all four come back null.

### 2. Close the transport editing gap

Transport items have no post-creation editing surface. Enter a bus wrong and it
cannot be fixed. That is a functional hole, not polish — it stops a test client
dead and produces a support call instead of a bug report.

Well-scoped and ready to start.

### 3. Burn down the logged defect list

All found and diagnosed in earlier sessions, none fixed. **These were identified
by reading, not reproduced — re-confirm each before fixing.**

- The group edit page never recomputes pricing after flipping `includes_visa`
  false → true
- `resolveGroupVendors()` unconditionally requires a resolvable visa vendor
- `FuelStation/Sales/Form.vue` indexes `[0]` into `onError` strings
- `AmountInput.vue` carries its own hardcoded `CURRENCY_CONFIG`
- `Periods/Show.vue` has a period-level Badge outside a cell slot
- `FuelStation/FuelReceipts/Index.vue` appears to be dead code
- `2026_06_24_000005_link_umrah_agents_to_users.php` has a latent `dropIndex`
  bug — Blueprint `dropIndex` is not schema-qualified (see gotchas below)

### 4. Then CI

319 tests that only run when someone remembers to run them are a safety net with
a hole in it. Needs a Postgres service container and RLS `set_config` handling.

Deploy-from-CI needs `munshi.pem` in GitHub Secrets — **recommend holding that
back** until there is a reason to want it.

---

## Parked, with reasons

**A new module** — breadth for an audience of one. Worst option right now.

**An umrah submodule** — better, but ticketing only just landed and has not been
through a full QA cycle. Stacking a second unproven thing on the first.

**The `pay.*` validation landmine** — 19 string validation rules name FORCE-RLS
tables (`exists:pay.employees`, `unique:pay.leave_types`, and so on). A string
rule's dotted table name is parsed as `connection.table`, so it runs on a
separate connection with no RLS context, and on a FORCE'd table `exists:` fails
**closed** — valid input gets refused. **Zero umrah rules are affected**; umrah
passes model classes, which are safe. `fuel.pumps` / `nozzles` / `investors` are
not FORCE'd, so those three are fine too.

This is a must-fix *before anyone demos payroll*, and irrelevant until then.

**The RLS `FORCE` pass** over the 67 unforced tables — user called it "good to
have". Note the hazard: turning on FORCE means any query without
`app.current_company_id` silently returns zero rows, which hits seeders,
migrations, queue jobs and console commands. Audit those paths first.

**Ledger design plan** (`docs/superpowers/plans/` and the plan file from the
ledger session) — `Vouchers/Show.vue` at 1243 lines onto `LedgerDocument`,
`Derivation` adoption, ~81 hand-rolled footers onto `CardFooter`, ~34
hand-rolled spinners.

**Server capacity** — do not buy more RAM. 1.9 GiB + a 2 GiB swapfile builds in
~55s, and builds are rare. Settled.

---

## Gotchas a fresh session will otherwise rediscover the hard way

**Row-level security.** 39 production tables carry `FORCE ROW LEVEL SECURITY`
(all `umrah.*`, all `pay.*`, two `fuel.*`), which subjects even the owning role
`haasib_app` to the isolation policy. Dev connects as `postgres`, a **superuser,
which bypasses RLS unconditionally** — so no local test can ever catch an RLS
bug. Never generalise a dev RLS observation to production.

A migration or console pass that must cross companies uses the policy's own
escape hatch, not `NO FORCE`:

```php
DB::statement("SELECT set_config('app.is_super_admin', 'true', false)");
try { $work(); } finally { DB::statement("SELECT set_config('app.is_super_admin', 'false', false)"); }
```

Session scope, not `SET LOCAL` — `SET LOCAL` outside a transaction silently does
nothing. This is what `91faf107` fixed, after a backfill selected zero rows,
reported success, and left `ALTER TABLE ... SET NOT NULL` to fail on the 14 rows
it never saw. `pg_dump` fails the same way and needs
`PGOPTIONS="-c app.is_super_admin=true" --enable-row-security`.

**Postgres and schemas.** Blueprint `dropIndex` is not schema-qualified — use
`DB::statement('DROP INDEX IF EXISTS <schema>.<name>')`. A CHECK constraint's
expression survives a column rename, so drop it before renaming.

**`migrate:fresh` does not work on this codebase** — it does not drop the
non-public schemas and dies on `relation "item_categories" already exists`. To
reset the test DB, drop and recreate the database, then `migrate`.

**Tests.** Only one test database (`haasib_test`); concurrent `php artisan test`
runs corrupt each other. `assertSessionHasNoErrors()` **passes on a 500** — pair
it with `assertRedirect()` or a 500 masquerades as success. That is exactly how
a dead transport-fare route hid until `85d9a586`.

**Verification quad**, from `build/`:

```
npx vue-tsc --noEmit 2>&1 | grep -v "^resources/js/actions/" | grep -cE "error TS"   # 10 is baseline
node scripts/lint-palette.mjs    # 0
node scripts/lint-nav.mjs        # 7 definitions
npx vite build                   # exit 0
```

**Deploy.** `ssh -i ~/.ssh/munshi.pem ubuntu@52.76.76.243 'cd /var/www/haasib && ./deploy.sh'`.
It merges code and swaps assets *before* migrating, so a migration failure
leaves code ahead of the database. See `deploy-flow.md` at repo root.

`deploy.sh:91`'s `git restore --worktree -- build/public/build` is now vestigial
— `2982e9cb` untracked those assets, and its own comment predicted this. Safe to
delete next time that file is touched.
