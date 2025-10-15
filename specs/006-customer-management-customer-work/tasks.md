# Tasks: Customer Management - Complete Customer Lifecycle

**Input**: Design documents from `/specs/006-customer-management-customer-work/`
**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/

**Tests**: Tests are mandatory per Constitution Principle IX—author them first, ensure they fail, and only then proceed with implementation.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`
- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization and basic structure

- [X] T001 [P] Create `stack/modules/Accounting/Domain/Customers/{Actions,Services,Exceptions,Telemetry}` directories with `.gitkeep` files to anchor the new domain structure.
- [X] T002 [P] Add baseline `stack/resources/js/locales/en-US/customers.json` with empty sections for list, detail, contacts, credit, aging, and statements copy.
- [X] T003 [P] Create compliance evidence scaffold at `docs/CUSTOMER-LIFECYCLE-COMPLIANCE.md` capturing sections for RBAC, RLS, telemetry, and performance metrics.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [X] T004 Update `stack/modules/Accounting/Providers/AccountingServiceProvider.php` to autoload `Domain/Customers` actions/services and register the Customers CLI namespace.
- [X] T005 Ensure `stack/modules/Accounting/Domain/Customers/Actions/registry.php` exists (returning an empty array) and prune obsolete `customer.*` mappings from `stack/config/command-bus.php`, replacing them with an include of the new registry.

**Checkpoint**: Foundation ready — user story implementation can now begin in parallel.

---

## Phase 3: User Story 1 - Customer Master Data (Priority: P1) 🎯 MVP

**Goal**: Accountants can create, update, delete, and search customer records with status management and CLI/API parity.

**Independent Test**: Using a seeded tenant, run `php artisan customer:create` then hit `/api/customers` and the Inertia customers list to verify the new record appears with correct status transitions.

### Tests for User Story 1 — write first, ensure they fail ⚠️

- [X] T006 [P] [US1] Add red PHPUnit test `stack/tests/Feature/Accounting/Customers/CreateCustomerActionTest.php` covering `customer.create` bus dispatch and validation.
- [X] T007 [P] [US1] Add red CLI probe test `stack/tests/Feature/CLI/CustomerListCommandTest.php` asserting the new `customer:list` command outputs JSON with pagination metadata.

### Implementation for User Story 1

- [X] T008 [US1] Create migration `stack/database/migrations/2025_10_15_124630_update_customers_table_add_customer_lifecycle_fields.php` adding `legal_name`, `default_currency`, `credit_limit_effective_at`, and new indexes per data model.
- [X] T009 [US1] Update `stack/app/Models/Customer.php` to point to `invoicing.customers`, expose new fillable/casts, and define relationships for contacts, addresses, credit limits, statements, and communications.
- [X] T010 [P] [US1] Implement `stack/modules/Accounting/Domain/Customers/Services/CustomerQueryService.php` encapsulating list/search/detail queries with company scoping.
- [X] T011 [P] [US1] Add `CreateCustomerAction` in `stack/modules/Accounting/Domain/Customers/Actions/CreateCustomerAction.php` dispatching audit events and returning DTOs.
- [X] T012 [P] [US1] Add `UpdateCustomerAction` in `stack/modules/Accounting/Domain/Customers/Actions/UpdateCustomerAction.php` with optimistic concurrency and audit logging.
- [X] T013 [P] [US1] Add `DeleteCustomerAction` in `stack/modules/Accounting/Domain/Customers/Actions/DeleteCustomerAction.php` honoring soft deletes and audit logging.
- [X] T014 [P] [US1] Add `ChangeCustomerStatusAction` in `stack/modules/Accounting/Domain/Customers/Actions/ChangeCustomerStatusAction.php` enforcing allowed transitions.
- [X] T015 [US1] Populate `stack/modules/Accounting/Domain/Customers/Actions/registry.php` with `customer.create`, `customer.update`, `customer.delete`, and `customer.status` mappings to the new action classes.
- [X] T016 [US1] Update `stack/config/command-bus.php` to map customer verbs to the module actions and add descriptive docblocks for palette integration.
- [X] T017 [US1] Refactor `stack/app/Http/Controllers/Invoicing/CustomerController.php` to delegate to bus actions, return Inertia props via `CustomerQueryService`, and surface search/status filters.
- [X] T018 [US1] Register REST routes in `stack/routes/api.php` for `/customers`, `/customers/{customerId}`, and `/customers/{customerId}/status` pointing to new API handlers.
- [X] T019 [P] [US1] Implement `stack/modules/Accounting/Http/Controllers/Api/CustomerController.php` providing list/create/show/update/delete/status endpoints backed by the bus actions.
- [X] T020 [P] [US1] Build `stack/resources/js/Pages/Accounting/Customers/Index.vue` using PrimeVue DataTable with server-side pagination, filters, and export triggers.
- [X] T021 [P] [US1] Build `stack/resources/js/Pages/Accounting/Customers/Form.vue` handling create/update with validation messaging tied to API errors.
- [X] T022 [US1] Build `stack/resources/js/Pages/Accounting/Customers/Show.vue` to display core profile data and container tabs for downstream stories.
- [X] T023 [US1] Add CLI commands `customer:create`, `customer:update`, `customer:delete`, and `customer:list` under `stack/modules/Accounting/CLI/Commands/` with JSON output parity.
- [X] T024 [US1] Populate `stack/resources/js/locales/en-US/customers.json` with copy for list labels, filters, statuses, and form validation messages.
- [X] T025 [US1] Extend `stack/database/seeders/CommandSeeder.php` to register the new customer CLI commands and palette metadata.

**Checkpoint**: At this point, User Story 1 should be fully functional and testable independently.

---

## Phase 4: User Story 2 - Contacts, Addresses & Communications (Priority: P2)

**Goal**: Accountants can maintain multiple contacts/addresses, manage customer groups, and log communication history with proper permissions.

**Independent Test**: For an existing customer, create a billing contact, assign to a group, and log a call; verify via API and the Inertia detail tabs while enforcing RBAC.

### Tests for User Story 2 — write first, ensure they fail ⚠️

- [X] T026 [P] [US2] Add red PHPUnit test `stack/tests/Feature/Accounting/Customers/ManageCustomerContactsTest.php` covering contact CRUD bus actions and uniqueness rules.
- [X] T027 [P] [US2] Add red Playwright spec `stack/tests/Browser/customers.contacts.spec.ts` covering tab navigation, contact add/edit, and permission gating.

### Implementation for User Story 2

- [X] T028 [US2] Create migration `stack/database/migrations/2025_10_15_120100_create_customer_contacts_table.php` with RLS policies and primary-contact uniqueness constraints.
- [X] T029 [P] [US2] Create migration `stack/database/migrations/2025_10_15_120110_create_customer_addresses_table.php` with default-address enforcement and RLS policies.
- [X] T030 [P] [US2] Create migration `stack/database/migrations/2025_10_15_120120_create_customer_groups_tables.php` for groups and membership with RLS and unique indexes.
- [X] T031 [P] [US2] Create migration `stack/database/migrations/2025_10_15_120130_create_customer_communications_table.php` capturing channel/direction metadata with RLS.
- [X] T032 [P] [US2] Add `CustomerContact` and `CustomerAddress` models in `stack/modules/Accounting/Domain/Customers/Models/` with relationships and scopes.
- [X] T033 [P] [US2] Add `CustomerGroup` and `CustomerGroupMember` models with helper methods for membership toggles.
- [X] T034 [P] [US2] Add `CustomerCommunication` model including attachment casting and timeline helpers.
- [X] T035 [P] [US2] Implement contact actions (`Create/Update/DeleteCustomerContactAction`) under `stack/modules/Accounting/Domain/Customers/Actions/`.
- [X] T036 [P] [US2] Implement address actions (`Create/Update/DeleteCustomerAddressAction`) under the same namespace.
- [X] T037 [P] [US2] Implement group membership actions (`AssignCustomerToGroupAction`, `RemoveCustomerFromGroupAction`, `CreateCustomerGroupAction`).
- [X] T038 [P] [US2] Implement communication log actions (`LogCustomerCommunicationAction`, `DeleteCustomerCommunicationAction`) with audit hooks.
- [X] T039 [US2] Extend `stack/modules/Accounting/Domain/Customers/Actions/registry.php` and `stack/config/command-bus.php` with contact, address, group, and communication action IDs.
- [X] T040 [US2] Update `stack/routes/api.php` to expose `/customers/{id}/contacts`, `/addresses`, `/groups`, and `/communications` routes.
- [X] T041 [US2] Extend `stack/modules/Accounting/Http/Controllers/Api/CustomerController.php` with methods for contact/address/group/communication endpoints using the new actions.
- [X] T042 [P] [US2] Add `stack/resources/js/Pages/Accounting/Customers/ContactsTab.vue` with forms/dialogs powered by PrimeVue components.
- [X] T043 [P] [US2] Add `stack/resources/js/Pages/Accounting/Customers/AddressesTab.vue` including default toggles and map to billing/shipping types.
- [X] T044 [P] [US2] Add `stack/resources/js/Pages/Accounting/Customers/CommunicationsTab.vue` rendering timeline entries with filters.
- [X] T045 [US2] Update `stack/resources/js/Pages/Accounting/Customers/Show.vue` to register new tabs and wire data props from the API.
- [X] T046 [US2] Add CLI commands (`customer:contact:add`, `customer:contact:list`, etc.) under `stack/modules/Accounting/CLI/Commands/` and register them in `CommandSeeder.php`.
- [X] T047 [US2] Update `stack/database/seeders/PermissionSeeder.php` and role assignments to include `accounting.customers.manage_contacts`, `.manage_groups`, and `.manage_comms`.
- [X] T048 [US2] Apply new permission checks across API controllers, Inertia responses, and CLI commands to enforce RBAC boundaries.

**Checkpoint**: User Stories 1 and 2 operate independently with contacts, addresses, groups, and communications managed securely.

---

## Phase 5: User Story 3 - Credit Limits & Balance Enforcement (Priority: P3)

**Goal**: Credit officers can adjust customer credit limits, and invoice creation enforces those limits with auditability and telemetry.

**Independent Test**: Adjust a customer’s credit limit via CLI, attempt to create invoices exceeding the limit, and confirm enforcement plus telemetry counters.

### Tests for User Story 3 — write first, ensure they fail ⚠️

- [X] T049 [P] [US3] Add red PHPUnit test `stack/tests/Feature/Accounting/Customers/AdjustCustomerCreditLimitTest.php` verifying action workflow and audit records.
- [X] T050 [P] [US3] Add red PHPUnit test `stack/tests/Feature/Invoicing/InvoiceCreditLimitEnforcementTest.php` ensuring invoice creation fails when exposure exceeds limit.

### Implementation for User Story 3

- [X] T051 [US3] Create migration `stack/database/migrations/2025_10_15_120200_create_customer_credit_limits_table.php` with history tracking and RLS policies.
- [X] T052 [US3] Add `CustomerCreditLimit` model in `stack/modules/Accounting/Domain/Customers/Models/CustomerCreditLimit.php` with scopes for active limits.
- [X] T053 [US3] Implement `AdjustCustomerCreditLimitAction` handling approvals, effective/expiry, and customer record sync.
- [X] T054 [US3] Implement `CustomerCreditService` in `stack/modules/Accounting/Domain/Customers/Services/CustomerCreditService.php` calculating exposure and providing enforcement helpers.
- [X] T055 [US3] Inject credit enforcement into `stack/modules/Invoicing/Services/InvoiceService.php` prior to invoice persistence, including override pathways with audit logging.
- [X] T056 [US3] Extend `stack/modules/Accounting/Http/Controllers/Api/CustomerController.php` to service `/customers/{id}/credit-limit` endpoints using the new action/service.
- [X] T057 [US3] Add CLI command `customer:credit:adjust` under `stack/modules/Accounting/CLI/Commands/CustomerCreditAdjust.php` with JSON output and idempotency key support.
- [X] T058 [US3] Introduce `stack/modules/Accounting/Domain/Customers/Telemetry/CreditMetrics.php` incrementing `customer_credit_breach_total` and other counters.
- [X] T059 [P] [US3] Build `stack/resources/js/Pages/Accounting/Customers/CreditLimitDialog.vue` providing adjustment UI with exposure summary.
- [X] T060 [US3] Update `stack/resources/js/Pages/Accounting/Customers/Show.vue` to embed the credit summary card, open the credit dialog, and display enforcement warnings.
- [X] T061 [US3] Extend `stack/resources/js/locales/en-US/customers.json` with credit limit messaging, error strings, and telemetry labels.

**Checkpoint**: Credit governance is in place—limits managed centrally, enforced during invoicing, and instrumented.

---

## Phase 6: User Story 4 - Aging, Statements & Import/Export (Priority: P4)

**Goal**: Collections teams can generate statements, review aging buckets, and run customer import/export workflows with CLI parity.

**Independent Test**: Generate a statement for a customer, refresh aging snapshots, and export/import customer data; verify outputs via API, CLI, and UI.

### Tests for User Story 4 — write first, ensure they fail ⚠️

- [ ] T062 [P] [US4] Add red PHPUnit test `stack/tests/Unit/Accounting/Customers/CustomerAgingServiceTest.php` validating bucket math and filtering.
- [ ] T063 [P] [US4] Add red PHPUnit feature test `stack/tests/Feature/Accounting/Customers/GenerateCustomerStatementTest.php` covering statement action, document path, and audit.

### Implementation for User Story 4

- [ ] T064 [US4] Create migration `stack/database/migrations/2025_10_15_120300_create_customer_statements_table.php` with uniqueness and RLS policies.
- [ ] T065 [P] [US4] Create migration `stack/database/migrations/2025_10_15_120310_create_customer_aging_snapshots_table.php` with bucket columns and RLS.
- [ ] T066 [US4] Implement `CustomerAgingService` in `stack/modules/Accounting/Domain/Customers/Services/CustomerAgingService.php` deriving buckets from invoices/payments.
- [ ] T067 [US4] Implement `CustomerStatementService` in `stack/modules/Accounting/Domain/Customers/Services/CustomerStatementService.php` assembling period summaries and documents.
- [ ] T068 [US4] Implement actions `GenerateCustomerStatementAction` and `RefreshCustomerAgingSnapshotAction` under `stack/modules/Accounting/Domain/Customers/Actions/`.
- [ ] T069 [US4] Extend `stack/modules/Accounting/Domain/Customers/Actions/registry.php` and `stack/config/command-bus.php` with statement, aging, import, and export action IDs.
- [ ] T070 [US4] Add queued job `stack/modules/Accounting/Domain/Customers/Jobs/UpdateCustomerAgingJob.php` and CLI command `ar:update-aging` to trigger snapshots (ensure `bootstrap/app.php` schedule references the new command).
- [ ] T071 [US4] Update `stack/modules/Accounting/Http/Controllers/Api/CustomerController.php` with `/customers/{id}/aging` and `/customers/{id}/statements` endpoints returning service output.
- [ ] T072 [US4] Implement `ImportCustomersAction` and `ExportCustomersAction` handling CSV/XLSX parsing and streaming in `stack/modules/Accounting/Domain/Customers/Actions/`.
- [ ] T073 [US4] Wire `/customers/import` and `/customers/export` routes plus controller methods (API + Inertia) using the new actions and idempotency keys.
- [ ] T074 [US4] Add CLI commands `customer:import` and `customer:export` under `stack/modules/Accounting/CLI/Commands/` and register them in `CommandSeeder.php`.
- [ ] T075 [P] [US4] Add `stack/resources/js/Pages/Accounting/Customers/AgingTab.vue` rendering bucket charts and refresh controls.
- [ ] T076 [P] [US4] Add `stack/resources/js/Pages/Accounting/Customers/StatementsTab.vue` with statement history, document downloads, and generation dialog.
- [ ] T077 [US4] Update `stack/resources/js/Pages/Accounting/Customers/Index.vue` with export/import actions and progress feedback.
- [ ] T078 [US4] Update `stack/resources/js/Pages/Accounting/Customers/Show.vue` to surface aging and statement tabs plus snapshot metadata.
- [ ] T079 [US4] Extend `stack/resources/js/locales/en-US/customers.json` with aging/statement/import/export copy.

**Checkpoint**: Full customer lifecycle is delivered—aging, statements, and data exchange operate with parity across surfaces.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Improvements that affect multiple user stories

- [X] T080 Update `specs/006-customer-management-customer-work/quickstart.md` with final CLI commands, UI flows, and verification steps.
- [X] T081 Populate `docs/CUSTOMER-LIFECYCLE-COMPLIANCE.md` with RBAC matrices, RLS policy references, telemetry screenshots, and performance metrics.
- [X] T082 Record decisions and follow-ups in `docs/TEAM_MEMORY.md` under the Customer Lifecycle section.
- [X] T083 Run the documented quickstart workflow end-to-end (migrate, seed, CLI, UI) and capture results in `docs/CUSTOMER-LIFECYCLE-COMPLIANCE.md`.
- [X] T084 Review observability dashboards to validate new metrics/logs, tuning thresholds or alerts as needed in `docs/monitoring/`.

---

## Dependencies & Execution Order

### Phase Dependencies
- **Setup (Phase 1)**: Independent; establishes scaffolding for the feature.
- **Foundational (Phase 2)**: Depends on Setup; blocks all user stories by wiring providers and command bus plumbing.
- **User Stories (Phases 3–6)**: Each depends on Foundational completion. Stories may run in priority order (P1 → P2 → P3 → P4) or in parallel once shared prerequisites are done.
- **Polish (Phase 7)**: Requires all targeted user stories to be complete before documentation and validation can finish.

### User Story Dependencies
- **US1 (P1)**: No prior story dependencies; delivers MVP.
- **US2 (P2)**: Builds on US1 data structures (Show.vue tabs, registry) but remains independently testable.
- **US3 (P3)**: Requires US1 (for core customer actions) and integrates with Invoice service; independent tests validate enforcement.
- **US4 (P4)**: Depends on US1 for base profile and on aged invoices/payments; operates independently via dedicated services/jobs.

### Within Each User Story
- Tests (T006–T007, T026–T027, T049–T050, T062–T063) must be authored and failing before implementation tasks begin.
- Migrations precede models, which precede services/actions, followed by API/CLI/UI work.
- Story checkpoints confirm independent deliverability before progressing.

### Parallel Opportunities
- Setup tasks (T001–T003) can execute concurrently.
- Foundational tasks modify distinct files and may run sequentially to avoid merge conflicts.
- Within each story, tasks marked [P] target distinct files (e.g., multiple action classes, Vue components) allowing safe parallel development.
- Different user stories can proceed in parallel after Phase 2 if staffed, provided shared files (e.g., `Show.vue`, `customers.json`) are coordinated sequentially.

---

## Parallel Example: User Story 1

```bash
# Run fail-first backend and CLI tests together:
# T006 and T007 target different suites and can execute in parallel.

# Develop UI pieces concurrently:
# T020 (Index.vue) and T021 (Form.vue) are independent Vue components.
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)
1. Complete Phases 1–2 to wire scaffolding and command bus plumbing.
2. Deliver Phase 3 (US1) end-to-end: migrations, actions, API/CLI/UI, translations.
3. Execute independent tests (PHPUnit + CLI) and front-end smoke checks.
4. Demo/deploy MVP focused on core customer master data.

### Incremental Delivery
1. After MVP, layer Phase 4 (US2) for contacts/communications; validate tabs and RBAC.
2. Add Phase 5 (US3) for credit governance; verify enforcement before merging.
3. Add Phase 6 (US4) for aging/statements/import/export; confirm scheduler and data outputs.
4. Polish (Phase 7) finalizes compliance, docs, and observability.

### Parallel Team Strategy
1. Team pairs on Setup and Foundational tasks to unblock everyone quickly.
2. Developer A owns US1 while Developers B and C spike tests/migrations for US2 and US3 respectively.
3. Once US1 stabilizes, Developers B/C integrate their changes, keeping shared-file updates (e.g., `Show.vue`, `customers.json`) serialized.
4. US4 can begin once aging/statement services scaffolding is ready, with a dedicated developer handling jobs and import/export flows.
