# Implementation Plan: [FEATURE]

**Branch**: `[###-feature-name]` | **Date**: [DATE] | **Spec**: [link]
**Input**: Feature specification from `/specs/[###-feature-name]/spec.md`

**Note**: This template is filled in by the `/speckit.plan` command. See `.specify/templates/commands/plan.md` for the execution workflow.

## Summary

[Extract from feature spec: primary requirement + technical approach from research]

## Technical Context

<!--
  ACTION REQUIRED: Replace the content in this section with the technical details
  for the project. The structure here is presented in advisory capacity to guide
  the iteration process.
-->

**Language/Version**: PHP 8.2 (Laravel 12) — note deviations with owner approval  
**Primary Dependencies**: Vue 3 + Inertia.js v2, PrimeVue 4.3.9, Tailwind CSS, Spatie Permission  
**Storage**: PostgreSQL 16 with Row Level Security  
**Testing**: PHPUnit, Playwright, CLI probes (`tools/cli_suite.py`)  
**Target Platform**: Linux containers on PostgreSQL-backed infrastructure
**Project Type**: Web application (`stack/` Laravel workspace)  
**Performance Goals**: Document p95 latency, concurrency, and throughput targets per feature  
**Constraints**: Enforce tenancy, RBAC, audit logging, and idempotency for every write flow  
**Scale/Scope**: Multi-tenant SME accounting workloads; specify tenant counts and data volume assumptions

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

**Core Principles (Haasib Constitution v2.2.0)**
- [ ] I. Single Source Doctrine — cite canonical docs and update `docs/TEAM_MEMORY.md` for any deviations
- [ ] II. Command-Bus Supremacy — confirm write flows route through registered actions in `stack/config/command-bus.php`
- [ ] III. CLI–GUI Parity — document CLI command(s), natural language aliases, and output formats
- [ ] IV. Tenancy & RLS Safety — ensure `company_id` + `app.current_company_id` policies and onboarding scripts are accounted for
- [ ] V. RBAC Integrity — list permissions, roles, and negative tests
- [ ] VI. Translation & Accessibility — capture locale updates, ARIA coverage, and keyboard interactions
- [ ] VII. PrimeVue v4 & FontAwesome 5 Compliance — verify component usage and icon sourcing
- [ ] VIII. Module Governance — identify module locations, contracts, and boundaries
- [ ] IX. Tests Before Triumph — outline fail-first tests (unit, feature, CLI, contract) for the change
- [ ] X. Audit, Idempotency & Observability — specify audit events, idempotency keys, and telemetry updates

**Architecture Guardrails**
- [ ] Stack alignment — PHP 8.2/Laravel 12, Vue 3 + Inertia v2, PrimeVue 4.3.9, PostgreSQL 16 (note/justify exceptions)
- [ ] Directory discipline — new work lives in `stack/` (modules + app orchestration); `/app` remains untouched
- [ ] Command bus registry — planned action updates include registry + documentation adjustments
- [ ] Tenancy infrastructure — onboarding scripts, roles, and RLS configs accounted for in migrations
- [ ] Observability — logging, probes, dashboards updated with new signals

**Workflow & Documentation Gates**
- [ ] Spec → Plan → Tasks order respected; exceptions logged in `docs/TEAM_MEMORY.md`
- [ ] Constitution Check re-run after design artifacts are produced
- [ ] Required docs updated (spec, plan, tasks, briefs, release notes)
- [ ] TODOs for deferred translation/tests/telemetry include owner + deadline
- [ ] Compliance evidence captured for quarterly review

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
