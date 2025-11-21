# RBAC Permission System Seeder

**Purpose**: Provide a canonical recipe for seeding permissions and roles across Haasib, aligned with the guidance in `docs/briefs/rbac_implementation_brief.md`. Use this document while maintaining `app/database/seeders/RbacSeeder.php` or any module-specific RBAC registrar.

---

## 1. Seeder Goals

1. Register every permission string defined in `App\Constants\Permissions` or module equivalents.
2. Attach metadata (`domain`, `scope`, `description`) so modules can query permissions programmatically.
3. Seed dual-tier roles:
   - **System Roles** (team IDs from `docs/system-users-design.md`).
   - **Company Roles** (per-tenant bundles, using `company_id` as `team_id`).
   - **Module Roles** (teacher, student, etc.) that declare their scope explicitly.
4. Reset Spatie caches and log the operation via `audit_log()` for traceability.

---

## 2. Seeder Structure

```php
class RbacSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $registrar = new RbacRegistrar();
        $this->registerCorePermissions($registrar);
        $this->registerModulePermissions($registrar);

        $registrar->persistPermissions();
        $registrar->persistRoles();

        artisan_command('permission:cache-reset');
        audit_log('rbac.seeder.run', ['count' => $registrar->permissionCount()]);
    }
}
```

### 2.1 Core Registration

```php
private function registerCorePermissions(RbacRegistrar $registrar): void
{
    $registrar->registerDomain('system', [
        ['name' => 'system.companies.create', 'scope' => 'system', 'description' => 'Provision companies'],
        // ...
    ]);

    $registrar->registerRole([
        'name' => 'super_admin',
        'scope' => 'system',
        'team_id' => '00000000-0000-0000-0000-000000000000',
        'permissions' => $registrar->allPermissionsForScope('system'),
    ]);
}
```

### 2.2 Module Hook

Modules implement `ModuleRbacRegistrar` and expose a service provider that calls into the seeder:

```php
public function registerModulePermissions(RbacRegistrar $registrar): void
{
    foreach (ModuleRegistry::rbacRegistrars() as $moduleRegistrar) {
        $moduleRegistrar->registerPermissions($registrar);
        $moduleRegistrar->registerRoles($registrar);
    }
}
```

---

## 3. Role Bundles

| Role               | Scope   | Key Permissions (example)                           | Notes |
|--------------------|---------|-----------------------------------------------------|-------|
| `super_admin`      | system  | All `system.*`, `companies.*`, seeding hooks        | Uses sentinel UUID |
| `systemadmin`      | system  | `system.*` minus restricted list                    | Cannot manage super admins |
| `company_owner`    | company | `companies.*`, `acct.*`, `ledger.*`, `ops.*`        | Full tenant control |
| `accounting_admin` | company | `acct.*`, `ledger.entries.post`, `ledger.period-close.*` | Controls period close |
| `accounting_operator` | company | `acct.invoices.*`, `acct.payments.*` (no posting) | Data entry |
| `accounting_viewer` | company | Read-only `acct.*`, `rpt.*`                        | Reporting |
| `edu_teacher`      | company | `edu.classes.grade`, `edu.portal.view`              | Example module role |
| `edu_student`      | company | `edu.assignments.submit`, `edu.portal.view`         | Example module role |

---

## 4. Running the Seeder

```bash
php artisan db:seed --class=RbacSeeder
php artisan permission:cache-reset
```

**CI requirement**: pipelines must run the seeder on fixtures and fail if permissions drift (snapshot test).

---

## 5. Maintenance Checklist

1. Update `docs/briefs/rbac_implementation_brief.md` whenever permission taxonomy changes.
2. Reflect new domains/roles here with a short description.
3. Ensure module teams contribute registrar hooks before merging features.
4. Track restricted system permissions in one place to prevent `systemadmin` escalation.
5. Document any tenant-specific role overrides in their company settings specs.

---

Use this seeder guide as the blueprint for implementing or reviewing RBAC data fixtures across the entire codebase.***
