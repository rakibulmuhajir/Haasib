# Implementation Plan: Journal Entries - Manual & Automatic

**Branch**: `007-journal-entries-manual` | **Date**: 2025-10-15 | **Spec**: `specs/007-journal-entries-manual/spec.md`
**Input**: Feature specification from `/specs/007-journal-entries-manual/spec.md`

**Note**: This template is filled in by the `/speckit.plan` command. See `.specify/templates/commands/plan.md` for the execution workflow.

## Summary

Manual and automatic journal entry capabilities with traceable source documents, audit trails, and balancing safeguards built on the existing Accounting ledger services and command-bus actions, with Inertia/Vue interfaces for manual workflows.

## Technical Context

<!--
  ACTION REQUIRED: Replace the content in this section with the technical details
  for the project. The structure here is presented in advisory capacity to guide
  the iteration process.
-->

**Language/Version**: PHP 8.2 (Laravel 12) and TypeScript/Vue 3  
**Primary Dependencies**: Laravel framework, Inertia.js v2, PrimeVue 4.3.9, Tailwind CSS, Spatie Permission  
**Storage**: PostgreSQL 16 (`invoicing` schema)  
**Testing**: Pest (PHPUnit runner) for Laravel Feature/Unit/CLI suites, Playwright for browser automation  
**Target Platform**: Web (Laravel application deployed on Linux)  
**Project Type**: Full-stack monolith (`stack/` Laravel backend + Vue SPA)  
**Performance Goals**: p95 journal entry create/post latency < 1.5s for ≤20 lines; support ≥10k journal lines/day per tenant  
**Constraints**: Closed-period enforcement, double-entry validation, source document traceability, audit trail integration  
**Scale/Scope**: Finance roles (ledger.postJournal) across ~50 concurrent tenants with manual/recurring/reversal coverage

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- Constitution file `.specify/memory/constitution.md` is an unratified template; defaulting to `AGENTS.md` and repository guidelines until governance updates land.
- No explicit gates are currently enforceable; proceed while documenting reliance on published development guidelines.
- Post-design check: Phase 1 artifacts align with documented guidelines; no additional gate actions available until constitution is ratified.

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
stack/
├── app/Http/Controllers/            # Laravel controllers (e.g., Invoicing, Accounting)
├── modules/Accounting/              # Domain modules (services, CLI, HTTP)
├── resources/js/Pages/              # Vue 3 + Inertia pages
├── database/migrations/             # Schema changes (PostgreSQL 16)
└── config/                          # Application configuration (command bus, permissions)

tests/
├── Feature/                         # Laravel feature tests (HTTP, CLI)
├── Unit/                            # Domain/service unit coverage
└── Browser/                         # End-to-end UI tests
```

**Structure Decision**: Feature will extend existing Laravel monolith under `stack/`, leveraging Accounting module services, Vue Inertia pages, and corresponding tests in `tests/Feature` and `tests/Unit`.

## Complexity Tracking

*Fill ONLY if Constitution Check has violations that must be justified*

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| [e.g., 4th project] | [current need] | [why 3 projects insufficient] |
| [e.g., Repository pattern] | [specific problem] | [why direct DB access insufficient] |
