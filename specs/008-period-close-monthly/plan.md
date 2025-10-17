# Implementation Plan: Period Close - Monthly Closing Process

**Branch**: `008-period-close-monthly` | **Date**: 2025-10-16 | **Spec**: `specs/008-period-close-monthly/spec.md`
**Input**: Feature specification from `/specs/008-period-close-monthly/spec.md`

**Note**: This template is filled in by the `/speckit.plan` command. See `.specify/templates/commands/plan.md` for the execution workflow.

## Summary

Implement a monthly period closing workflow that validates balances and open items via a new `PeriodCloseService`, orchestrates close/reopen actions through the command bus, and delivers checklist-driven Inertia/PrimeVue pages with auditable ledger events and RLS-safe storage.

## Technical Context

<!--
  ACTION REQUIRED: Replace the content in this section with the technical details
  for the project. The structure here is presented in advisory capacity to guide
  the iteration process.
-->

**Language/Version**: PHP 8.2 (Laravel 12) and TypeScript/Vue 3  
**Primary Dependencies**: Laravel framework, Inertia.js v2, PrimeVue 4.3.9, Tailwind CSS, Spatie Permission, command-bus infrastructure, Modules\Ledger services  
**Storage**: PostgreSQL 16 (ledger + accounting schemas)  
**Testing**: Pest (PHPUnit) for backend unit/feature tests; Playwright for Inertia UI flows  
**Target Platform**: Web (Laravel monolith deployed on Linux)  
**Project Type**: Full-stack monolith under `stack/` (Laravel backend with Vue SPA)  
**Performance Goals**: Close validations p95 < 2s; full close/reopen cycle < 5 minutes for periods up to 10k journal lines  
**Constraints**: Enforce closed-period locking, RLS company scoping, audit logging via `audit_log()`, double-entry validation before close  
**Scale/Scope**: Controllers and senior accountants (with `accounting_periods.close`) supporting ~50 concurrent tenant companies and 60 historical periods with multi-user review

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- Multi-schema domain separation: monthly close work must live in `ledger` (core) and reference `acct` data via services where required.
- Security-first bookkeeping: period close mutations require RLS, `company_id` scoping, audit coverage, and safeguards for double-entry balances.
- Test & review discipline: migrations, command-bus handlers, and services need regression coverage; document RLS/audit implications.
- Observability & traceability: capture close/reopen activity with `audit_log()` and surface metrics via monitoring playbooks.
- No violations identified; proceed while ensuring all gates remain satisfied post-design.
- Post-design re-check: Data model keeps new tables in `ledger` schema with RLS, contracts route through command bus, and audit logging is captured through `audit_log()` events—gates remain satisfied.

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
├── app/Http/Controllers/            # Laravel controllers (Inertia endpoints)
├── modules/Ledger/                  # Ledger domain services, actions, period logic
├── modules/Accounting/              # Upstream AR/AP data used during close validations
├── resources/js/Pages/              # Vue 3 + Inertia pages (PrimeVue UI)
├── database/migrations/             # PostgreSQL 16 migrations (ledger, acct schemas)
└── config/                          # Command-bus registrations, permissions, close policies

tests/
├── Feature/                         # Laravel HTTP/CLI feature tests for close workflows
├── Unit/                            # Domain service coverage (period status, validations)
└── Browser/                         # Playwright flows for monthly close UI (if required)
```

**Structure Decision**: Extend existing Laravel monolith within `stack/`, focusing on `modules/Ledger` for period close orchestration, Inertia pages under `stack/resources/js/Pages`, and corresponding coverage in `tests/Feature` and `tests/Unit`.

## Complexity Tracking

*Fill ONLY if Constitution Check has violations that must be justified*

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| [e.g., 4th project] | [current need] | [why 3 projects insufficient] |
| [e.g., Repository pattern] | [specific problem] | [why direct DB access insufficient] |
