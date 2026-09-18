# Row level security: enforcement readiness

**Date:** 18 September 2026
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

## What has been done

| | |
|---|---|
| `34d9173a` | Provisions `haasib_app` where it does not exist — not a superuser, no `BYPASSRLS`. Production already had this role and already connects as it; the migration matters for other environments. Note it **does** own production's tables, which is why FORCE is the switch. |
| `b914d0d0` | Carries company context into every write path that runs outside an HTTP request: console commands, seeders, queued jobs. |
| `06b8e6d4` | Converted ~112 `exists:` validation rules off the schema-named connections, which are separate sessions and carry no company context. |
| `c250a70d` | The same for 16 `unique:` rules, which had never once fired under test for the same reason. |

None of these change runtime behaviour. `.env` still reads
`DB_USERNAME=postgres`, so the role exists but is unused.

## What blocks the switch

Running the full PHP suite as `haasib_app` against the test database:

```
Tests: 67 failed, 151 passed (741 assertions)
```

The failures are overwhelmingly one shape:

```
SQLSTATE[42501]: Insufficient privilege: 7 ERROR:
new row violates row-level security policy for table "..."
```

58 of them. These are write paths — mostly test fixtures, but each one has to
be examined rather than assumed — that insert rows without
`app.current_company_id` matching the row's own `company_id`. Under a superuser
they succeed silently; under `haasib_app` PostgreSQL refuses them. **This is the
work that remains**, and it is not a configuration problem: until those paths
set context, switching the role turns a passing suite into a failing
application.

One further failure is structural: the migration that creates the role cannot
run as the role it creates (`permission denied to create role`). Role creation
belongs to a privileged connection — `pgsql_migrator` — not to the application
connection.

## Why the failures are dangerous rather than merely noisy

A read without company context returns **nothing**, and reports success. It does
not error. So a missing `set_config` surfaces as an empty screen or an empty
report, not as a stack trace — the same way a genuinely empty table would. A
write without context does error, which is the louder and safer half.

This asymmetry is why the switch should not be made hopefully. An incomplete
rollout produces screens that look fine and are silently blank for some tenants.

## How to perform the switch, when the blocker is cleared

Production is already connected as `haasib_app`, so **no `.env` role change is
needed** — the switch is the `FORCE` itself.

1. Confirm the suite passes as `haasib_app` on the test database. It does not
   today: 67 failures, almost all `new row violates row-level security policy`.
2. Fix those write paths. That is the work; everything else is a step.
3. On a staging copy of production data, run the migration with
   `RLS_ENFORCEMENT=on` and exercise the app: post a close, take a payment,
   record a bill. Writes are the risk, and they fail loudly.
4. Then production, in a maintenance window, with the rollback below to hand.

## Rollback

`ALTER TABLE <each> NO FORCE ROW LEVEL SECURITY` returns every table to
owner-bypass, which is exactly today's behaviour. The migration's `down()` does
this. No data changes, and the policies themselves can stay in place.

## Recommendation

Do not switch until the 67 failures are resolved and the suite passes as
`haasib_app`. The preparatory work is safe to merge and deploy as it stands: it
changes no runtime behaviour, and it means the eventual switch is a
configuration change rather than a rewrite.
