# Implementation Plan: Reporting Dashboard - Financial & KPI

**Branch**: `010-reporting-dashboard-financial` | **Date**: 2025-10-17 | **Spec**: `specs/010-reporting-dashboard-financial/spec.md`
**Input**: Feature specification from `/specs/010-reporting-dashboard-financial/spec.md`

**Note**: This template is filled in by the `/speckit.plan` command. See `.specify/templates/commands/plan.md` for the execution workflow.

## Summary

Deliver a real-time financial reporting experience for Owner, Accountant, and Viewer roles with dashboards, statements, trial balance, KPI trends, and exports that meet <5 second freshness and <10 second generation targets by combining PrimeVue dashboards, `rpt` schema materialized snapshots, and 5-second cache refresh cycles orchestrated through the command bus.

## Technical Context

<!--
  ACTION REQUIRED: Replace the content in this section with the technical details
  for the project. The structure here is presented in advisory capacity to guide
  the iteration process.
-->

**Language/Version**: PHP 8.2 (Laravel 12), TypeScript (Vue 3 + Inertia.js v2)  
**Primary Dependencies**: PrimeVue 4.3.9 (Chart.js), Tailwind CSS, Laravel command bus, Spatie Permission  
**Storage**: PostgreSQL 16 across `ledger`, `acct`, `ops`, plus `rpt` reporting schema with materialized snapshots and short-lived cache store  
**Testing**: PestPHP 4 (feature + module tests)  
**Target Platform**: Web SPA served via Laravel + Vite  
**Project Type**: Full-stack web application (Laravel backend + Inertia SPA frontend)  
**Performance Goals**: Metrics freshness <5s; complex report generation <10s; exports within 10s  
**Constraints**: Enforce RLS & audit per Security-First Bookkeeping; 5s TTL cache for live metrics with scheduled snapshot refreshes  
**Scale/Scope**: Unlimited historical data retention; size for 100 dashboard viewers + ≥10 concurrent heavy report jobs

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **Gate 1 – Multi-Schema Domain Separation**: PASS. All new persistence lives in `rpt.*` with read-only access to `ledger`/`acct` via materialized views; no cross-schema writes planned.
- **Gate 2 – Security-First Bookkeeping**: PASS. Design enforces company-scoped RLS, Spatie permissions, and audit logging on report generation/export/schedule events.
- **Gate 3 – Test & Review Discipline**: PASS (Plan). Pest feature tests will cover dashboard API, report generation workflows, and schedule jobs; command bus handlers designed for unit isolation.
- **Gate 4 – Observability & Traceability**: PASS. Reporting commands emit `audit_log()` entries and integrate with monitoring playbooks; cache refresh jobs expose metrics for SLA tracking.

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
├── app/
│   ├── Http/Controllers/
│   ├── Services/
│   └── Models/
├── modules/
│   ├── Accounting/
│   ├── Ledger/
│   └── Reporting/              # planned module entry point for dashboards
├── resources/js/
│   ├── Pages/
│   │   ├── Ledger/
│   │   ├── Invoicing/
│   │   └── Reporting/          # planned Inertia flows for this feature
│   └── Components/
└── database/
    ├── migrations/
    └── seeders/

stack/tests/
├── Feature/
└── Unit/
```

**Structure Decision**: Full-stack Laravel + Inertia SPA. Backend reporting services/controllers live under `stack/modules/Reporting` and `stack/app/Http/Controllers/Reporting`, while dashboards and flows are added to `stack/resources/js/Pages/Reporting`; regression coverage resides in `stack/tests/Feature` and `stack/tests/Unit`.

## Complexity Tracking

*Fill ONLY if Constitution Check has violations that must be justified*

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| [e.g., 4th project] | [current need] | [why 3 projects insufficient] |
| [e.g., Repository pattern] | [specific problem] | [why direct DB access insufficient] |
