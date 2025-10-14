<!--
Sync Impact Report
Version: 2.1.1 → 2.2.0
Modified Principles:
- Single Source Doctrine → Single Source Doctrine (clarified canonical sources and exception logging)
- Command-Bus Supremacy → Command-Bus Supremacy (aligned CLI entrypoints with action contracts)
- CLI–GUI Parity → CLI–GUI Parity (explicit parity gating and format requirements)
- Tenancy & RLS Safety → Tenancy & RLS Safety (documented app.current_company_id enforcement)
- RBAC Integrity → RBAC Integrity (mandatory permission mapping + negative tests)
- Translation & Accessibility → Translation & Accessibility (baseline locale coverage and accessibility hooks)
- PrimeVue v4 & FontAwesome 5 Compliance → PrimeVue v4 & FontAwesome 5 Compliance (Tailwind-only styling guardrail)
- Module Governance → Module Governance (stack/ module boundary reaffirmed)
- Tests Before Triumph → Tests Before Triumph (fail-first enforcement clarified)
- Audit, Idempotency & Observability → Audit, Idempotency & Observability (telemetry scope expanded)
Added Sections:
- Architecture Guardrails
- Workflow & Documentation Gates
Removed Sections:
- None
Templates:
- ✅ .specify/templates/plan-template.md
- ✅ .specify/templates/spec-template.md
- ✅ .specify/templates/tasks-template.md
Follow-ups:
- TODO(RATIFICATION_DATE): Original adoption date not recorded; request owner confirmation.
-->

# Haasib Constitution

## Core Principles

### I. Single Source Doctrine
- All solution design, code changes, and documentation MUST map to canonical artifacts in `docs/`, `.specify/memory/`, or approved specs and plans in `/specs`.
- Deviations or net-new patterns MUST be recorded in `docs/TEAM_MEMORY.md` with owner sign-off before implementation starts.
- Pull requests and research notes MUST cite the governing source (spec, plan, or constitution clause) they fulfill.

**Rationale**: A single authoritative knowledge base prevents drift between planning, docs, and production code.

### II. Command-Bus Supremacy
- All write operations (HTTP, CLI, jobs, background tasks) MUST dispatch registered actions defined in `stack/config/command-bus.php` through the command bus service.
- Controllers, services, and CLI commands MUST avoid inline persistence in favor of invoking application services that enqueue command bus actions.
- New actions MUST ship with idempotency guards and audit logging hooks before they are added to the registry.

**Rationale**: Centralized command handling keeps business logic consistent, auditable, and testable across interfaces.

### III. CLI–GUI Parity
- Every feature exposed in the Vue/Inertia interface MUST deliver an equivalent CLI capability via `stack/app/Console/Commands` or `stack/modules/*/CLI/Commands`.
- CLI commands MUST support natural-language aliases through the command palette parsers and provide machine-readable output (`--format=json`) for automation.
- Release sign-off MUST include documented parity validation in the feature spec and plan constitution checks.

**Rationale**: Parity guarantees that automation, support, and power users share the exact operational surface area as the GUI.

### IV. Tenancy & RLS Safety
- Tenant-owned tables MUST carry `company_id` columns with Row Level Security enforced via the `app.current_company_id` setting.
- Runtime context resolution MUST flow through `App\Services\ContextService`; direct session or global overrides are prohibited.
- Database migrations MUST enable RLS policies and verify `set_config('app.current_company_id', ...)` usage in seeds and tests.

**Rationale**: Strong tenancy controls prevent cross-company access in a multi-tenant accounting system.

### V. RBAC Integrity
- All endpoints, commands, and jobs MUST guard execution with explicit Spatie permission checks declared in seeders and policies.
- Feature specs MUST enumerate required permissions and update `config/permission.php` (or module-specific seeds) before implementation begins.
- Negative authorization tests MUST exist for each new permission path before merge approval.

**Rationale**: Least-privilege enforcement across every surface eliminates privilege escalation vectors.

### VI. Translation & Accessibility
- User-facing strings MUST live in `stack/resources/lang/<locale>` with `en` as canonical and `ar` kept in lockstep; missing keys require TODO flags in the spec.
- Vue components MUST ship with semantic labelling and ARIA attributes, and interactive elements MUST remain operable by keyboard alone.
- CLI output MUST honor locale selection and provide plain-text fallbacks suitable for screen readers.

**Rationale**: Inclusive design keeps the product usable for every locale and accessibility requirement the business supports.

### VII. PrimeVue v4 & FontAwesome 5 Compliance
- Frontend code MUST exclusively use PrimeVue v4 components/services; custom UI augments through Tailwind utility classes and component slots only.
- Iconography MUST draw from FontAwesome 5 bundles registered globally—no ad-hoc SVG imports or mismatched icon sets.
- New UI work MUST cross-check component availability against `docs/primevue-inventory.md` before introducing imports.

