# Models Dos and Don'ts

## Highlighted Missteps
- **Applied `HasUuids` to a pivot without an `id` column** – Laravel tried to generate a UUID for a non-existent primary key on `auth.company_user`, breaking inserts.
- **Persisted `$this->settings` with no backing column** – `User::setSetting()` attempted to update a column that the `auth.users` table does not provide.
- **Called `request()` / `auth()` without guards in CLI contexts** – Queue workers and console commands lack HTTP helpers, leading to undefined-function or null-pointer errors.
- **Left relationships pointing at phantom columns** – `JournalTransaction::reference()` expected `reference_type`/`reference_id`, but the migration never created them, so every call crashed at runtime.

## Do This Instead
- Let composite pivots extend `Model` without UUID traits; declare `$incrementing = false` and `$keyType = 'string'` only when the table actually has a surrogate key.
- Keep model accessors/mutators aligned with the schema. Add JSON columns (via migration) before storing structured settings, or move ad-hoc state to a dedicated `settings` relation/table.
- Audit every `$fillable`, `$casts`, and relationship against the latest migrations; delete or feature-flag code paths that depend on columns still on the roadmap.
- Wrap HTTP-only helpers behind feature detection: `if (function_exists('request') && request())` before reading IPs or sessions; inject dependencies where possible.
- Centralize audit hooks in observers or events to avoid repeating request/auth checks across models.
- Prefer value objects or DTOs for complex state changes; models should stay thin and persistence-focused.

## Quick Checklist
- [ ] Traits match actual columns (UUIDs, timestamps, soft deletes).
- [ ] Accessors/mutators correspond to real database fields.
- [ ] No direct reliance on global helpers inside jobs/CLI without guards.
- [ ] Pivot models stay free of conflicting primary-key traits.
- [ ] Domain rules live in actions/services, not inside Eloquent events.
