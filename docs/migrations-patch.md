Migrations Strategy: Baseline + Patch Table

Goal
- Keep existing migrations as your baseline history.
- Apply all future changes as "patch" migrations tracked separately in `migrations_patches`.

What was added
- Config: `config/database.php` now allows `DB_MIGRATIONS_TABLE` override. Default remains `migrations`.
- Commands:
  - `php artisan make:patch NameOfMigration` → creates a migration under `database/migrations_patches`.
  - `php artisan migrate:patch` → applies patch migrations from `database/migrations_patches`, tracking them in `migrations_patches` table.
  - `php artisan migrate:patch rollback --step=1` → rolls back recent patch steps.
  - `php artisan migrate:patch status` → shows patch migration status.

Workflow
1) Baseline (optional but recommended once stable)
   - Ensure your database reflects the current baseline (run `php artisan migrate`).
   - Optionally create a schema dump: `php artisan schema:dump`.
2) Add a patch migration
   - `php artisan make:patch add_widget_table --create=widgets`
   - Edit the generated file in `database/migrations_patches/`.
3) Apply patches
   - `php artisan migrate:patch` (uses `migrations_patches` repository table)
4) Check status
   - `php artisan migrate:patch status`
5) Rollback (patch-only)
   - `php artisan migrate:patch rollback --step=1`

Notes
- Patch migrations are isolated from the baseline and won’t pollute the default `migrations` table.
- You can still use all standard migration commands for the baseline.
- Use `--database` on `migrate:patch` if you need a non-default connection.

