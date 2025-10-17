# Tasks: Bank Reconciliation - Statement Matching

**Input**: Design documents from `/specs/009-bank-reconciliation-statement/`
**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/

**Tests**: Acceptance coverage is included where called out; each user story has at least one feature test for regression safety.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`
- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Establish dependencies and configuration required for bank statement ingestion.

- [ ] T001 Update composer dependencies with OFX/QFX parser packages in `stack/composer.json`
- [ ] T002 Register `bank-statements` storage disk configuration in `stack/config/filesystems.php`
- [ ] T003 Add bank statement disk environment variables to `stack/.env.example`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core schema, models, and permissions that all stories rely on.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [ ] T004 Create migration `stack/database/migrations/2025_10_17_000300_create_ops_bank_statements_tables.php` for `ops.bank_statements` and `ops.bank_statement_lines`
- [ ] T005 Create migration `stack/database/migrations/2025_10_17_000400_create_ledger_bank_reconciliation_tables.php` for reconciliations, matches, and adjustments
- [ ] T006 [P] Define `stack/app/Models/Ops/BankStatement.php` with company scoping and relations
- [ ] T007 [P] Define `stack/app/Models/Ops/BankStatementLine.php` with hash dedupe helper
- [ ] T008 Implement `stack/app/Models/Ledger/BankReconciliation.php`, `BankReconciliationMatch.php`, and `BankReconciliationAdjustment.php` with state enums
- [ ] T009 Seed reconciliation permissions in `stack/database/seeders/CompanyPermissionsSeeder.php`

---

## Phase 3: User Story 1 - Import and Normalize Bank Statements (Priority: P1) 🎯 MVP

**Goal**: Accountants can upload CSV/OFX/QFX files, store metadata, and review normalized statement lines.

**Independent Test**: Upload a sample statement via UI; verify lines persist in `ops.bank_statement_lines` and display in the import review table.

### Implementation for User Story 1

- [ ] T010 [P] [US1] Add feature test `stack/tests/Feature/Ledger/BankStatementImportTest.php` covering CSV and OFX uploads
- [ ] T011 [P] [US1] Implement command action `stack/modules/Ledger/Actions/BankReconciliation/ImportBankStatement.php`
- [ ] T012 [P] [US1] Build parser service with CSV/OFX/QFX adapters in `stack/modules/Ledger/Services/BankStatementImportService.php`
- [ ] T013 [US1] Queue normalization workflow in `stack/modules/Ledger/Jobs/NormalizeBankStatement.php`
- [ ] T014 [US1] Add controller + routes in `stack/app/Http/Controllers/Ledger/BankStatementImportController.php` and `stack/routes/web.php`
- [ ] T015 [US1] Create Inertia upload page `stack/resources/js/Pages/Ledger/BankReconciliation/Import.vue`
- [ ] T016 [US1] Add request validation for idempotent imports in `stack/app/Http/Requests/Ledger/ImportBankStatementRequest.php`

**Checkpoint**: Statement import works end-to-end and is independently demoable.

---

## Phase 4: User Story 2 - Auto-Match and Manual Matching (Priority: P1)

**Goal**: Provide automated suggestions and manual tools to link statement lines with internal transactions.

**Independent Test**: Run auto-match on a prepared data set; confirm matched lines update variance totals and manual overrides persist.

### Implementation for User Story 2

- [ ] T017 [P] [US2] Add feature test `stack/tests/Feature/Ledger/BankReconciliationAutoMatchTest.php` for auto-match and manual overrides
- [ ] T018 [P] [US2] Implement auto-match action `stack/modules/Ledger/Actions/BankReconciliation/RunAutoMatch.php`
- [ ] T019 [P] [US2] Create matching service with confidence scoring in `stack/modules/Ledger/Services/BankReconciliationMatchingService.php`
- [ ] T020 [US2] Emit match events in `stack/modules/Ledger/Events/BankReconciliationMatched.php`
- [ ] T021 [US2] Build manual match controller endpoints in `stack/app/Http/Controllers/Ledger/BankReconciliationMatchController.php` and update `stack/routes/api.php`
- [ ] T022 [US2] Implement reconciliation workspace UI in `stack/resources/js/Pages/Ledger/BankReconciliation/Workspace.vue`
- [ ] T023 [US2] Subscribe to WebSocket updates in `stack/resources/js/services/bankReconciliationChannel.ts`

**Checkpoint**: Matching experience is functional and can be validated independently.

---

## Phase 5: User Story 3 - Manage Discrepancies and Adjustments (Priority: P1)

**Goal**: Allow accountants to record bank fees, interest, and timing adjustments to resolve variances.

**Independent Test**: Record a bank fee and interest adjustment; verify journal entries post and variance returns to zero.

### Implementation for User Story 3

- [ ] T024 [P] [US3] Add feature test `stack/tests/Feature/Ledger/BankReconciliationAdjustmentTest.php` for adjustments
- [ ] T025 [P] [US3] Implement adjustment action `stack/modules/Ledger/Actions/BankReconciliation/CreateAdjustment.php`
- [ ] T026 [US3] Build adjustment service to post journal entries in `stack/modules/Ledger/Services/BankReconciliationAdjustmentService.php`
- [ ] T027 [US3] Expose adjustment API in `stack/app/Http/Controllers/Ledger/BankReconciliationAdjustmentController.php`
- [ ] T028 [US3] Add adjustment drawer UI `stack/resources/js/Pages/Ledger/BankReconciliation/AdjustmentDrawer.vue`
- [ ] T029 [US3] Update variance calculations in `stack/modules/Ledger/Services/BankReconciliationSummaryService.php`

**Checkpoint**: Variance resolution via adjustments is independently testable.

---

## Phase 6: User Story 4 - Reconciliation Lifecycle & Locking (Priority: P2)

**Goal**: Manage reconciliation completion, locking, and reopening with proper authorization controls.

**Independent Test**: Complete a reconciliation, verify lock prevents edits, then reopen with appropriate permission.

### Implementation for User Story 4

- [ ] T030 [P] [US4] Add feature test `stack/tests/Feature/Ledger/BankReconciliationLifecycleTest.php` for complete/lock/reopen flows
- [ ] T031 [US4] Implement completion action `stack/modules/Ledger/Actions/BankReconciliation/CompleteReconciliation.php`
- [ ] T032 [US4] Implement reopen action with variance checks in `stack/modules/Ledger/Actions/BankReconciliation/ReopenReconciliation.php`
- [ ] T033 [US4] Add lifecycle endpoints in `stack/app/Http/Controllers/Ledger/BankReconciliationStatusController.php`
- [ ] T034 [US4] Enhance workspace UI with lock controls in `stack/resources/js/Pages/Ledger/BankReconciliation/Workspace.vue`
- [ ] T035 [US4] Enforce policy checks in `stack/app/Policies/BankReconciliationPolicy.php`

**Checkpoint**: Reconciliation lifecycle management is independently verifiable.

---

## Phase 7: User Story 5 - Reporting & Audit Trail (Priority: P2)

**Goal**: Deliver reconciliation reports and persist audit events for compliance and monitoring.

**Independent Test**: Generate a reconciliation report and confirm audit log entries for lifecycle and adjustment actions.

### Implementation for User Story 5

- [ ] T036 [P] [US5] Add feature test `stack/tests/Feature/Ledger/BankReconciliationReportTest.php` for report download and audit hooks
- [ ] T037 [US5] Implement audit subscriber in `stack/modules/Ledger/Listeners/BankReconciliationAuditSubscriber.php`
- [ ] T038 [US5] Publish status change broadcasts in `stack/modules/Ledger/Events/BankReconciliationStatusChanged.php`
- [ ] T039 [US5] Build report service in `stack/modules/Ledger/Services/BankReconciliationReportService.php`
- [ ] T040 [US5] Add report controller + route in `stack/app/Http/Controllers/Ledger/BankReconciliationReportController.php`
- [ ] T041 [US5] Surface report & audit UI in `stack/resources/js/Pages/Ledger/BankReconciliation/ReportPanel.vue`

**Checkpoint**: Reporting and audit outputs are independently demonstrable.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Repository-wide refinements and documentation once core stories are stable.

- [ ] T042 [P] Update quickstart guidance in `specs/009-bank-reconciliation-statement/quickstart.md`
- [ ] T043 Document monitoring expectations in `stack/docs/monitoring/bank-reconciliation.md`
- [ ] T044 Run end-to-end validation using quickstart steps in `stack/tests/Feature/Ledger/BankReconciliationQuickstartTest.php`

---

## Dependencies & Execution Order

### Phase Dependencies
- **Phase 1 → Phase 2**: Setup must complete before schema work begins.
- **Phase 2 → Phases 3-7**: Foundational schema/models/permissions block all user stories.
- **Phase 8** depends on completion of all targeted user stories.

### User Story Dependencies
- **US1 (P1)** has no story dependencies once Phase 2 finishes.
- **US2 (P1)** depends on US1 data ingestion being available.
- **US3 (P1)** depends on US2 for match artifacts and US1 for statement data.
- **US4 (P2)** depends on US3 to ensure variance logic is correct before locking.
- **US5 (P2)** depends on US4 lifecycle events and US3 adjustments for accurate reporting.

### Task-Level Notes
- Tests precede implementation within each story so they can drive development.
- UI tasks should start after corresponding backend endpoints are stubbed to avoid placeholder data.

---

## Parallel Execution Examples

- **US1**: T010 and T011 can run in parallel; T012 may begin once T001-T002 establish dependencies.
- **US2**: T017, T018, and T019 can proceed concurrently, while T022 waits on backend endpoints (T021).
- **US3**: T024 and T025 can run together; T028 can start once API (T027) exposes adjustment payloads.
- **US4**: T030 can run in parallel with T031/T032; T034 depends on backend status endpoints.
- **US5**: T036 and T037 can execute simultaneously; T041 follows report service completion (T039).

---

## Implementation Strategy

### MVP First (Deliver US1)
1. Complete Phases 1 and 2.
2. Ship Phase 3 (US1) and validate import/normalization flow.
3. Demo MVP to stakeholders before continuing.

### Incremental Delivery
1. After MVP, add US2 for matching to unlock reconciliation productivity.
2. Layer US3 to resolve discrepancies and achieve zero variance.
3. Deliver US4 once teams need reconciliation locking controls.
4. Finish with US5 to satisfy reporting/audit requirements.

### Parallel Team Strategy
- One team handles backend command bus and services while another builds Inertia/PrimeVue pages per story.
- Dedicated QA can implement T010/T017/T024/T030/T036 tests while development progresses on services.

---
