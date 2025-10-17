# Tasks: Period Close - Monthly Closing Process

**Input**: Design documents from `/specs/008-period-close-monthly/`
**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/, quickstart.md

**Tests**: Only included where the specification or constitution expectations imply mandatory coverage (ledger locking, audit integrity).

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`
- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Prepare shared scaffolding for backend, frontend, and tests.

- [ ] T001 Create feature test scaffold for period close at `stack/tests/Feature/Ledger/PeriodClose/PeriodCloseChecklistTest.php`
- [ ] T002 Create Inertia page directory and placeholder component at `stack/resources/js/Pages/Ledger/PeriodClose/Index.vue`
- [ ] T003 Create Playwright spec shell for close workflow at `stack/tests/Browser/period-close.spec.ts`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented.

- [ ] T004 Add migration `stack/database/migrations/2025_10_XX_create_ledger_period_close_tables.php` for `ledger.period_closes`, `ledger.period_close_tasks`, templates, and checklist indexes
- [ ] T005 Add migration `stack/database/migrations/2025_10_XX_update_accounting_periods_for_close.php` to extend period status enum, reopen columns, and locking triggers
- [ ] T006 Update accounting period model state and relations in `stack/app/Models/AccountingPeriod.php`
- [ ] T007 Create period close model with company scoping in `stack/modules/Ledger/Domain/PeriodClose/Models/PeriodClose.php`
- [ ] T008 Create period close task model tracking checklist state at `stack/modules/Ledger/Domain/PeriodClose/Models/PeriodCloseTask.php`
- [ ] T009 Create period close template model at `stack/modules/Ledger/Domain/PeriodClose/Models/PeriodCloseTemplate.php`
- [ ] T010 Create period close template task model at `stack/modules/Ledger/Domain/PeriodClose/Models/PeriodCloseTemplateTask.php`
- [ ] T011 Seed new permissions (`period-close.view`, `period-close.close`, `period-close.reopen`) in `stack/database/seeders/CompanyPermissionsSeeder.php`
- [ ] T012 Introduce `Modules\Ledger\Services\PeriodCloseService` skeleton at `stack/modules/Ledger/Services/PeriodCloseService.php`

---

## Phase 3: User Story 1 – Checklist & Validations (Priority: P1) 🎯 MVP

**Goal**: Accountants can launch the monthly close process, review the automated checklist, and see validation output before locking.

**Independent Test**: From the dashboard, start a period close, ensure checklist tasks render with required status, and validation API returns zero blocking items when conditions met.

### Implementation & Tests

- [ ] T013 [P] [US1] Implement feature coverage for checklist start/refresh in `stack/tests/Feature/Ledger/PeriodClose/PeriodCloseChecklistTest.php`
- [ ] T014 [P] [US1] Add service unit tests for validation rules in `stack/tests/Unit/Modules/Ledger/PeriodClose/PeriodCloseServiceTest.php`
- [ ] T015 [US1] Implement start action in `stack/modules/Ledger/Domain/PeriodClose/Actions/StartPeriodCloseAction.php`
- [ ] T016 [US1] Implement summary action in `stack/modules/Ledger/Domain/PeriodClose/Actions/GetPeriodCloseSnapshotAction.php`
- [ ] T017 [US1] Implement validation action in `stack/modules/Ledger/Domain/PeriodClose/Actions/ValidatePeriodCloseAction.php`
- [ ] T018 [US1] Flesh out start/snapshot/validate methods in `stack/modules/Ledger/Services/PeriodCloseService.php`
- [ ] T019 [US1] Register `period-close.start` and `period-close.validate` handlers in `stack/config/command-bus.php`
- [ ] T020 [US1] Build API endpoints (index/start/validate) in `stack/app/Http/Controllers/Ledger/PeriodCloseController.php`
- [ ] T021 [US1] Add period close API routes to `stack/routes/api.php`
- [ ] T022 [US1] Create Inertia page controller at `stack/app/Http/Controllers/Ledger/PeriodClosePageController.php`
- [ ] T023 [US1] Register web route for period close dashboard in `stack/routes/web.php`
- [ ] T024 [P] [US1] Implement checklist UI and state management in `stack/resources/js/Pages/Ledger/PeriodClose/Index.vue`
- [ ] T025 [P] [US1] Build reusable checklist/task components in `stack/resources/js/Pages/Ledger/PeriodClose/components/ChecklistPanel.vue`
- [ ] T026 [US1] Wire frontend API calls for start/validate flows in `stack/resources/js/Pages/Ledger/PeriodClose/Index.vue`

**Checkpoint**: MVP ready – start/validate endpoints, checklist UI, and validations operate independently.

---

## Phase 4: User Story 2 – Adjusting Journal Entries (Priority: P1)

**Goal**: Controllers can create and track period-end adjustments directly from the close workflow with audit tagging.

**Independent Test**: Trigger adjustment creation, confirm journal entry is posted with `period_adjustment` type and metadata linking the period close.

### Implementation & Tests

- [ ] T027 [P] [US2] Cover period adjustment flow in `stack/tests/Feature/Ledger/PeriodClose/PeriodCloseAdjustmentTest.php`
- [ ] T028 [US2] Extend adjustment orchestration in `stack/modules/Ledger/Services/PeriodCloseService.php`
- [ ] T029 [US2] Implement adjustment action handler in `stack/modules/Ledger/Domain/PeriodClose/Actions/CreatePeriodCloseAdjustmentAction.php`
- [ ] T030 [US2] Register `period-close.adjustment` command in `stack/config/command-bus.php`
- [ ] T031 [US2] Add adjustment endpoint to `stack/app/Http/Controllers/Ledger/PeriodCloseController.php`
- [ ] T032 [US2] Surface adjustment controls in `stack/resources/js/Pages/Ledger/PeriodClose/Index.vue`
- [ ] T033 [US2] Support `period_adjustment` entry type in `stack/modules/Ledger/Services/LedgerService.php`

**Checkpoint**: Adjustments can be created, tagged, and reflected in the checklist without impacting other stories.

---

## Phase 5: User Story 3 – Lock & Close Period (Priority: P1)

**Goal**: Authorised users can lock and close the period, preventing further edits while capturing audit logs.

**Independent Test**: After locking, attempts to post or backdate transactions fail; audit entries log close events with user/time.

### Implementation & Tests

- [ ] T034 [P] [US3] Add close/lock feature test in `stack/tests/Feature/Ledger/PeriodClose/PeriodCloseCloseTest.php`
- [ ] T035 [P] [US3] Add blocked mutation regression test in `stack/tests/Feature/Ledger/PeriodClose/PreventBackdateTest.php`
- [ ] T036 [US3] Implement lock/complete methods with audit logging in `stack/modules/Ledger/Services/PeriodCloseService.php`
- [ ] T037 [US3] Create lock action in `stack/modules/Ledger/Domain/PeriodClose/Actions/LockPeriodCloseAction.php`
- [ ] T038 [US3] Create complete action in `stack/modules/Ledger/Domain/PeriodClose/Actions/CompletePeriodCloseAction.php`
- [ ] T039 [US3] Register `period-close.lock` and `period-close.complete` handlers in `stack/config/command-bus.php`
- [ ] T040 [US3] Expand API controller for lock/complete endpoints in `stack/app/Http/Controllers/Ledger/PeriodCloseController.php`
- [ ] T041 [US3] Finalize trigger-based guards in `stack/database/migrations/2025_10_XX_update_accounting_periods_for_close.php`
- [ ] T042 [US3] Add lock/close flows and messaging to `stack/resources/js/Pages/Ledger/PeriodClose/Index.vue`

**Checkpoint**: Closing a period enforces ledger locks and audit coverage independently of later stories.

---

## Phase 6: User Story 4 – Period Close Reporting (Priority: P2)

**Goal**: Controllers can generate and retrieve period-close financial statements directly from the workflow.

**Independent Test**: Request reports, verify refreshed statements stored and accessible for download for the closed period.

### Implementation & Tests

- [ ] T043 [P] [US4] Add reporting feature test in `stack/tests/Feature/Ledger/PeriodClose/PeriodCloseReportTest.php`
- [ ] T044 [US4] Implement report generation hooks in `stack/modules/Ledger/Services/PeriodCloseService.php`
- [ ] T045 [US4] Add report action handler in `stack/modules/Ledger/Domain/PeriodClose/Actions/GeneratePeriodCloseReportsAction.php`
- [ ] T046 [US4] Expose reports endpoint in `stack/app/Http/Controllers/Ledger/PeriodCloseController.php`
- [ ] T047 [US4] Render report download panel in `stack/resources/js/Pages/Ledger/PeriodClose/Index.vue`

**Checkpoint**: Reporting works independently once prior phases complete.

---

## Phase 7: User Story 5 – Reopen Period (Priority: P2)

**Goal**: CFOs can reopen a closed period with justification, recording the audit trail and enforcing temporary windows.

**Independent Test**: Reopen request records audit metadata, transitions state to `reopened`, and permits edits until re-lock.

### Implementation & Tests

- [ ] T048 [P] [US5] Add reopen feature test in `stack/tests/Feature/Ledger/PeriodClose/PeriodCloseReopenTest.php`
- [ ] T049 [US5] Implement reopen logic and scheduling in `stack/modules/Ledger/Services/PeriodCloseService.php`
- [ ] T050 [US5] Create reopen action handler in `stack/modules/Ledger/Domain/PeriodClose/Actions/ReopenPeriodCloseAction.php`
- [ ] T051 [US5] Register `period-close.reopen` command in `stack/config/command-bus.php`
- [ ] T052 [US5] Add reopen endpoint to `stack/app/Http/Controllers/Ledger/PeriodCloseController.php`
- [ ] T053 [US5] Add reopen UI controls and warnings in `stack/resources/js/Pages/Ledger/PeriodClose/Index.vue`

**Checkpoint**: Reopen workflow self-contained with audit compliance.

---

## Phase 8: User Story 6 – Templates & Workflows (Priority: P3)

**Goal**: Teams can manage reusable period-close templates and task definitions per tenant.

**Independent Test**: CRUD template operations persist checklist definitions and can seed future closes without extra dependencies.

### Implementation & Tests

- [ ] T054 [P] [US6] Add template management test in `stack/tests/Feature/Ledger/PeriodClose/PeriodCloseTemplateTest.php`
- [ ] T055 [US6] Implement template services in `stack/modules/Ledger/Services/PeriodCloseService.php`
- [ ] T056 [US6] Create sync action handler in `stack/modules/Ledger/Domain/PeriodClose/Actions/SyncPeriodCloseTemplateAction.php`
- [ ] T057 [US6] Create update action handler in `stack/modules/Ledger/Domain/PeriodClose/Actions/UpdatePeriodCloseTemplateAction.php`
- [ ] T058 [US6] Create archive action handler in `stack/modules/Ledger/Domain/PeriodClose/Actions/ArchivePeriodCloseTemplateAction.php`
- [ ] T059 [US6] Register template command handlers in `stack/config/command-bus.php`
- [ ] T060 [US6] Add template API controller at `stack/app/Http/Controllers/Ledger/PeriodCloseTemplateController.php`
- [ ] T061 [US6] Register template routes in `stack/routes/api.php`
- [ ] T062 [US6] Implement template management UI in `stack/resources/js/Pages/Ledger/PeriodClose/components/TemplateDrawer.vue`

**Checkpoint**: Template CRUD operates independently once prior infrastructure exists.

---

## Phase 9: Polish & Cross-Cutting Concerns

**Purpose**: Refinements impacting multiple stories.

- [ ] T063 [P] Finalize Playwright coverage in `stack/tests/Browser/period-close.spec.ts`
- [ ] T064 Update operational runbook with monitoring steps in `docs/monitoring/period-close.md`
- [ ] T065 Refresh quickstart instructions in `specs/008-period-close-monthly/quickstart.md`
- [ ] T066 Harden audit event catalog in `stack/modules/Ledger/Services/PeriodCloseService.php` with metric hooks
- [ ] T067 Run end-to-end verification script documented in `specs/008-period-close-monthly/quickstart.md`

---

## Dependencies & Execution Order

### Phase Dependencies
- **Phase 1 (Setup)** → enables foundational work.
- **Phase 2 (Foundational)** → blocks all user stories until migrations, models, permissions, and service skeleton exist.
- **Phase 3 (US1)** → establishes MVP; later stories depend on the checklist API/service.
- **Phase 4 (US2)** → depends on Phase 3 for period close context.
- **Phase 5 (US3)** → depends on Phases 3–4 for adjustment linkage and validation.
- **Phase 6 (US4)** → depends on completed close workflow to refresh statements.
- **Phase 7 (US5)** → depends on lock/close mechanics.
- **Phase 8 (US6)** → depends on prior phases for domain fundamentals but can proceed parallel with reporting/reopen once API scaffolding stabilises.
- **Phase 9 (Polish)** → final verification after desired stories complete.

### User Story Dependencies
- **US1 (Checklist & Validations)**: unlocks the close workflow; no dependency beyond foundational phase.
- **US2 (Adjustments)**: depends on checklist context to tag adjustments.
- **US3 (Lock & Close)**: depends on adjustments to ensure totals and metadata before locking.
- **US4 (Reporting)**: depends on closed periods; can run parallel with US5 after US3.
- **US5 (Reopen)**: depends on locked period state.
- **US6 (Templates)**: depends on baseline checklist infrastructure but can progress alongside US4/US5 once service scaffolding exists.

### Parallel Opportunities
- Tasks marked [P] within each phase can proceed concurrently (tests, UI components).
- After Phase 2, US1 work can begin; once US1 stabilises, US2 and US3 require sequential coordination, while US4 and US5 can split workstreams post-close.
- US6 template management can progress in parallel with reporting and reopen once service endpoints exist.

---

## Parallel Execution Examples

### User Story 1
```bash
# Parallel tasks
Task T013 (Feature tests) and Task T014 (Service unit tests)
Task T024 (Checklist UI) and Task T025 (Checklist components)
```

### User Story 2
```bash
# Parallel tasks
Task T027 (Adjustment feature test) and Task T033 (LedgerService entry type support)
```

### User Story 4
```bash
# Parallel tasks
Task T043 (Report feature test) and Task T047 (Report UI panel)
```

### User Story 6
```bash
# Parallel tasks
Task T054 (Template tests), T062 (Template UI), and T059 (Command bus registrations)
```

---

## Implementation Strategy

### MVP First (US1 only)
1. Complete Phase 1 and Phase 2 prerequisites.
2. Deliver Phase 3 (US1) to ship checklist + validation MVP.
3. Validate using T013/T014 tests and manual flow from quickstart.

### Incremental Delivery
1. Ship US1 (checklist), demo to stakeholders.
2. Add US2 (adjustments) to capture period-end journals.
3. Deliver US3 (lock/close) for full compliance.
4. Layer optional enhancements: US4 reporting, US5 reopen, US6 templates.

### Parallel Team Strategy
1. Shared team completes Setup & Foundational phases.
2. Developer A owns US1 → US2.
3. Developer B picks up US3 once US1 stabilized, then US5.
4. Developer C drives US4 and US6 in parallel after API scaffolding.
5. Team reconvenes for Phase 9 polish and validation.
