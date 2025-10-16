# Tasks: Journal Entries - Manual & Automatic

**Input**: Design documents from `/specs/007-journal-entries-manual/`
**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/

**Tests**: Include focused feature and browser tests per user story to keep ledger workflows independently verifiable.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`
- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization and baseline configuration required by all ledger stories

- [X] T001 Add `journal` and `ledger` queues to default worker configuration in `stack/config/accounting_queues.php`
- [X] T002 [P] Document queue worker requirements for journal processing in `docs/TEAM_MEMORY.md`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core schema, models, and permissions that all user stories rely on

- [X] T003 Create migrations for batches, sources, recurring templates, and audit tables in `stack/modules/Accounting/Database/Migrations/2025_10_20_000000_create_journal_foundation_tables.php`
- [X] T004 [P] Align `stack/app/Models/JournalEntry.php` with new columns, relationships, and `acct` schema references
- [X] T005 [P] Add `stack/app/Models/JournalBatch.php` and `stack/app/Models/RecurringJournalTemplate.php` with relationships and casts
- [X] T006 [P] Add `stack/app/Models/JournalEntrySource.php` and `stack/app/Models/JournalAudit.php` to represent traceability and audit records
- [X] T007 Update ledger-related permissions and role assignments in `stack/database/seeders/PermissionSeeder.php`
- [X] T008 [P] Extend `stack/modules/Accounting/Providers/AccountingServiceProvider.php` to register ledger actions, CLI, and queue bindings scaffolds

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 1 - Manual Journals & Approvals (Priority: P1) 🎯 MVP

**Goal**: Accountants can create, approve, post, and reverse manual journal entries with balance validation and period guards.

**Independent Test**: Submit a balanced manual journal via API or CLI, approve/post it, create a reversal, and verify both entries show correct status in the Inertia UI.

### Tests for User Story 1

- [X] T009 [P] [US1] Add Pest feature tests for manual entry create/approve/post/reverse in `stack/tests/Feature/Accounting/JournalEntries/ManualEntryTest.php`
- [X] T010 [P] [US1] Add Playwright scenario validating manual entry UI flow in `stack/tests/Browser/journal.manual-entry.spec.ts`

### Implementation for User Story 1

- [X] T011 [P] [US1] Implement `CreateManualJournalEntryAction.php` and `SubmitJournalEntryAction.php` in `stack/modules/Accounting/Domain/Ledgers/Actions/`
- [X] T012 [US1] Implement `ApproveJournalEntryAction.php`, `PostJournalEntryAction.php`, and `ReverseJournalEntryAction.php` in `stack/modules/Accounting/Domain/Ledgers/Actions/`
- [ ] T013 [US1] Register manual journal command bus routes in `stack/config/command-bus.php` and module registrar in `stack/modules/Accounting/Providers/AccountingServiceProvider.php`
- [ ] T014 [US1] Extend `stack/modules/Accounting/Services/LedgerService.php` to support manual journal persistence, validations, and reversal links
- [ ] T015 [P] [US1] Add API controller and routes for manual journals in `stack/modules/Accounting/Http/Controllers/Api/JournalEntryController.php` and `stack/modules/Accounting/routes/api.php`
- [ ] T016 [P] [US1] Add CLI commands for manual journal operations in `stack/modules/Accounting/CLI/Commands/JournalEntry*.php`
- [ ] T017 [P] [US1] Build Inertia pages (`Index.vue`, `Create.vue`, `Show.vue`) under `stack/resources/js/Pages/Accounting/JournalEntries/`
- [ ] T018 [US1] Wire Inertia forms to command bus responses and handle validation/audit status messaging in `stack/resources/js/Pages/Accounting/JournalEntries/`

**Checkpoint**: Manual journal workflows functional end-to-end with automated coverage

---

## Phase 4: User Story 2 - Automatic Ledger Posting for Transactions (Priority: P2)

**Goal**: Invoice posting and payment events automatically create balanced journal entries with source document links.

**Independent Test**: Post an invoice and record a payment; confirm corresponding journal entries and source references are generated without manual intervention.

### Tests for User Story 2

- [ ] T019 [P] [US2] Add feature tests covering invoice/payment auto-journaling and source links in `stack/tests/Feature/Accounting/JournalEntries/AutomaticEntryTest.php`

### Implementation for User Story 2

- [ ] T020 [US2] Update `stack/modules/Accounting/Domain/Payments/Services/LedgerService.php` to emit journal entries with `JournalEntrySource` records for payments and allocations
- [ ] T021 [US2] Introduce invoice posting journal actions/listeners in `stack/modules/Accounting/Domain/Ledgers/Listeners/InvoicePostedSubscriber.php`
- [ ] T022 [P] [US2] Register automatic journal command mappings and event subscribers in `stack/modules/Accounting/Providers/AccountingServiceProvider.php`
- [ ] T023 [US2] Persist origin metadata and ensure idempotency keys for auto entries in `stack/modules/Accounting/Domain/Ledgers/Actions/AutoJournalEntryAction.php`

**Checkpoint**: Automatic postings emit ledger entries with traceable metadata

---

## Phase 5: User Story 3 - Audit Trail, Search & Trial Balance (Priority: P3)

**Goal**: Users can audit journal history, filter/search entries, and generate a trial balance from posted journals.

**Independent Test**: View an entry’s audit timeline, search journals by status/date/reference, and fetch a trial balance snapshot that reconciles with the ledger.

### Tests for User Story 3

- [ ] T024 [P] [US3] Add feature tests for audit endpoint, search filters, and trial balance API in `stack/tests/Feature/Accounting/JournalEntries/JournalAuditTest.php`

### Implementation for User Story 3

- [ ] T025 [US3] Implement audit logging subscriber to populate `JournalAudit` records in `stack/modules/Accounting/Domain/Ledgers/Listeners/JournalAuditSubscriber.php`
- [ ] T026 [US3] Enhance `stack/modules/Accounting/Http/Controllers/Api/JournalEntryController.php` to support filtering, search, and source joins
- [ ] T027 [P] [US3] Implement audit endpoint and UI timeline component in `stack/modules/Accounting/Http/Controllers/Api/JournalEntryAuditController.php` and `stack/resources/js/Pages/Accounting/JournalEntries/components/AuditTimeline.vue`
- [ ] T028 [US3] Implement trial balance API controller using `acct.trial_balance` view in `stack/modules/Accounting/Http/Controllers/Api/TrialBalanceController.php`
- [ ] T029 [P] [US3] Add Inertia trial balance page and summary cards in `stack/resources/js/Pages/Accounting/JournalEntries/TrialBalance.vue`

**Checkpoint**: Audit, search, and reporting features independently verifiable

---

## Phase 6: User Story 4 - Recurring Templates & Batch Processing (Priority: P4)

**Goal**: Finance teams can schedule recurring journals and manage batch approvals/posting with queue-backed execution.

**Independent Test**: Create a recurring template and confirm scheduled generation; create a batch, approve/post it, and verify queue processing updates statuses.

### Tests for User Story 4

- [ ] T030 [P] [US4] Add feature tests for template scheduling and batch lifecycle in `stack/tests/Feature/Accounting/JournalEntries/RecurringTemplateTest.php`

### Implementation for User Story 4

- [ ] T031 [US4] Implement recurring template CRUD actions in `stack/modules/Accounting/Domain/Ledgers/Actions/Recurring/`
- [ ] T032 [US4] Add job and scheduler wiring for recurring generation in `stack/modules/Accounting/Jobs/GenerateRecurringJournalEntries.php` and `stack/app/Console/Kernel.php`
- [ ] T033 [P] [US4] Implement API & CLI endpoints for template management in `stack/modules/Accounting/Http/Controllers/Api/JournalTemplateController.php` and `stack/modules/Accounting/CLI/Commands/JournalTemplate*.php`
- [ ] T034 [US4] Implement batch approval/post endpoints and UI pages under `stack/modules/Accounting/Http/Controllers/Api/JournalBatchController.php` and `stack/resources/js/Pages/Accounting/JournalEntries/Batches/`
- [ ] T035 [P] [US4] Register queue routing and events for batch lifecycle in `stack/modules/Accounting/Providers/AccountingServiceProvider.php` and `stack/modules/Accounting/Events/`

**Checkpoint**: Automated scheduling and batch workflows ready for production use

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Hardening, documentation, and end-to-end validation across all stories

- [ ] T036 [P] Refresh ledger quickstart instructions with final CLI/UI steps in `specs/007-journal-entries-manual/quickstart.md`
- [ ] T037 Execute end-to-end validation from quickstart and capture notes in `docs/CUSTOMER-LIFECYCLE-COMPLIANCE.md`
- [ ] T038 Address performance and security review items (RLS, idempotency) in `stack/modules/Accounting/Services/` and related configs

---

## Dependencies & Execution Order

### Phase Dependencies
- **Phase 1 → Phase 2**: Setup must complete before foundational schema updates.
- **Phase 2 → Phases 3-6**: Foundational migrations/models/permissions block all user stories.
- **Phase 7**: Runs after desired user stories are complete.

### User Story Dependencies
- **US1 (P1)**: Depends on Phase 2; unlocks manual workflows and UI foundations leveraged by later stories.
- **US2 (P2)**: Depends on US1 command bus/action scaffolding for shared services.
- **US3 (P3)**: Depends on US1 for journal endpoints and US2 for source metadata population.
- **US4 (P4)**: Depends on US1 entities and US3 reporting hooks for batch status visibility.

### Within User Stories
- Tests (where included) should be authored before implementations.
- Actions/models precede service and controller wiring.
- UI tasks depend on API/command bus wiring to avoid stubbed endpoints.

---

## Parallel Execution Opportunities
- Phase 1 tasks can run concurrently across config and documentation.
- Within Phase 2, model additions (T004–T006) can run in parallel after migration scaffold (T003).
- In US1, CLI, API, and UI tasks (T015–T018) can proceed concurrently once actions/services (T011–T014) are in place.
- US2 listener/action updates (T020–T022) can be parallelized after ledger service changes are outlined.
- US3 UI work (T029) can parallel API enhancements (T026–T028) once response shapes are defined.
- US4 CLI/API endpoints (T033) and UI batch pages (T034) can progress simultaneously after recurring actions (T031) exist.

---

## Implementation Strategy
1. **MVP (US1)**: Deliver manual journal workflows end-to-end (Phases 1–3) to unblock finance teams.
2. **Automation (US2)**: Layer automatic postings to eliminate manual duplication.
3. **Compliance (US3)**: Ship audit/search/trial balance for oversight and reporting.
4. **Scale (US4)**: Introduce automation at scale with recurring templates and batch processing.
5. **Polish**: Harden, document, and validate with quickstart once all functional increments pass.
