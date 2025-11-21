# RBAC Implementation Brief (Workspace Edition)

> **Source of truth:** the full canonical brief still lives at `docs/briefs/rbac_implementation_brief.md`. This file provides a workspace-focused summary plus clarifications that surfaced in recent discussions.

## Dual-Tier Role Model

Haasib deliberately separates **system roles** from **company roles**:

- **System roles** (`super_admin`, `system_admin`, `system_manager`) operate outside of any tenant context. They provision companies, manage modules, seed permissions, and step into tenant data only through audited impersonation flows. Treat these as global Spatie roles that rarely expand and always require audit logging.
- **Company roles** are bound to a specific `company_id` context and must pass RLS validation through helpers such as `$this->validateRlsContext()`. The default hierarchy remains `company_admin`, `accounting_manager`, `accounting_clerk`, and `viewer`, but you can alias business terms (Owner, Manager, Administrator, Accountant, Customer, Vendor, Spectator) to these bundles as needed per tenant.

## Module-Defined Roles

Modules can introduce their own permission namespaces and role bundles, provided they follow the same naming conventions (`{module}.{resource}.{action}`) and register them through the shared PermissionSeeder/command-bus workflows.

- Example: An educational module might add `teacher`, `student`, and `parent` roles, each wired to permissions such as `edu.classes.grade`, `edu.assignments.submit`, or `edu.portal.view`.
- Scope each module role explicitly:
  - `scope: system` if the role manages the module itself (e.g., `education_module_admin` who onboards districts).
  - `scope: company` when the role lives inside a tenant (e.g., school-specific teachers or parents). These roles must still validate RLS context.
- Registration steps:
  1. Define permission constants inside the module (or extend `App\Constants\Permissions` if shared).
  2. Extend the seeder to publish the new permissions/roles.
  3. Document the role bundles under the module’s brief so implementers know which permissions to assign.

## Implementation Guidance

- Always reference the canonical brief for the authoritative checklist (FormRequest helpers, RLS expectations, caching, testing).
- Keep the **system vs company** concern explicit in code and documentation so auditors understand when a role is allowed to bypass tenant boundaries.
- When modules add roles, ensure their permissions are surfaced in Inertia props so the frontend can conditionally show module UI without additional API round-trips.
