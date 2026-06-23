# Production Access Hardening Checklist

Use this before client handoff or production deployment when modules, permissions, or tenant tables change.

## Required Checks

- Run migrations:
  - `php artisan migrate --force`
- Sync permissions:
  - `php artisan app:sync-permissions`
  - `php artisan app:sync-role-permissions`
- Confirm no code references missing permission constants:
  - Search `Permissions::` references and compare with `App\Constants\Permissions`.
- Confirm RLS policies do not cast empty settings directly:
  - Unsafe pattern: `current_setting('app.is_super_admin', true)::boolean`
  - Safe pattern: `COALESCE(NULLIF(current_setting('app.is_super_admin', true), '')::boolean, false)`
- Confirm each tenant route uses company context:
  - Route contains `/{company}`.
  - Middleware includes `auth` and `identify.company`.
  - Module routes include `require.module:<module-key>`.
- Confirm writes use FormRequests:
  - `authorize()` checks a permission through `hasCompanyPermission(...)`.
  - `rules()` avoid schema-qualified Laravel database rules like `Rule::exists('umrah.agents')`; use model-based closures for schema tables.
- Confirm inactive memberships cannot write:
  - Temporarily mark `auth.company_user.is_active = false` in a transaction.
  - The write FormRequest must return unauthorized.
- Confirm active owners/admins can write:
  - Existing owner/admin should pass authorization and create one test record.
  - Remove the test record after the check.

## Umrah Module Baseline

- Enabled only for companies with `settings.modules.umrah = true`.
- Must not require fuel station or inventory modules.
- Write permissions:
  - `umrah.agent.create`
  - `umrah.vendor.create`
  - `umrah.group.create`
  - `umrah.group.update`
  - `umrah.payment.create`
  - `umrah.settings.update`
- Read/report permissions:
  - `umrah.agent.view`
  - `umrah.vendor.view`
  - `umrah.group.view`
  - `umrah.report.view`

## Known Safe Smoke Commands

```bash
php artisan app:sync-permissions
php artisan app:sync-role-permissions

php artisan tinker --execute "$(cat <<'PHP'
$pattern = "%current_setting('app.is_super_admin', true)::boolean%";
$rows = DB::select(
    "select schemaname, tablename, policyname from pg_policies where coalesce(qual, '') like ? or coalesce(with_check, '') like ?",
    [$pattern, $pattern]
);
echo "unsafe_policy_count=".count($rows).PHP_EOL;
PHP
)"
```

Expected result:

```text
unsafe_policy_count=0
```
