# Implementation Plan: Bank Reconciliation - Statement Matching

**Branch**: `009-bank-reconciliation-statement` | **Date**: 2025-10-17 | **Spec**: `specs/009-bank-reconciliation-statement/spec.md`
**Input**: Feature specification from `/specs/009-bank-reconciliation-statement/spec.md`

**Note**: This template is filled in by the `/speckit.plan` command. See `.specify/templates/commands/plan.md` for the execution workflow.

## Summary

Accountants need to import bank statements, match them against internal ledger activity, surface discrepancies, and lock reconciliations once balances agree. Implementation will stage statement files in new `ops.bank_*` tables, drive ingestion/matching through command-bus actions, and deliver a PrimeVue-powered reconciliation workspace with audit coverage.

## Technical Context

<!--
  ACTION REQUIRED: Replace the content in this section with the technical details
  for the project. The structure here is presented in advisory capacity to guide
  the iteration process.
-->

**Language/Version**: PHP 8.2 (Laravel 12), TypeScript/Vue 3 via Inertia.js v2  
**Primary Dependencies**: Laravel command bus, Inertia.js, PrimeVue 4.3.9, Tailwind CSS, Spatie Permission  
**Storage**: PostgreSQL 16 (new `ops.bank_statements`/`ops.bank_statement_lines` staging tables referencing `ledger` transactions)  
**Testing**: Pest (`php artisan test`)  
**Target Platform**: Web (Laravel backend with Vue 3 SPA shell)
**Project Type**: Monolithic web app under `stack/` (backend + SPA)  
**Performance Goals**: Import ≤2k statement lines in <5s; auto-match batches in <2s  
**Constraints**: Idempotent imports, 10 MB file cap, duplicate detection, currency-aware reconciliation  
**Scale/Scope**: Up to 10 active bank accounts per company with monthly reconciliation cadence

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

- **G1 – Multi-Schema Domain Separation**: Stage statements in `ops` schema while linking to `ledger` entities; no new schema required. Status: OK.
- **G2 – Security-First Bookkeeping**: All reconciliation mutations require `company_id` scoping, RLS policies, audit coverage, and safe migration patterns. Status: OK (design will adhere; verify during design).
- **G3 – Test & Review Discipline**: New migrations/handlers need regression coverage (Pest feature/unit tests). Status: OK.
- **G4 – Observability & Traceability**: Use `audit_log()` plus `bank.reconciliation` broadcast events for every reconciliation milestone. Status: OK.

*Re-evaluated after Phase 1 design: PASS*

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
│   ├── Models/
│   ├── Http/Controllers/
│   ├── Services/
│   └── Console/Commands/
├── modules/
│   ├── Accounting/
│   └── Ledger/
├── resources/js/
│   ├── Layouts/
│   ├── Pages/
│   └── Services/
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
└── tests/
    ├── Feature/
    └── Unit/

specs/009-bank-reconciliation-statement/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
└── contracts/
```

**Structure Decision**: Continue within the `stack/` Laravel monolith, adding reconciliation domain logic under existing modules (`Modules/Ledger` or new `Modules/Ops` components) plus SPA pages under `stack/resources/js`.

## Complexity Tracking

*Fill ONLY if Constitution Check has violations that must be justified*

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| _None_ | – | – |
