# RBAC Implementation Brief

**Version**: 3.0  
**Last Updated**: 2025-11-20  
**Status**: Canonical Implementation Guide  
**Related Files**: `app/docs/briefs/rbac_implementation_brief.md` (workspace summary), `docs/system-users-design.md`, `app/database/seeders/RbacSeeder.php`

---

## 1. 🎯 Purpose & Scope

This brief defines the Role-Based Access Control (RBAC) standard for Haasib across PHP (Laravel 12) and Vue (Inertia) surfaces. It unifies the previous briefs, seeders, and system-user design notes so every module can adopt the same security posture while remaining extensible. The document covers:

1. Permission naming, scoping, and lifecycle management
2. Dual-tier role architecture (system vs company) with module-defined extensions
3. Seeder, authorization, and frontend integration patterns
4. Guidelines for future modules to introduce their own roles/permissions without breaking RLS or constitutional requirements

---

## 2. 🔐 Principles & Guardrails

1. **Permission Naming**  
   Use `{domain}.{resource}.{action}` with lowercase dotted strings (`acct.invoices.create`, `system.audit.view`). Domain choices: `system`, `companies`, `acct`, `ledger`, `ops`, `rpt`, etc. Modules can register new domains (`edu`, `hsp`, `crm`).

2. **Scope Awareness**  
   Every permission carries either a `system` scope (global operations) or `company` scope (requires `company_id`). Modules must declare scope when registering new permissions so middleware can enforce RLS automatically.

3. **Spatie Permission + Team IDs**  
   Roles are namespaced through Spatie’s team support:
   - System roles use sentinel UUIDs from `docs/system-users-design.md`.
   - Company roles use the tenant’s `company_id`.

4. **RLS + ServiceContext**  
   Company-scoped permissions never bypass RLS. Controllers and FormRequests must route through helpers in `BaseFormRequest` and command bus handlers must inject `ServiceContext`.

5. **Auditable Impersonation**  
   System roles interacting with tenant data must impersonate (or switch to) a company context, logging via `audit_log()` with both user UUID and source role.

---

## 3. 🧱 Permission Taxonomy

| Domain            | Example Permissions                              | Default Scope | Notes                                                                 |
|-------------------|---------------------------------------------------|---------------|-----------------------------------------------------------------------|
| `system.*`        | `system.companies.create`, `system.audit.view`    | system        | Reserved for platform operations. Managed only by system roles.       |
| `companies.*`     | `companies.settings.update`, `companies.currencies.enable` | company | Tenant administration (branding, base currency, invites).             |
| `acct.*`          | `acct.invoices.create`, `acct.payments.post`      | company       | Accounting module. Use subresources (`acct.invoice-items.*`).         |
| `ledger.*`        | `ledger.entries.post`, `ledger.period-close.execute` | company    | Ledger domain, including period close command-bus actions.            |
| `ops.*`, `rpt.*`  | `ops.bank-statements.ingest`, `rpt.dashboard.view` | company      | Operational pipelines and reporting materializations.                 |
| `module.*`        | e.g., `edu.classes.grade`, `edu.portal.view`      | declared      | Any module can add its own domain; must document scope + roles here.  |

> **Tip:** Keep permission constants in `App\Constants\Permissions` for shared domains. Modules can ship their own constants classes (e.g., `Modules\Edu\Constants\Permissions`) but must follow the same naming rules.

---

## 4. 🧩 Role Architecture

### 4.1 System Tier (Global)

| Role            | Description                                                              | Team ID (from docs/system-users-design.md) | Key Permissions                                          |
|-----------------|--------------------------------------------------------------------------|-------------------------------------------|-----------------------------------------------------------|
| `super_admin`   | Full platform control, including schema/security overrides.              | `00000000-0000-0000-0000-000000000000`    | All `system.*` + `companies.*` + module seed operations.  |
| `systemadmin`   | Day-to-day platform ops, minus restricted actions (manage super admins, edit schema, security keys). | `00000000-0000-0000-0000-000000000001` | All `system.*` except restricted list from RbacSeeder.    |
| `system_manager` *(optional)* | Read/execute most platform tools, cannot mutate sensitive config. | Reserve new sentinel UUID                 | `system.companies.view`, `system.users.manage`, `system.audit.view`. |
| `system_auditor` *(optional)* | Audit-only view into system logs and settings.              | Reserve sentinel UUID                     | `system.audit.view`, `system.reports.view`.               |

