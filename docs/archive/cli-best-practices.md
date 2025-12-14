# CLI Dos and Don'ts

## Pitfalls We Encountered
- **Assuming an authenticated user exists** – commands called exports that relied on `auth()->user()`, throwing type errors the moment they ran outside HTTP.
- **Skipping context bootstrapping** – enabling/disabling modules without setting the tenant context left Postgres RLS unset and produced empty results.
- **Forgetting to tear down context** – lingering `SET app.current_company_id` calls bleed into subsequent commands/tests.
- **Dropping handlers in the wrong module** – shipping accounting commands from `app/Console/Commands/` meant they never loaded when the module was toggled.
- **Calling helpers that don’t exist** – reaching for `ContextService::getCurrentUser()` (which isn’t defined) causes hard failures the moment the command runs.

## Do This Instead
- Accept a `?User` argument (or fetch a known Super Admin) explicitly and pass it into services that audit or authorize actions.
- Use a dedicated context helper (e.g., `ContextService::setCLICompanyContext()` / `clearCLICompanyContext()`) around any tenant-scoped work.
- Wrap multi-company loops in `try/finally` so context is cleared even when one iteration fails.
- When querying many-to-many relationships from a command, use `wherePivot()` helpers; raw `where('module_id')` hits the wrong table.
- Log/audit with the acting user you pass in; never rely on guards inside command handlers.
- Keep module-specific commands inside their module (`modules/Core/CLI/Commands`, etc.) so enable/disable toggles work.
- Reference only methods that actually exist on shared services—add the helper to the service first, then call it from the command.

## Quick Checklist
- [ ] Commands work with `php artisan …` in a fresh shell (no guard/session assumed).
- [ ] Tenant context set/cleared for every operation that hits RLS.
- [ ] Acting user resolved deterministically (super admin, service account, etc.).
- [ ] Export/report code accepts `?User` and handles CLI callers gracefully.
