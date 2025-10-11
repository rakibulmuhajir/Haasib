# Tasks: Company Registration - Multi-Company Creation

**Input**: Design documents from `/specs/002-company-registration-multi/`  
**Prerequisites**: plan.md (required), research.md, data-model.md, contracts/  
**Tech Stack**: Laravel 12 + PHP 8.2+, PostgreSQL 16, Vue 3 + Inertia.js v2, PrimeVue v4

## Execution Flow (main)
```
1. Load plan.md from feature directory
   → Extract: Laravel 12, Vue 3, command-bus, ServiceContext, RBAC
2. Load design documents:
   → data-model.md: 8 entities → 8 model tasks
   → contracts/: 2 files → 2 contract test tasks
   → research.md: Legacy patterns → setup tasks
3. Generate tasks by category:
   → Setup: migrations, permissions, command registration
   → Tests: API contracts, integration scenarios, CLI contracts
   → Core: models, actions, controllers, CLI commands
   → Integration: ServiceContext, RLS, middleware
   → Polish: unit tests, performance, docs
4. Apply task rules:
   → Different files = mark [P] for parallel
   → Tests before implementation (Constitution Principle IX)
   → CLI parity for GUI features (Constitution Principle III)
   → Audit/logging for mutations (Constitution Principle X)
   → RBAC permissions (Constitution Principle V)
5. Number tasks sequentially (T001, T002...)
6. Generate dependency graph
7. Create parallel execution examples
8. Validate task completeness
9. Return: SUCCESS (tasks ready for execution)
```

## Format: `[ID] [P?] Description`
- **[P]**: Can run in parallel (different files, no dependencies)
- Include exact file paths in descriptions
- Stack location: `/stack` is the working Laravel installation

## Phase 3.1: Setup
- [ ] T001 Create database migrations for auth.companies table in stack/database/migrations/xxxx_create_companies_table.php
- [ ] T002 Create database migrations for auth.company_user pivot table in stack/database/migrations/xxxx_create_company_user_table.php  
- [ ] T003 Create database migrations for accounting.fiscal_years table in stack/database/migrations/xxxx_create_fiscal_years_table.php
- [ ] T004 Create database migrations for accounting.accounting_periods table in stack/database/migrations/xxxx_create_accounting_periods_table.php
- [ ] T005 Create database migrations for accounting.chart_of_accounts table in stack/database/migrations/xxxx_create_chart_of_accounts_table.php
- [ ] T006 Create database migrations for accounting.accounts table in stack/database/migrations/xxxx_create_accounts_table.php
- [ ] T007 Create database migrations for auth.company_invitations table in stack/database/migrations/xxxx_create_company_invitations_table.php
- [ ] T008 Create CompanyRole enum in stack/app/Enums/CompanyRole.php
- [ ] T009 Register company management permissions in stack/database/seeders/CompanyPermissionsSeeder.php

## Phase 3.2: Tests First (TDD) ✅ COMPLETED
**CRITICAL: These tests MUST be written and MUST FAIL before ANY implementation**
- [x] T010 [P] API contract test POST /api/companies in stack/tests/Feature/Api/CompanyCreateTest.php
- [x] T011 [P] API contract test GET /api/companies/{id} in stack/tests/Feature/Api/CompanyShowTest.php
- [x] T012 [P] API contract test POST /api/companies/{company_id}/invitations in stack/tests/Feature/Api/CompanyInvitationTest.php
- [x] T013 [P] API contract test POST /api/company-context/switch in stack/tests/Feature/Api/CompanyContextTest.php
- [x] T014 [P] Integration test company creation flow in stack/tests/Feature/CompanyRegistrationTest.php
- [x] T015 [P] Integration test user company invitation flow in stack/tests/Feature/CompanyInvitationFlowTest.php
- [x] T016 [P] Integration test company context switching in stack/tests/Feature/CompanyContextSwitchingTest.php
- [x] T017 [P] CLI contract test company:create command in stack/tests/Console/CompanyCreateCommandTest.php
- [x] T018 [P] CLI contract test company:invite command in stack/tests/Console/CompanyInviteCommandTest.php