**Rules:**
- Maintain a restricted-permission list in code so `systemadmin` cannot escalate to `super_admin`.
- Require MFA and audit logging for all system users.
- When executing tenant operations, system users must select a company (for RLS) unless the action is purely system scoped.

### 4.2 Company Tier (Tenant)

| Role                | Purpose                                                     | Modules Covered                             |
|---------------------|-------------------------------------------------------------|---------------------------------------------|
| `company_owner`     | Full tenant control: invites, billing, integrations, accounting locking. | `companies.*`, `acct.*`, `ledger.*`, `ops.*`, `rpt.*`. |
| `company_admin`     | Operates tenant settings, users, approvals. No destructive backups. | `companies.*` (except billing), `acct.*` (non-posting). |
| `accounting_admin`  | Period close, posting, approvals, audits.                    | `acct.*`, `ledger.period-close.*`, `ledger.entries.post`. |
| `accounting_operator` | Create/edit invoices/bills/payments, cannot post/void/close periods. | `acct.invoices.*` (except post/void), `acct.payments.allocate`. |
| `accounting_viewer` | Read-only access to accounting + reporting.                  | `acct.*` view, `rpt.*` view.                 |
| `sales_manager` / `sales_rep` *(optional)* | CRM-focused roles when modules require.         | `crm.*`, `customers.*`, `invoices.send`.    |
| `portal_customer` / `portal_vendor` | External portal roles with limited permissions. | `portal.*` or `acct.portal.*`.              |

> These roles are seeded per company (`team_id = company_id`). Tenants can clone/extend them but must not remove required RLS helpers or rename constants without updating documentation.

### 4.3 Module-Defined Roles

Modules may add role bundles when they introduce new domains:

- **Educational Example**  
  - `edu_teacher`: `edu.classes.grade`, `edu.assignments.review`, `edu.portal.view`.  
  - `edu_student`: `edu.assignments.submit`, `edu.portal.view`.  
  - `edu_parent`: `edu.portal.read`.  
- Each module role declares:
  1. **Scope** (`system` if managing the module globally, `company` if tenant-bound).  
  2. **Permission bundle** (list of strings).  
  3. **Seeder hook** or migration script to register roles/permissions.  
  4. **Documentation entry** appended to this brief (section 8.3).

---

## 5. 🧪 Permission Lifecycle

1. **Define Constants**
   - Add to `App\Constants\Permissions` or module-specific constant file.
   - Follow naming taxonomy and annotate scope (PHPDoc or array metadata).

2. **Register via Seeder**
   - Use `app/database/seeders/RbacSeeder.php` as the central orchestrator.  
   - Break permissions into domain arrays to avoid monolithic lists.  
   - For module packages, expose a `registerPermissions(RbacRegistrar $registrar)` method that RbacSeeder calls.

3. **Assign to Roles**
   - System roles: attach `team_id` sentinel values.  
   - Company roles: attach permissions per company via pivot or `sync` calls in tenant bootstrapping logic.  
   - Module roles: call the registrar with metadata so `artisan db:seed --class=RbacSeeder` remains the single command to generate everything.

4. **Cache Invalidation**
   - Always run `php artisan permission:cache-reset` after seeding.  
   - CI pipeline should run seeder + cache reset + smoke tests.

---

## 6. ⚙️ Authorization Patterns (Laravel)

### 6.1 BaseFormRequest Helpers

```php
use App\Constants\Permissions;

class StoreInvoiceRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->authorizeInvoiceOperation('create');
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'issued_at' => ['required', 'date', 'before_or_equal:today'],
        ];
    }
}
```

**Checklist (per request/controller/command):**
- ✅ Import permission constants
- ✅ Call helper (`authorizeCustomerOperation`, `authorizeLedgerOperation`, etc.)
- ✅ Always call `$this->validateRlsContext()` when the helper does not already do so
- ✅ Wrap writes in command bus actions with ServiceContext injection
- ❌ Never return `true` from `authorize()` just to bypass checks

### 6.2 Policy & Middleware
- Use policies when resources require dynamic checks beyond simple permissions (e.g., verifying invoice ownership).
- Tenants switch companies via middleware that sets `current_company_id`; policies must derive company context from there.

---

## 7. 🖥️ Frontend Integration (Vue + Inertia)

