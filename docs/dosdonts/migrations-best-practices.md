# Migrations Dos and Don'ts

## Highlighted Missteps
- **Used `Schema::hasSchema('auth')`** – Laravel's schema builder has no such API. The migration failed before creating tables.
- **Seeded UUIDs with `generate_uuid()` before enabling pgcrypto** – The helper function referenced `gen_random_uuid()` but the extension was never added, so the migration crashed on a fresh database.
- **Dropped tables before dropping triggers** – Calling `DROP TRIGGER ... ON auth.modules` after `Schema::dropIfExists('auth.modules')` raises `ERROR: relation does not exist` on rollback.
- **Left one migration's `down()` inconsistent** – The `auth.audit_entries` rollback still dropped the table before the trigger, showing how easy it is to miss when duplicating patterns.
- **Service layer referenced columns that the schema never created** – `AuthService` started writing to `last_login_at`, but the users table lacked that column, causing every login to throw a SQL error.
- **Created RLS insert policies without `WITH CHECK`** – Row Level Security was effectively bypassed because inserts were not validated against the policy predicate.
- **Casting `current_setting()` straight to UUID** – tenant helpers that do `current_setting(... )::uuid` explode when the setting is missing; always guard or `NULLIF` before casting.
- **Enabling RLS without forcing it** – PostgreSQL lets the owning role bypass policies unless you `ALTER TABLE ... FORCE ROW LEVEL SECURITY`.
- **Blocked bootstrap data with RLS** – the `company_user` policy demanded an existing owner/admin record before any insert, making the very first membership impossible on a fresh database.

## Do This Instead
- Create schemas or extensions with plain `DB::statement('CREATE SCHEMA IF NOT EXISTS auth')` or `DB::statement('CREATE EXTENSION IF NOT EXISTS pgcrypto')`. These statements are idempotent and work in both up/down directions.
- Always enable dependencies (extensions, helper functions) before calling them in the same migration. Alternatively, keep pure PHP helpers (`Str::uuid()`), which do not depend on database state.
- Tear down triggers, functions, and policies **before** dropping the table in `down()` so PostgreSQL does not choke during rollback.
- After editing any migration, run both `artisan migrate` and `artisan migrate:rollback` (or `migrate:fresh`) in a disposable database to confirm the full lifecycle still works.
- Keep migrations and the service layer in sync: whenever new service logic reads/writes a column, add (or backfill) that field in a migration before shipping.
- For RLS, pair every `USING` clause with a matching `WITH CHECK` clause for `INSERT`/`UPDATE` policies. Test policies with unprivileged roles to confirm they actually block writes.
- Guard tenant lookups: wrap `current_setting(..., true)` with `NULLIF` (or raise a clear error) before casting to UUID.
- Always `FORCE ROW LEVEL SECURITY` after enabling it so the table owner doesn’t bypass policies during tests or CLI runs.
- When policies rely on membership lookups, include a bootstrap escape hatch (or seed through a privileged role) so the first owner/admin row can be inserted.
- Run migrations in a disposable database (Docker, CI) to surface missing extensions, schema calls, and rollback failures early.

## Quick Checklist
- [ ] No calls to non-existent Schema helpers.
- [ ] Extensions/functions enabled before first use.
- [ ] `down()` reverses objects in the opposite order of `up()`.
- [ ] Rollback checks (`artisan migrate:rollback`, `migrate:fresh`) are part of the review checklist.
- [ ] RLS policies include `WITH CHECK` where needed.
- [ ] `artisan migrate:fresh` succeeds locally before pushing.
