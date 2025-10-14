# Implementation Plan: Payment Processing - Receipt & Allocation

**Branch**: `005-payment-processing-receipt` | **Date**: 2025-10-14 | **Spec**: specs/005-payment-processing-receipt/spec.md
**Input**: Feature specification from `/specs/005-payment-processing-receipt/spec.md`

**Note**: This template is filled in by the `/speckit.plan` command. See `.specify/templates/commands/plan.md` for the execution workflow.

## Summary

Create end-to-end payment receipt handling so accountants can record customer payments, allocate amounts across invoices (manual or automated strategies), surface real-time balances/aging, manage reversals, and produce receipts/audit artifacts aligned with FR-001–FR-014 from the spec.

## Technical Context

**Language/Version**: PHP 8.2 / Laravel 12 back-end within `stack/`, Vue 3 + Inertia.js v2 front-end, PrimeVue 4.3.9 UI; no deviations planned.  
**Primary Dependencies**: `Modules\Accounting` domain/services (e.g., `Services/PaymentService.php`, `Domain/Payments`), shared `App\Services\PaymentAllocationService`, new payment command-bus actions to register in `stack/config/command-bus.php`, and Inertia pages under `stack/resources/js/Pages/Invoicing`.  
**Storage**: PostgreSQL 16 schemas `invoicing.payments`, `invoicing.payment_allocations`, plus new `invoicing.payment_receipt_batches` for FR-011; all tables keyed by `company_id` with RLS + audit triggers and ledger postings via `ledger.journal_entries`.  
**Testing**: PHPUnit suites under `stack/modules/Accounting/Tests` (new fail-first unit/feature coverage), CLI probes in `tools/cli_suite.py`, and forthcoming Playwright scenarios for receipt allocation parity.  
**Target Platform**: Linux containers deployed with existing multi-tenant config; CLI usage through `artisan` (ensure `--format=json` parity).  
**Project Type**: Multi-tenant accounting web app; all new domain logic should live in `stack/modules/Accounting/Domain/Payments` with adapters in `Http/`, `CLI/`, and Inertia resources.  
**Performance Goals**: Target p95 receipt creation latency <2s and allocation completion <3s with <1% error rate, aligning with `docs/ServiceContext-Metrics.md`.  
**Constraints**: Enforce tenancy scoping (`company_id`, `app.current_company_id`), RBAC via Spatie permissions (`accounting.payments.*` plus new allocate/reverse), full audit logging & idempotency tokens for every write, and explicit CLI↔GUI parity for manual/automatic allocation flows.  
**Scale/Scope**: Design for ~250 receipts/day/tenant with up to 10 allocations each and <=5 batches/hour, supporting concurrency via optimistic locks and background batch ingestion.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

**Core Principles (Haasib Constitution v2.2.0)**
- [x] I. Single Source Doctrine — Plan references `docs/api-allocation-guide.md` with updates scheduled for `docs/TEAM_MEMORY.md` and OpenAPI contracts.
- [x] II. Command-Bus Supremacy — New payment actions will be registered and all surfaces dispatch via the bus (`stack/config/command-bus.php`).
- [x] III. CLI–GUI Parity — Consolidate CLI commands with GUI flows using shared actions, documenting aliases and JSON output in quickstart.
- [x] IV. Tenancy & RLS Safety — All migrations use `company_id` foreign keys with RLS, and services rely on `ContextService` to set `app.current_company_id`.
- [x] V. RBAC Integrity — Permissions extend `accounting.payments.*` with allocate/reverse scopes and include negative test coverage.
- [x] VI. Translation & Accessibility — New Vue screens will use vue-i18n namespaces and PrimeVue components with ARIA annotations per guides.
- [x] VII. PrimeVue v4 & FontAwesome 5 Compliance — UI stack limited to PrimeVue 4.3.9 inventory and FontAwesome 5 icons.
- [x] VIII. Module Governance — Payment logic lives in `Modules\Accounting` with legacy `App\Services` refactored or wrapped; `/app` stays orchestration-only.
- [x] IX. Tests Before Triumph — Fail-first PHPUnit, CLI, and Playwright scenarios defined in testing strategy (see research).
- [x] X. Audit, Idempotency & Observability — Structured audit events, idempotency middleware, and metrics counters planned per monitoring guide.

**Architecture Guardrails**
- [x] Stack alignment — No non-standard SDKs required; payment gateways handled via existing Laravel stack.
- [x] Directory discipline — Domain logic consolidated under `stack/modules/Accounting`; only orchestration stays in `/app`.
- [x] Command bus registry — Action map updates planned alongside module registry docs.
- [x] Tenancy infrastructure — Migrations extend RLS, and onboarding seeds include new payment permissions.
- [x] Observability — Metrics and logs defined for dashboard integration per monitoring guide.

**Workflow & Documentation Gates**
- [x] Spec → Plan → Tasks order respected; plan work follows spec 005.
- [x] Constitution Check re-run after design artifacts are produced.
- [x] Required docs updated (team memory, allocation guide, OpenAPI) during design delivery.
- [x] TODOs for deferred translation/tests/telemetry will capture owners/deadlines in tasks.md.
- [x] Compliance evidence captured for quarterly review via `docs/TEAM_MEMORY.md` entry.

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
├── modules/
│   └── Accounting/
│       ├── Domain/Payments/ (Actions, ValueObjects, Events) — populate with receipt/allocation domain logic
│       ├── Http/Controllers/PaymentController.php
│       ├── CLI/Commands/PaymentRecord.php
│       ├── Services/PaymentService.php
│       ├── Resources/js/Payments (planned Inertia pages for receipt workflow)
│       └── Tests/{Feature,Unit} (expand with fail-first coverage)
├── app/
│   ├── Models/Payment.php
│   ├── Models/PaymentAllocation.php
│   ├── Services/PaymentAllocationService.php (candidate for module migration)
│   └── Console/Commands/PaymentAllocate.php & related CLI parity commands
├── resources/js/Pages/Invoicing (existing customer invoicing UI; extend for payments)
└── tests/Playwright (add scenarios for receipt allocation parity)
```

**Structure Decision**: Payment receipt work belongs in `stack/modules/Accounting` with supporting updates to shared `stack/app` services/models until migrated; front-end continues under `stack/resources/js/Pages/Invoicing` aligning with existing Inertia layout.

## Complexity Tracking

*Fill ONLY if Constitution Check has violations that must be justified*

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| [e.g., 4th project] | [current need] | [why 3 projects insufficient] |
| [e.g., Repository pattern] | [specific problem] | [why direct DB access insufficient] |