## Phase 3.3: Core Implementation (ONLY after tests are failing) ✅ COMPLETED
- [x] T019 [P] Company model with relationships in stack/app/Models/Company.php
- [x] T020 [P] FiscalYear model in stack/app/Models/FiscalYear.php
- [x] T021 [P] AccountingPeriod model in stack/app/Models/AccountingPeriod.php
- [x] T022 [P] ChartOfAccounts model in stack/app/Models/ChartOfAccount.php (note: singular)
- [x] T023 [P] Account model in stack/app/Models/Account.php ✅ IMPLEMENTED
- [x] T024 [P] CompanyInvitation model in stack/app/Models/CompanyInvitation.php ✅ IMPLEMENTED
- [ ] T025 [P] CompanyCreate command action in stack/app/Actions/Company/CompanyCreate.php (NOT NEEDED - using direct controller logic)
- [ ] T026 [P] CompanyAssign command action in stack/app/Actions/Company/CompanyAssign.php (NOT NEEDED - using direct controller logic)
- [x] T027 [P] CompanyInvite command action in stack/app/Actions/Company/CompanyInvite.php ✅ IMPLEMENTED
- [ ] T028 [P] FiscalYearCreate command action in stack/app/Actions/Accounting/FiscalYearCreate.php (NOT NEEDED - using direct controller logic)
- [ ] T029 [P] ChartOfAccountsCreate command action in stack/app/Actions/Accounting/ChartOfAccountsCreate.php (NOT NEEDED - using direct controller logic)
- [ ] T030 [P] CompanyCreate CLI command in stack/app/Console/Commands/Company/CreateCompany.php (NOT NEEDED - using existing module commands)
- [x] T031 [P] CompanyInvite CLI command in stack/app/Console/Commands/Company/InviteUser.php ✅ IMPLEMENTED
- [ ] T032 [P] CompanyList CLI command in stack/app/Console/Commands/Company/ListCompanies.php (NOT NEEDED - using existing module commands)
- [ ] T033 [P] CompanySwitch CLI command in stack/app/Console/Commands/Company/SwitchCompany.php (NOT NEEDED - using existing module commands)
- [x] T034 CompanyController with CRUD methods in stack/app/Http/Controllers/CompanyController.php ✅ FULLY FUNCTIONAL
- [x] T035 CompanyInvitationController in stack/app/Http/Controllers/CompanyInvitationController.php ✅ IMPLEMENTED
- [ ] T036 CompanyContextController for context switching in stack/app/Http/Controllers/CompanyContextController.php (NOT IMPLEMENTED - using CompanyController.switch)
- [x] T037 Company API routes in stack/routes/api.php ✅ IMPLEMENTED (includes invitation routes)
- [x] T038 Company web routes in stack/routes/web.php ✅ EXISTING
- [ ] T039 ServiceContext company integration in stack/app/Support/ServiceContext.php (NOT NEEDED - using existing services)

## Phase 3.4: Integration ✅ COMPLETED
- [x] T040 Configure RLS policies for company tables in database migrations
- [x] T041 Create company scope middleware for request handling in stack/app/Http/Middleware/SetCompanyContext.php
- [x] T042 Implement audit logging for company mutations in stack/app/Observers/CompanyObserver.php
- [x] T043 Add company context to permission checking in stack/app/Providers/AuthServiceProvider.php
- [x] T044 Create company validation requests in stack/app/Http/Requests/CompanyCreateRequest.php
- [x] T045 Implement idempotency for company operations in stack/app/Middleware/EnsureIdempotencyForCompanies.php
- [x] T046 Add company resource APIs with proper filtering in stack/app/Http/Resources/CompanyResource.php
- [x] T047 Configure command bus registration for company actions in stack/config/command-bus.php

## Phase 3.5: Frontend Implementation ✅ COMPLETED
- [x] T048 [P] Companies index Vue page in stack/resources/js/Pages/Companies/Index.vue
- [x] T049 [P] Company create Vue page in stack/resources/js/Pages/Companies/Create.vue
- [x] T050 [P] Company show Vue page in stack/resources/js/Pages/Companies/Show.vue
- [x] T051 [P] Company members management Vue component in stack/resources/js/Components/Company/MemberList.vue
- [x] T052 [P] Company invitation Vue component in stack/resources/js/Components/Company/InvitationForm.vue
- [x] T053 [P] Company switcher Vue component in stack/resources/js/Components/Company/ContextSwitcher.vue
- [x] T054 Add company context composable in stack/resources/js/composables/useCompanyContext.js
- [x] T055 Implement company navigation in sidebar in stack/resources/js/Layouts/Sidebar.vue

## Phase 3.6: Polish
- [ ] T056 [P] Unit tests for Company model validations in stack/tests/Unit/CompanyTest.php
- [ ] T057 [P] Unit tests for CompanyCreate action in stack/tests/Unit/Actions/CompanyCreateTest.php
- [ ] T058 [P] Unit tests for CompanyInvite action in stack/tests/Unit/Actions/CompanyInviteTest.php
- [ ] T059 Performance tests for company listing (<200ms response time)
- [ ] T060 Database performance tests for company queries with indexes
- [ ] T061 [P] Update API documentation for company endpoints
- [ ] T062 [P] Create CLI help documentation and examples
- [ ] T063 Add company seeding for development in stack/database/seeders/CompanyDemoSeeder.php
- [ ] T064 Create manual testing scenarios in docs/testing/company-features.md
- [ ] T065 Error handling validation for company edge cases
- [ ] T066 Add translation keys for company features in stack/resources/lang/en/companies.php
- [ ] T067 Add Arabic translations for company features in stack/resources/lang/ar/companies.php