1. **Pass Permission Flags**
   ```php
   return Inertia::render('Ledger/PeriodClose', [
       'steps' => $steps,
       'can' => [
           'execute' => $user->hasPermissionTo(Permissions::LEDGER_PERIOD_CLOSE_EXECUTE),
           'lock' => $user->hasPermissionTo(Permissions::LEDGER_PERIOD_CLOSE_LOCK),
       ],
   ]);
   ```

2. **Use Composition API Props**
   ```vue
   <script setup lang="ts">
   const props = defineProps<{ can: { execute: boolean; lock: boolean } }>()
   </script>
   ```

3. **Avoid API Permission Calls**  
   Everything the component needs arrives via initial props. Use watchers only when permission state can change during the session.

4. **Consistent UI**  
   - Hide actions when `can.*` is false.  
   - Disable buttons if action visibility must remain (e.g., show with tooltip explaining lack of permission).  
   - Use PrimeVue + Tailwind per CLAUDE.md instructions.

---

## 8. 🔄 Module & Role Extension Workflow

### 8.1 Module RBAC Checklist
1. Document roles/permissions in the module brief and append to Section 8.3 here.
2. Create permission constants and register them with the RBAC registrar interface.
3. Provide default role bundles (teacher/student, auditor, etc.).  
4. Write feature tests that assert 403 boundaries for new endpoints.  
5. Update Inertia props and components to respect new permission flags.

### 8.2 Registrar Interface (Conceptual)

```php
interface ModuleRbacRegistrar
{
    public function registerPermissions(): array; // return ['edu.classes.grade', ...]
    public function registerRoles(): array; // return [['name' => 'edu_teacher', 'scope' => 'company', 'permissions' => [...]]]
}
```

Modules implement this interface and RbacSeeder loops through registered modules to merge everything into Spatie.

### 8.3 Registered Module Roles (Appendix)

| Module | Scope   | Role           | Permissions (summary)                       | Notes |
|--------|---------|----------------|---------------------------------------------|-------|
| Accounting (core) | company | `accounting_admin` | Full `acct.*`, posting, period close | Default tenant financial controller |
| Accounting (core) | company | `accounting_operator` | Create/update AR/AP, reconcile | No posting/voiding |
| Accounting (core) | company | `accounting_viewer` | Read-only `acct.*`, `rpt.*` | Reports + audit read |
| CRM (planned) | company | `sales_manager`, `sales_rep` | `crm.*`, `customers.*` | Example placeholder |
| Education (example) | company | `edu_teacher`, `edu_student`, `edu_parent` | `edu.*` permissions | Documented when module ships |

Add new rows whenever modules register additional roles.

---

## 9. ✅ Testing & Auditing

1. **Unit/Feature Tests**
   - Unauthorized tests (expect 403).  
   - Authorized tests (expect 200/201).  
   - RLS negative tests (user from company A cannot touch company B).

2. **Seeder Tests**
   - Snapshot permission count to catch accidental removals.  
   - Assert restricted permissions are not assigned to `systemadmin`.

3. **Audit Logging**
   - Log all role/permission changes using `audit_log()` with `user_id`, `company_id` (if any), `role`, `action`.
   - Provide dashboards for system auditors to review modifications.

4. **CI Gates**
   - Run `php artisan db:seed --class=RbacSeeder`.  
   - Run permission-focused test suite.  
   - Validate no hard-coded `true` authorizations via lint scripts.

---

## 10. 🛠 Troubleshooting Cheatsheet

- `php artisan tinker` → `User::find($id)->getAllPermissions();`
- `session('current_company_id')` to verify tenant context.
- `php artisan permission:cache-reset` when permissions seem stale.
- SQL check: `SELECT * FROM acct.customers WHERE company_id = current_setting('app.current_company_id');`
- Review `app/Http/Requests/BaseFormRequest.php` for helper implementations if 403s persist.

---

## 11. 📦 Implementation Roadmap

1. **Phase 1** – Refactor `RbacSeeder` to use domain-specific arrays and registrar hooks.  
2. **Phase 2** – Update all FormRequests/controllers to use new helpers.  
3. **Phase 3** – Ensure Vue pages honor `can` flags.  
4. **Phase 4** – Document any new module roles in Section 8.3 and corresponding module briefs.  
5. **Phase 5** – Automate permission integrity tests in CI.

---

**Status**: ✅ Guide Approved – use this as the definitive reference for all RBAC-related work. Update both this file and `app/docs/briefs/rbac_implementation_brief.md` whenever rules change. Module teams must add their RBAC annex entries before merging features.***
