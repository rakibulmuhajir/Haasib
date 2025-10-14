# Implementation Plan: [FEATURE]

**Branch**: `[###-feature-name]` | **Date**: [DATE] | **Spec**: [link]
**Input**: Feature specification from `/specs/[###-feature-name]/spec.md`

**Note**: This template is filled in by the `/speckit.plan` command. See `.specify/templates/commands/plan.md` for the execution workflow.

## Summary

[Extract from feature spec: primary requirement + technical approach from research]

## Technical Context

**Language/Version**: PHP 8.2+, Laravel 12, Vue 3 with Inertia.js v2, PrimeVue v4, Tailwind CSS
**Primary Dependencies**: Laravel 12, Vue 3, Inertia.js v2, PrimeVue v4, Tailwind CSS, PostgreSQL 16
**Storage**: PostgreSQL 16 (multi-schema: auth, public, hrm, acct)
**Testing**: Pest v4 for backend, Playwright for E2E
**Target Platform**: Web application
**Project Type**: Web application (frontend + backend)
**Performance Goals**: <200ms p95 response times
**Constraints**: Tenancy & RLS Safety, RBAC Integrity, CLI–GUI Parity, Command-Bus Supremacy, Audit & Idempotency
**Scale/Scope**: NEEDS CLARIFICATION

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

**Relevant Gates from Constitution:**

- **III. CLI–GUI Parity**: Every GUI capability must have an equal CLI path. Keep palette metadata and parser fragments in sync with command-bus actions, and test them together. CLI commands use unique names (no module prefixes) and support natural language interaction.
  - **Evaluation**: Feature directly implements CLI access to GUI actions via command palette. No violation.

- **II. Command-Bus Supremacy**: All write flows must dispatch registered command actions (app/config/command-bus.php). Controllers, CLI, future clients share these actions. No direct service mutations without command registration. Every mutation MUST be traceable through the command bus.
  - **Evaluation**: Command palette will execute commands through the command bus. No violation.

- **V. RBAC Integrity**: Respect the seeded role/permission catalog. No hidden routes, no privilege creep. Every new feature declares precise permissions and tests allow/deny behavior. All endpoints MUST have permission guards.
  - **Evaluation**: Feature must filter commands based on user permissions (FR-010). No violation.

- **X. Audit, Idempotency & Observability**: All write actions log via the audit system and enforce idempotency keys. Expose structured errors, maintain logs/traces, and never silence failures. Every financial mutation MUST be auditable and idempotent.
  - **Evaluation**: Command execution must provide audit log references (FR-008). No violation.

- **IV. Tenancy & RLS Safety**: Every tenant record carries company_id. Enforce RLS and policies, never weaken isolation or bypass permissions. Direct database access bypassing safety checks is prohibited. All queries MUST include tenant scoping.
  - **Evaluation**: Must maintain company context in command execution (FR-013). No violation.

- **IX. Tests Before Triumph**: Add or update unit, feature, CLI, and RLS tests to cover each change. Never mark a feature complete without verifying automation and documenting manual QA. TDD is mandatory: failing tests written first.
  - **Evaluation**: Implementation must follow TDD. No violation.

- **VI. Translation & Accessibility**: All user-facing strings live in locale files (EN + AR baseline). Preserve accessibility cues (ARIA labels, focus states) and confirm right-to-left rendering where applicable. Hard-coded strings in views are prohibited.
  - **Evaluation**: User-facing strings in command palette must be localized. No violation.

- **VII. PrimeVue v4 & FontAwesome 5 Compliance**: Build UI with PrimeVue v4 components, synchronized light/dark themes, and the FontAwesome 5 icon set described in docs. Mixing component libraries is prohibited. Custom CSS only through Tailwind utilities.
  - **Evaluation**: Command palette UI must use PrimeVue v4. No violation.

**Violations Identified**: None

## Post-Phase 1 Design Constitution Check

*Re-evaluated after completing data model and API contracts*

**Relevant Gates from Constitution:**

- **III. CLI–GUI Parity**: Data model and API contracts ensure command palette provides equal access to GUI actions. Command execution routes through command bus maintaining parity.
  - **Evaluation**: Still compliant. API contracts specify command bus integration.

- **II. Command-Bus Supremacy**: API contracts explicitly state all executions route through the command bus. No direct service mutations.
  - **Evaluation**: Still compliant. Execute command contract references command bus.

- **V. RBAC Integrity**: Data model includes permission filtering. API contracts specify permission validation before execution.
  - **Evaluation**: Still compliant. Commands table includes required_permissions.

- **X. Audit, Idempotency & Observability**: Data model includes audit references. API contracts specify audit logging and idempotency keys.
  - **Evaluation**: Still compliant. Execution tracking includes audit links.

- **IV. Tenancy & RLS Safety**: All entities include company_id. API contracts specify company context headers.
  - **Evaluation**: Still compliant. Tenancy enforced in all tables and endpoints.

- **IX. Tests Before Triumph**: Implementation will follow TDD as specified in plan.
  - **Evaluation**: Still compliant. No change in approach.

- **VI. Translation & Accessibility**: User-facing strings in API responses will be localized.
  - **Evaluation**: Still compliant. Quickstart mentions localization.

- **VII. PrimeVue v4 & FontAwesome 5 Compliance**: UI components will use PrimeVue v4.
  - **Evaluation**: Still compliant. No backend API changes affect this.

**Post-Design Violations Identified**: None

**Conclusion**: Phase 1 design artifacts (data model, API contracts, quickstart) maintain full compliance with Constitution requirements. Ready to proceed to Phase 2 implementation planning.

## Project Structure

### Documentation (this feature)

```
specs/[###-feature]/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output (/speckit.plan command)
├── data-model.md        # Phase 1 output (/speckit.plan command)
├── quickstart.md        # Phase 1 output (/speckit.plan command)
├── contracts/           # Phase 1 output (/speckit.plan command)
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)
<!--
  ACTION REQUIRED: Replace the placeholder tree below with the concrete layout
  for this feature. Delete unused options and expand the chosen structure with
  real paths (e.g., apps/admin, packages/something). The delivered plan must
  not include Option labels.
-->

```
# [REMOVE IF UNUSED] Option 1: Single project (DEFAULT)
src/
├── models/
├── services/
├── cli/
└── lib/

tests/
├── contract/
├── integration/
└── unit/

# [REMOVE IF UNUSED] Option 2: Web application (when "frontend" + "backend" detected)
backend/
├── src/
│   ├── models/
│   ├── services/
│   └── api/
└── tests/

frontend/
├── src/
│   ├── components/
│   ├── pages/
│   └── services/
└── tests/

# [REMOVE IF UNUSED] Option 3: Mobile + API (when "iOS/Android" detected)
api/
└── [same as backend above]

ios/ or android/
└── [platform-specific structure: feature modules, UI flows, platform tests]
```

**Structure Decision**: [Document the selected structure and reference the real
directories captured above]

## Complexity Tracking

*Fill ONLY if Constitution Check has violations that must be justified*

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| [e.g., 4th project] | [current need] | [why 3 projects insufficient] |
| [e.g., Repository pattern] | [specific problem] | [why direct DB access insufficient] |