**Rationale**: A consistent UI stack simplifies theming, accessibility, and long-term maintenance.

### VIII. Module Governance
- Domain logic MUST live inside `stack/modules/<Domain>`; `stack/app` remains a thin orchestration layer limited to cross-module glue.
- Cross-module dependencies MUST be declared via contracts or facades with documentation in the relevant module README before use.
- Each module MUST own its entrypoints (routes, jobs, CLI commands) and tests under the module directory to stay independently deployable.

**Rationale**: Bounded contexts keep features scalable, composable, and testable in isolation.

### IX. Tests Before Triumph
- Contributors MUST author failing automated tests (unit, feature, CLI, contract, or policy) before implementing production code for any change.
- Test suites MUST cover tenancy, RBAC, audit events, and CLI pathways; merge gates fail if coverage or critical assertions regress.
- CI pipelines MUST run PHPUnit, CLI probes (`tools/cli_suite.py`), and Playwright smoke suites before release tags or production deploys.

**Rationale**: TDD enforces design clarity and protects financial workflows from regression.

### X. Audit, Idempotency & Observability
- All mutating flows MUST emit audit entries via the shared auditing utilities and persist idempotency tokens to prevent duplicate writes.
- Critical commands and HTTP endpoints MUST produce structured logs (JSON) carrying correlation IDs, actor identity, and tenant context.
- Operations dashboards MUST track command bus throughput, queue failures, and audit anomalies; incidents trigger documented follow-up in `docs/TEAM_MEMORY.md`.

**Rationale**: Finance systems demand verifiable history and actionable telemetry to maintain trust.

## Architecture Guardrails

- **Runtime Stack**: PHP 8.2+, Laravel 12, Vue 3 with Inertia.js v2, PrimeVue 4.3.9, Tailwind CSS, and PostgreSQL 16 are the approved baseline; deviations require architectural review and constitution addendum.
- **Directory Canon**: `stack/` is the active Laravel workspace (app orchestration + modules); `/app` is legacy and MUST remain read-only for historical reference.
- **Command Bus Registry**: `stack/config/command-bus.php` plus `stack/app/Services/CommandRegistryService.php` form the authoritative action index; update both alongside documentation when registering new actions.
- **Multi-Tenancy Infrastructure**: Provision `app_owner`/`app_user` roles, enforce `pg_stat_statements`, and configure RLS in migrations; onboarding scripts MUST set `app.current_company_id` before hitting tenant data.
- **Observability Tooling**: Logging and monitoring integrate with Laravel logging config, CLI probes in `tools/cli_probe.py`, GUI checks in `tools/gui_suite.py`, and audit tables; expand telemetry only by extending these pipelines.

## Workflow & Documentation Gates

- Follow the phase order: `/specs/.../spec.md` → `/specs/.../plan.md` → `/specs/.../tasks.md` → implementation → validation; skipping a phase requires constitution exemption recorded in `docs/TEAM_MEMORY.md`.
- The Constitution Check in `plan.md` MUST be updated before Phase 0 research and re-validated after Phase 1 design, explicitly documenting compliance or approved exceptions.
- Feature specs MUST enumerate CLI parity plans, tenancy/RBAC implications, translation coverage, and audit expectations before development starts.
- Completed work MUST refresh `docs/TEAM_MEMORY.md`, relevant briefs, and release notes to keep Single Source Doctrine intact.
- Tests, telemetry dashboards, and migration docs MUST be updated in the same PR as functional changes; deferred items require TODO annotations with owners and deadlines.

## Governance

The Haasib Constitution is binding for every contributor, reviewer, and automation workflow. Violations block merge until remediated or an explicit exception is ratified by the project owner.

- **Amendment Procedure**: Propose changes via PR updating this file plus impacted templates/docs; include a compliance impact note and secure owner approval before merge.
- **Versioning Policy**: Follow semantic versioning—MAJOR for breaking principle shifts, MINOR for new principles or guardrails, PATCH for clarifications. Record version updates in the Sync Impact Report and in downstream templates.
- **Compliance Reviews**: Conduct quarterly audits (minimum) covering command bus usage, CLI parity, tenancy policies, RBAC tests, translation coverage, and audit telemetry; log findings in `docs/constitutional-compliance-validation.md`.
- **Release Gate**: No feature ships without passing CI, Constitution Check, and documented CLI/GUI parity; release notes MUST cite the constitution version in force.
- **Record Keeping**: Update `docs/TEAM_MEMORY.md` and relevant specs with amendment references, and archive superseded versions in repository history for traceability.

**Version**: 2.2.0 | **Ratified**: TODO(RATIFICATION_DATE): Original adoption date not recorded; request owner confirmation. | **Last Amended**: 2025-10-14
