---
description: "Task list for Payment Processing - Receipt & Allocation"
---

# Tasks: Payment Processing - Receipt & Allocation

**Input**: Design documents from `/specs/005-payment-processing-receipt/`
**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/

**Tests**: Tests are mandatory per Constitution Principle IX—author them first, ensure they fail, and only then proceed with implementation.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`
- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions
- **Backend**: `stack/` (Laravel application & modules)
- **Frontend**: `stack/resources/js/`
- **Tests**: `stack/modules/*/Tests/`, `tests/Console/`, `tests/Playwright/`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization and baseline documentation alignment

- [x] T001 [Shared] Add feature entry in `docs/TEAM_MEMORY.md` summarizing key decisions from `specs/005-payment-processing-receipt/plan.md` and research (command bus usage, new tables, metrics) to satisfy Single Source Doctrine.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [x] T002 [Foundation] Scaffold payments command-bus integration by creating `stack/modules/Accounting/Domain/Payments/Actions/registry.php`, updating `stack/modules/Accounting/Providers/AccountingServiceProvider.php`, and wiring stub `payment.*` routes in `stack/config/command-bus.php`.
- [x] T003 [Foundation] Extend Spatie permissions in `app/database/seeders/RbacSeeder.php` (and seeded roles) with `accounting.payments.allocate` + `accounting.payments.reverse`, adding doctrine notes in `docs/TEAM_MEMORY.md`.
- [x] T004 [Foundation] Establish telemetry scaffolding for payments by creating `stack/modules/Accounting/Domain/Payments/Telemetry/PaymentMetrics.php` (or similar) and registering counters in `stack/app/Providers/AppServiceProvider.php` for payment created/allocated/failure events.

**Checkpoint**: Foundation ready — user story implementation can now begin

---

## Phase 3: User Story 1 - Record & Allocate Receipts (Priority: P1) 🎯 MVP

**Goal**: Allow accountants to record customer payments, allocate amounts (manual or automatic) to invoices, and view updated balances in real time (Spec primary story; FR-001, FR-002, FR-004, FR-006, FR-012, FR-013).

**Independent Test**: From a seeded tenant, create a payment via API/CLI/UI, allocate it across invoices, and verify invoice balances + remaining amount update without cross-tenant leakage.

### Tests for User Story 1 — write first, ensure they fail ⚠️

- [ ] T005 [P] [US1] Author fail-first feature tests in `stack/modules/Accounting/Tests/Feature/Payments/RecordPaymentTest.php` covering command-bus `payment.create` + manual allocations (`payment.allocate`) with RLS assertions.
- [ ] T006 [P] [US1] Add console parity tests in `tests/Console/PaymentAllocateCommandTest.php` ensuring JSON output and error handling for allocation CLI workflows.

### Implementation for User Story 1

- [ ] T007 [US1] Implement `RecordPaymentAction` and `AllocatePaymentAction` in `stack/modules/Accounting/Domain/Payments/Actions/` with idempotency + telemetry hooks, and register concrete classes in `stack/modules/Accounting/Domain/Payments/Actions/registry.php`.
- [ ] T008 [US1] Update Eloquent models `stack/app/Models/Payment.php` and `stack/app/Models/PaymentAllocation.php` to add batch relations, aggregated accessors (`total_allocated`, `remaining_amount`), and tenancy scopes matching `data-model.md`.
- [ ] T009 [US1] Refactor services `stack/app/Services/PaymentService.php` and `stack/app/Services/PaymentAllocationService.php` to delegate through command-bus actions, enforce validation rules, and emit audit/metric events.
- [ ] T010 [US1] Replace stub API controller with real implementation by creating `stack/modules/Accounting/Http/Controllers/Api/PaymentController.php`, updating `stack/routes/api.php` bindings, and serializing responses that match `contracts/payments.yaml` (`POST /payments`, `POST /payments/{id}/allocations`, `POST /payments/{id}/allocations/auto`, `GET /payments/{id}`, `GET /payments/{id}/allocations`).
- [ ] T011 [US1] Update CLI commands `stack/modules/Accounting/CLI/Commands/PaymentRecord.php` and `stack/app/Console/Commands/PaymentAllocate.php` to dispatch through the command bus, add `--format=json` parity, and surface allocation strategy options.
- [ ] T012 [US1] Build Inertia UI for payments: create `stack/resources/js/Pages/Invoicing/Payments/Index.vue` plus supporting components under `stack/resources/js/Components/Payments/` using PrimeVue dialog/datatable to record receipts and allocations.
- [ ] T013 [P] [US1] Add localization keys in `stack/resources/js/locales/en/payments.json` and `stack/resources/js/locales/ar/payments.json`, wiring components to vue-i18n with ARIA annotations.
- [ ] T014 [US1] Document CLI/API workflow updates in `specs/005-payment-processing-receipt/quickstart.md` section 1–3 to reflect new command options and endpoints.

**Checkpoint**: User Story 1 delivers manual & automatic receipt allocation across API/CLI/UI with updated balances and passing tests.

---

## Phase 4: User Story 2 - Overpayments & Receipts (Priority: P2)

**Goal**: Handle overpayments by tracking unallocated cash or credits, apply early-payment discounts, and generate customer receipts (FR-003, FR-005, FR-007).

**Independent Test**: Record a payment exceeding invoice totals, verify unallocated cash ledger + discount application, and retrieve a PDF/JSON receipt reflecting allocations.

### Tests for User Story 2 — write first, ensure they fail ⚠️

- [ ] T015 [P] [US2] Add feature tests in `stack/modules/Accounting/Tests/Feature/Payments/OverpaymentTest.php` covering discount calculation, unallocated cash creation, and receipt rendering.
- [ ] T016 [P] [US2] Create contract tests in `tests/Feature/Api/Payments/ReceiptEndpointTest.php` validating `/payments/{paymentId}/receipt` outputs for PDF + JSON.

### Implementation for User Story 2

- [ ] T017 [US2] Extend domain actions in `stack/modules/Accounting/Domain/Payments/Actions/AllocatePaymentAction.php` to calculate early-payment discounts and persist unallocated cash entries (e.g., view or helper) per `data-model.md`.
- [ ] T018 [US2] Implement receipt builder service `stack/modules/Accounting/Domain/Payments/Services/PaymentReceiptService.php` and wire `GET /payments/{paymentId}/receipt` in `stack/modules/Accounting/Http/Controllers/Api/PaymentController.php` to stream PDF/JSON responses.
- [ ] T019 [US2] Surface overpayment and receipt download UI in `stack/resources/js/Pages/Invoicing/Payments/Index.vue` (and child components) including credit indicators and localized download actions.
- [ ] T020 [P] [US2] Update CLI commands `PaymentRecord` and `PaymentAllocate` to output unallocated cash + receipt reference data, and add new command `payment:receipt` in `stack/app/Console/Commands/PaymentReceipt.php`.
- [ ] T021 [US2] Add documentation for discounts/unallocated cash handling in `docs/api-allocation-guide.md` and `specs/005-payment-processing-receipt/quickstart.md` section 4.

**Checkpoint**: User Story 2 supports overpayments, receipt generation, and updated documentation with passing tests.

---

## Phase 5: User Story 3 - Audit & Reporting (Priority: P2)

**Goal**: Provide full audit history for payments/allocations and deliver allocation reports for reviewers (FR-009, FR-010, FR-014).

**Independent Test**: Perform payment + allocation operations, retrieve `/payments/{paymentId}/audit` + allocation reports, and confirm entries include actor, company scope, and reconciliation metadata.

### Tests for User Story 3 — write first, ensure they fail ⚠️

- [ ] T022 [P] [US3] Create feature tests in `stack/modules/Accounting/Tests/Feature/Payments/AuditTrailTest.php` validating audit entries and bank reconciliation markers.
- [ ] T023 [P] [US3] Add CLI probe in `tests/Console/PaymentAllocationReportCommandTest.php` verifying report JSON output for `payment:allocation:report`.

### Implementation for User Story 3

- [ ] T024 [US3] Emit detailed audit events in domain actions (`RecordPaymentAction`, `AllocatePaymentAction`) annotating telemetry metadata and persisting via `stack/modules/Accounting/Domain/Payments/Events`.
- [ ] T025 [US3] Implement audit + report endpoints in `stack/modules/Accounting/Http/Controllers/Api/PaymentController.php` for `/payments/{paymentId}/audit` and `/payments/{paymentId}/allocations`, pulling data through dedicated query service `stack/modules/Accounting/Domain/Payments/Services/PaymentQueryService.php`.
- [ ] T026 [US3] Enhance CLI command `stack/app/Console/Commands/PaymentAllocationReport.php` to use new query service and support filtering for reconciliation.
- [ ] T027 [US3] Update UI reporting widgets in `stack/resources/js/Pages/Invoicing/Payments/Index.vue` (or child components) to display audit timeline and export options with accessibility support.
- [ ] T028 [US3] Refresh `docs/api-allocation-guide.md` and add monitoring playbook entries in `docs/ServiceContext-Monitoring.md` for new metrics/log fields.

**Checkpoint**: User Story 3 exposes auditable report surfaces across API/CLI/UI with documentation updated.

---

## Phase 6: User Story 4 - Reversals & Adjustments (Priority: P2)

**Goal**: Support reversing payments and allocations, including ledger adjustments and bank reconciliation hooks (FR-008, edge-case handling for bounced checks).

**Independent Test**: Reverse an allocation and whole payment, verify balances roll back, ledger entries adjust, and audit trail captures reversal reason.

### Tests for User Story 4 — write first, ensure they fail ⚠️

- [ ] T029 [P] [US4] Add fail-first tests in `stack/modules/Accounting/Tests/Feature/Payments/PaymentReversalTest.php` covering allocation reversal and payment reversal flows.
- [ ] T030 [P] [US4] Create API tests in `tests/Feature/Api/Payments/PaymentReversalEndpointTest.php` validating `/payments/{paymentId}` POST behaviour and idempotency.

### Implementation for User Story 4

- [ ] T031 [US4] Create migration in `stack/modules/Accounting/Database/Migrations/` for `invoicing.payment_reversals` (plus indexes/RLS) and update `stack/app/Models/Payment.php` relationships.
- [ ] T032 [US4] Implement `ReverseAllocationAction` and `ReversePaymentAction` domain classes, updating `stack/modules/Accounting/Domain/Payments/Actions/registry.php` and wiring audit/metrics for reversals.
- [ ] T033 [US4] Expose reversal endpoints in `stack/modules/Accounting/Http/Controllers/Api/PaymentController.php` (`POST /payments/{paymentId}/allocations/{allocationId}/reverse`, `POST /payments/{paymentId}`) with validation + ledger hooks.
- [ ] T034 [US4] Update CLI commands (`stack/app/Console/Commands/PaymentAllocationReverse.php`, new `payment:reverse`) to invoke reversal actions and support JSON output.
- [ ] T035 [US4] Surface reversal management in UI (`stack/resources/js/Pages/Invoicing/Payments/Index.vue`) with confirmation flows and localization for reversal reasons.
- [ ] T036 [US4] Integrate ledger adjustments by extending `stack/modules/Accounting/Services/LedgerService.php` (or appropriate domain service) to sync reversal journal entries.

**Checkpoint**: User Story 4 enables safe payment/allocation reversals with full ledger + audit integration.

---

## Phase 7: User Story 5 - Batch Processing (Priority: P3)

**Goal**: Support batch ingestion of payment receipts via CSV/bank feeds, tracking batch status and outcomes (FR-011, scale expectations).

**Independent Test**: Upload a CSV batch, monitor processing status, ensure payments created, failures recorded, and metrics captured.

### Tests for User Story 5 — write first, ensure they fail ⚠️

- [ ] T037 [P] [US5] Create feature tests in `stack/modules/Accounting/Tests/Feature/Payments/BatchProcessingTest.php` simulating CSV ingestion and failure handling.
- [ ] T038 [P] [US5] Add API tests in `tests/Feature/Api/Payments/PaymentBatchEndpointTest.php` covering `/payment-batches` create + status retrieval.

### Implementation for User Story 5

- [ ] T039 [US5] Add migration in `stack/modules/Accounting/Database/Migrations/` for `invoicing.payment_receipt_batches` with RLS, and update `stack/app/Models/Payment.php` to include `batch_id` relationship/index.
- [ ] T040 [US5] Implement batch ingestion domain workflow (`stack/modules/Accounting/Domain/Payments/Actions/CreatePaymentBatchAction.php`, background jobs under `stack/modules/Accounting/Jobs/`) handling CSV parsing, idempotency, and optimistic locking.
- [ ] T041 [US5] Wire API endpoints in `stack/modules/Accounting/Http/Controllers/Api/PaymentController.php` and routes for `/payment-batches` POST + GET, returning payloads per `contracts/payments.yaml`.
- [ ] T042 [P] [US5] Build CLI command `stack/app/Console/Commands/PaymentBatchImport.php` and update Quickstart instructions for batch imports.
- [ ] T043 [US5] Create UI batch management view (`stack/resources/js/Pages/Invoicing/Payments/Batches.vue`) with upload progress, error reporting, and localization.
- [ ] T044 [US5] Instrument batch metrics/alerts by extending `stack/modules/Accounting/Domain/Payments/Telemetry/PaymentMetrics.php` and updating monitoring docs with batch KPIs.

**Checkpoint**: User Story 5 delivers batch ingestion across API/CLI/UI with monitoring in place.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Improvements that affect multiple user stories

- [ ] T045 [P] Documentation sweep updating `docs/api-allocation-guide.md`, `specs/005-payment-processing-receipt/quickstart.md`, and `docs/ServiceContext-Monitoring.md` to consolidate final behaviour (linking to final endpoints & metrics).
- [ ] T046 Resolve TODOs/tech debt flagged during implementation, including consolidating duplicated services between `stack/app/Services` and `stack/modules/Accounting/Domain/Payments`.
- [ ] T047 [P] Run end-to-end Playwright scenario `tests/Playwright/payments/receipt-allocation.spec.ts` (to be added) and update fixture data, ensuring CLI↔GUI parity.
- [ ] T048 Finalize release notes & compliance evidence by updating `docs/TEAM_MEMORY.md` with shipped artefacts and verifying telemetry dashboards.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately.
- **Foundational (Phase 2)**: Depends on Phase 1 completion — blocks all user stories.
- **User Stories (Phases 3–7)**: Depend on Phase 2; should proceed in priority order (US1 ➜ US2 ➜ US3 ➜ US4 ➜ US5) though later stories can begin once prerequisites they rely on are done.
- **Polish (Phase 8)**: Runs after desired user stories complete.

### User Story Dependencies

- **US1 (P1)**: Independent once foundation ready.
- **US2 (P2)**: Depends on US1 (requires core allocation flows).
- **US3 (P2)**: Depends on US1 (audit data emitted during core flows).
- **US4 (P2)**: Depends on US1 (needs payment records) and benefits from US3 audit hooks.
- **US5 (P3)**: Depends on US1 (core payment creation) and US2 (unallocated cash handling for batch row overflow).

### Within Each User Story

- Tests (T005/T006, T015/T016, etc.) MUST fail before implementing production code.
- Domain models/services update before API/CLI/UI layers.
- UI/CLI tasks depend on backend endpoints being in place.
- Checkpoints confirm story readiness before proceeding.

### Parallel Opportunities

- T005 and T006 can run in parallel while designing fail-first tests.
- UI localization task T013 can run alongside backend work (different files).
- In US2, tasks T017–T020 touch different layers and can be parallelized once backend contracts are final.
- US5 tasks T040–T043 split across backend, CLI, and UI permitting parallel delivery after migration T039.

---

## Parallel Example: User Story 1

```bash
# Suggested parallel workflow once tests (T005, T006) are committed:

# Terminal 1 — Backend actions/services
php artisan tinker # for rapid validation after implementing T007–T010

# Terminal 2 — CLI alignment
vendor/bin/pest tests/Console/PaymentAllocateCommandTest.php

# Terminal 3 — Frontend
pnpm run dev -- --host

# Terminal 4 — Telemetry verification
php artisan test stack/modules/Accounting/Tests/Feature/Payments/RecordPaymentTest.php
```

---

## Implementation Strategy

1. **MVP (US1)**: Deliver end-to-end receipt recording plus allocation across API/CLI/UI with telemetry.
2. **Stabilize**: Layer overpayments/receipts (US2) and audit/reporting (US3) to ensure transparency.
3. **Risk Mitigation**: Add reversal safeguards (US4) before enabling batch ingestion (US5), reducing recovery risk.
4. **Operationalize**: Finish with polish tasks to finalize documentation, telemetry, and parity validation.