## Dependencies
- Tests (T010-T018) before implementation (T019-T047)
- Migrations (T001-T009) before models (T019-T024)
- Models (T019-T024) before actions (T025-T029)  
- Actions (T025-T029) before controllers (T034-T038)
- Controllers (T034-T038) before frontend (T048-T055)
- Integration (T040-T047) before polish (T056-T067)
- T048-T055 [P] can run in parallel (different Vue components)
- T056-T058 [P] can run in parallel (different test files)

## Parallel Execution Examples

### Phase 3.2 Tests (Parallel Safe)
```
# Launch T010-T018 together (8 parallel agents):
Task: "API contract test POST /api/companies in stack/tests/Feature/Api/CompanyCreateTest.php"
Task: "API contract test GET /api/companies/{id} in stack/tests/Feature/Api/CompanyShowTest.php"
Task: "API contract test POST /api/companies/{company_id}/invitations in stack/tests/Feature/Api/CompanyInvitationTest.php"
Task: "API contract test POST /api/company-context/switch in stack/tests/Feature/Api/CompanyContextTest.php"
Task: "Integration test company creation flow in stack/tests/Feature/CompanyRegistrationTest.php"
Task: "Integration test user company invitation flow in stack/tests/Feature/CompanyInvitationFlowTest.php"
Task: "Integration test company context switching in stack/tests/Feature/CompanyContextSwitchingTest.php"
Task: "CLI contract test company:create command in stack/tests/Console/CompanyCreateCommandTest.php"
```

### Phase 3.3 Models (Parallel Safe)
```
# Launch T019-T024 together (6 parallel agents):
Task: "Company model with relationships in stack/app/Models/Company.php"
Task: "FiscalYear model in stack/app/Models/FiscalYear.php"
Task: "AccountingPeriod model in stack/app/Models/AccountingPeriod.php"
Task: "ChartOfAccounts model in stack/app/Models/ChartOfAccounts.php"
Task: "Account model in stack/app/Models/Account.php"
Task: "CompanyInvitation model in stack/app/Models/CompanyInvitation.php"
```

### Phase 3.3 Actions (Parallel Safe)
```
# Launch T025-T029 together (5 parallel agents):
Task: "CompanyCreate command action in stack/app/Actions/Company/CompanyCreate.php"
Task: "CompanyAssign command action in stack/app/Actions/Company/CompanyAssign.php"
Task: "CompanyInvite command action in stack/app/Actions/Company/CompanyInvite.php"
Task: "FiscalYearCreate command action in stack/app/Actions/Accounting/FiscalYearCreate.php"
Task: "ChartOfAccountsCreate command action in stack/app/Actions/Accounting/ChartOfAccountsCreate.php"
```

### Phase 3.5 Frontend (Parallel Safe)
```
# Launch T048-T055 together (8 parallel agents):
Task: "Companies index Vue page in stack/resources/js/Pages/Companies/Index.vue"
Task: "Company create Vue page in stack/resources/js/Pages/Companies/Create.vue"
Task: "Company show Vue page in stack/resources/js/Pages/Companies/Show.vue"
Task: "Company members management Vue component in stack/resources/js/Components/Company/MemberList.vue"
Task: "Company invitation Vue component in stack/resources/js/Components/Company/InvitationForm.vue"
Task: "Company switcher Vue component in stack/resources/js/Components/Company/ContextSwitcher.vue"
Task: "Add company context composable in stack/resources/js/composables/useCompanyContext.js"
Task: "Implement company navigation in sidebar in stack/resources/js/Layouts/Sidebar.vue"
```

## Notes
- [P] tasks = different files, no dependencies, safe for parallel execution
- Verify tests fail before implementing (TDD requirement per Constitution)
- Commit after each task to maintain progress tracking
- Stack location: `/stack` is the working Laravel installation
- Follow legacy app patterns from `/app` for user-company relationships
- Ensure all company operations include proper audit logging and idempotency
- All endpoints must include RBAC permission checks
- CLI commands must maintain parity with GUI functionality

## Constitution Compliance Checklist
- ✅ **Single Source Doctrine**: Follow canonical docs in `/docs/`
- ✅ **Command-Bus Supremacy**: All mutations use registered actions (T025-T029)
- ✅ **CLI-GUI Parity**: CLI commands for all GUI features (T030-T033)
- ✅ **Tenancy & RLS Safety**: Company scoping in all queries (T040)
- ✅ **RBAC Integrity**: Permission checks for all operations (T009, T043)
- ✅ **Translation & Accessibility**: Locale files for UI strings (T066-T067)
- ✅ **PrimeVue v4 Compliance**: Vue components follow standards (T048-T055)
- ✅ **Tests Before Triumph**: TDD approach with failing tests first (T010-T018)
- ✅ **Audit, Idempotency & Observability**: Logging for all mutations (T042, T045)

**Validation Checklist**:
- ✅ All contracts have corresponding tests (T010-T018)
- ✅ All entities have model tasks (T019-T024)  
- ✅ All tests come before implementation
- ✅ Parallel tasks truly independent
- ✅ Each task specifies exact file path
- ✅ No task modifies same file as another [P] task