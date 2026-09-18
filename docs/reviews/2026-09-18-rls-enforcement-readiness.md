# Row level security: enforcement readiness

**Date:** 18 September 2026
**Status:** prepared, **not switched on**. The application still connects as the
`postgres` superuser, so every RLS policy in this database is bypassed today.

## What is true right now

Tenant isolation rests entirely on application-level `company_id` scoping. The
~67 tables carrying policies, plus `fuel.daily_close_activity`,
`fuel.daily_close_drafts` and `fuel.daily_close_reading_corrections`, are
protected on paper only: a superuser bypasses row level security
unconditionally, `FORCE ROW LEVEL SECURITY` included.

## What has been done

| | |
|---|---|
| `34d9173a` | Creates `haasib_app` — not a superuser, not `BYPASSRLS`, not the table owner — with only the privileges the application needs. |
| `b914d0d0` | Carries company context into every write path that runs outside an HTTP request: console commands, seeders, queued jobs. |
| `06b8e6d4` | Converted ~112 `exists:` validation rules off the schema-named connections, which are separate sessions and carry no company context. |

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

1. Confirm the suite passes as `haasib_app` on the test database.
2. Verify on a staging copy that `app.current_company_id` is set on every
   request path, and spot-check that a query without it returns zero rows rather
   than another company's.
3. Change `DB_USERNAME` in the server `.env` to `haasib_app` and set its
   password. Leave `pgsql_migrator` as the owning role so migrations continue to
   run.
4. `php artisan config:clear` and restart the workers.
5. Verify isolation directly: sign in as two companies and confirm each sees
   only its own data; then, as `haasib_app` with no GUC set, confirm a
   policy-protected table returns no rows.

## Rollback

Change `DB_USERNAME` back to `postgres`, `php artisan config:clear`, restart.
One step, no migration, no data change. Nothing in `34d9173a` or `b914d0d0`
needs reverting — the role simply goes unused again, exactly as it is today.

## Recommendation

Do not switch until the 67 failures are resolved and the suite passes as
`haasib_app`. The preparatory work is safe to merge and deploy as it stands: it
changes no runtime behaviour, and it means the eventual switch is a
configuration change rather than a rewrite.
