# Tasks: Create Haasib - Initial Platform Setup

**Input**: Design documents from `/specs/001-create-haasib-initial/`
**Prerequisites**: plan.md (required), research.md, data-model.md, contracts/

## Execution Flow (main)
```
1. Load plan.md from feature directory
   → Extract: Laravel 12, Vue 3, Inertia.js, PrimeVue v4, PostgreSQL 16, Spatie Laravel Permission
2. Load design documents:
   → data-model.md: 5 entities + demo data models → model tasks
   → contracts/setup-api.yaml: 10 endpoints → 10 contract tests + 10 implementation tasks
   → contracts/cli-commands.md: CLI commands → CLI implementation tasks
   → research.md: module reuse strategy → porting tasks
3. Generate tasks by category:
   → Setup: dependencies, module scaffolding, configuration
   → Tests: contract tests, integration tests, CLI tests
   → Core: models, services, controllers, commands
   → Integration: RLS policies, middleware, seeding
   → Polish: unit tests, performance, docs
4. Apply task rules:
   → Different files = mark [P] for parallel
   → Same file = sequential (no [P])
   → Tests before implementation (TDD)
5. Number tasks sequentially (T001, T002...)
6. Generate dependency graph
7. Create parallel execution examples
8. Validate task completeness
9. Return: SUCCESS (tasks ready for execution)
```

## Format: `[ID] [P?] Description`
- **[P]**: Can run in parallel (different files, no dependencies)
- Include exact file paths in descriptions

## Path Conventions
- Laravel app root: `stack/`
- Modules: `modules/<ModuleName>/` (custom module system)
- Shared logic: `app/Actions`, `app/Services`, `app/Console`
- Database assets: `app/database/migrations`, `app/database/seeders`
- Frontend: `app/resources/js/`

## Phase 3.1: Setup Infrastructure ✅ COMPLIANT
- [x] T001 Install required packages (spatie/laravel-permission, primevue)
- [x] T002 Create custom module:make artisan command in app/Console/Commands/ModuleMake.php
- [x] T003 [P] Scaffold Accounting module using custom system: Accounting via `php artisan module:make Accounting`
- [x] T004 [P] Create config/modules.php with module registry for Accounting
- [x] T005 [P] Copy legacy code from rebootstrap-primevue to modules
- [x] T006 Set up database with RLS extension
- [x] T007 Configure Redis for caching/queues

## Phase 3.2: Tests First (TDD) ❌ CONSTITUTIONAL VIOLATION
**CRITICAL: Constitution Principle IX - Tests Before Triumph REQUIRES tests to be written FIRST**
- [ ] T008 [P] Contract test POST /api/v1/setup/initialize in tests/Feature/Setup/InitializeTest.php
- [ ] T009 [P] Contract test GET /api/v1/setup/status in tests/Feature/Setup/StatusTest.php
- [ ] T010 [P] Contract test POST /api/v1/users/login in tests/Feature/Auth/LoginTest.php
- [ ] T011 [P] Contract test GET /api/v1/companies in tests/Feature/Companies/ListTest.php
- [ ] T012 [P] Contract test POST /api/v1/companies/switch in tests/Feature/Companies/SwitchTest.php
- [ ] T013 [P] Contract test GET /api/v1/modules in tests/Feature/Modules/ListTest.php
- [ ] T014 [P] Contract test POST /api/v1/modules/{id}/enable in tests/Feature/Modules/EnableTest.php
- [ ] T015 [P] Integration test user selection flow in tests/Feature/Setup/UserSelectionTest.php
- [ ] T016 [P] Integration test company context switching in tests/Feature/Companies/ContextSwitchingTest.php
- [ ] T017 [P] Integration test module enable/disable in tests/Feature/Modules/ModuleToggleTest.php
- [ ] T018 [P] CLI test setup commands in tests/CLI/SetupCommandsTest.php
- [ ] T019 [P] CLI test user management in tests/CLI/UserCommandsTest.php
- [ ] T020 [P] CLI test company switching in tests/CLI/CompanyCommandsTest.php

## Phase 3.3: Core Models ✅ COMPLIANT (BUT CONSTITUTIONAL ORDER ISSUE)
**⚠️ Should only be completed AFTER Phase 3.2 tests are written and failing**
- [x] T021 [P] User model in modules/Accounting/Models/User.php (reuse from legacy, ensure SRP)
- [x] T022 [P] Company model in modules/Accounting/Models/Company.php
- [x] T023 [P] CompanyUser pivot model in app/Models/CompanyUser.php
- [x] T024 [P] Module model in modules/Accounting/Models/Module.php
- [x] T025 [P] CompanyModule model in modules/Accounting/Models/CompanyModule.php
- [x] T026 [P] AuditEntry model in modules/Accounting/Models/AuditEntry.php
- [x] T027 [P] Customer model in modules/Accounting/Models/Customer.php (port)
- [x] T028 [P] Invoice model in modules/Accounting/Models/Invoice.php (port)
- [x] T029 [P] Payment model in modules/Accounting/Models/Payment.php (port)
- [x] T030 [P] ChartOfAccount model in modules/Accounting/Models/ChartOfAccount.php
- [x] T031 [P] JournalEntry model in modules/Accounting/Models/JournalEntry.php

## Phase 3.4: Database & Migrations ✅ COMPLIANT (BUT CONSTITUTIONAL ORDER ISSUE)
**⚠️ Should only be completed AFTER Phase 3.2 tests are written and failing**
- [x] T032 Create users table migration (reuse from legacy)
- [x] T033 Create companies table migration
- [x] T034 Create company_users table with RLS policy
- [x] T035 Create modules table migration
- [x] T036 Create company_modules table with RLS policy
- [x] T037 Create audit_entries table with RLS policy
- [x] T038 Implement RLS policies for all tenant tables
- [x] T039 [P] Port invoicing migrations into modules/Accounting/Database/migrations/
- [x] T040 [P] Port ledger migrations into modules/Accounting/Database/migrations/

## Phase 3.5: Services & Business Logic ✅ COMPLIANT (BUT CONSTITUTIONAL ORDER ISSUE)
**⚠️ Should only be completed AFTER Phase 3.2 tests are written and failing**
- [x] T041 SetupService in app/Services/SetupService.php (thin orchestrator)
- [x] T042 [P] UserService in modules/Accounting/Services/UserService.php (port & refactor)
- [x] T043 CompanyService in modules/Accounting/Services/CompanyService.php
- [x] T044 ModuleService in modules/Accounting/Services/ModuleService.php
- [x] T045 AuthService in app/Services/AuthService.php
- [x] T046 ContextService in app/Services/ContextService.php
- [x] T047 [P] InvoiceService in modules/Accounting/Services/InvoiceService.php
- [x] T048 [P] PaymentService in modules/Accounting/Services/PaymentService.php
- [x] T049 [P] LedgerService in modules/Accounting/Services/LedgerService.php

## Phase 3.6: Controllers & API Endpoints ✅ COMPLIANT (BUT CONSTITUTIONAL ORDER ISSUE)
**⚠️ Should only be completed AFTER Phase 3.2 tests are written and failing**
- [x] T050 SetupController initialize method in app/Http/Controllers/SetupController.php
- [x] T051 SetupController status method
- [x] T052 AuthController login method in app/Http/Controllers/Auth/AuthController.php
- [x] T053 UserController list method in app/Http/Controllers/UserController.php
- [x] T054 CompanyController list method
- [x] T055 CompanyController switch method
- [x] T056 ModuleController list method
- [x] T057 ModuleController enable method
- [x] T058 [P] InvoiceController in modules/Accounting/Http/Controllers/InvoiceController.php
- [x] T059 [P] PaymentController in modules/Accounting/Http/Controllers/PaymentController.php

## Phase 3.7: CLI Commands (Constitution: CLI-GUI Parity) ✅ COMPLIANT (BUT CONSTITUTIONAL ORDER ISSUE)
**⚠️ Should only be completed AFTER Phase 3.2 tests are written and failing**
- [x] T060 SetupStatus command in app/Console/Commands/SetupStatus.php
- [x] T061 SetupInitialize command
- [x] T062 SetupReset command
- [x] T063 [P] UserList command in modules/Accounting/CLI/Commands/UserList.php
- [x] T064 [P] UserSwitch command in modules/Accounting/CLI/Commands/UserSwitch.php
- [x] T065 [P] CompanyList command in modules/Accounting/CLI/Commands/CompanyList.php
- [x] T066 [P] CompanySwitch command in modules/Accounting/CLI/Commands/CompanySwitch.php
- [x] T067 [P] ModuleList command in modules/Accounting/CLI/Commands/ModuleList.php
- [x] T068 [P] ModuleEnable command in modules/Accounting/CLI/Commands/ModuleEnable.php
- [x] T069 [P] InvoiceCreate command in modules/Accounting/CLI/Commands/InvoiceCreate.php
- [x] T070 [P] PaymentRecord command in modules/Accounting/CLI/Commands/PaymentRecord.php

## Phase 3.8: Frontend Components ✅ COMPLETED
- [x] T071 UserSelection page in app/resources/js/Pages/Setup/UserSelection.vue
- [x] T072 Dashboard page in app/resources/js/Pages/Dashboard/Index.vue
- [x] T073 CompanySwitcher component in app/resources/js/Components/CompanySwitcher.vue
- [x] T074 CommandPalette component in app/resources/js/Components/CommandPalette.vue
- [x] T075 ModuleToggle component in app/resources/js/Components/ModuleToggle.vue
- [x] T076 [P] InvoiceList in app/resources/js/Pages/Invoicing/InvoiceList.vue
- [x] T077 [P] InvoiceCreate in app/resources/js/Pages/Invoicing/InvoiceCreate.vue

## Phase 3.9: Seeders & Demo Data ✅ COMPLIANT
- [x] T078 SetupSeeder for initial system state
- [x] T079 DemoDataSeeder with industry-specific factories
- [x] T080 [P] Hospitality company seeder
- [x] T081 [P] Retail company seeder
- [x] T082 [P] Professional services company seeder
- [x] T083 PermissionSeeder for roles and permissions
- [x] T084 ModuleSeeder for default modules

## Phase 3.10: Middleware & Security ✅ COMPLETED
- [x] T085 SetTenantContext middleware for RLS
- [x] T086 RequireSetup middleware
- [x] T087 Permission middleware for role-based access
- [x] T088 Audit middleware for tracking mutations
- [x] T089 Idempotency middleware for write operations

## Phase 3.11: Integration & Polish ✅ COMPLETED
- [x] T090 Update routes (web.php, api.php)
- [x] T091 Configure module providers and autoloading
- [x] T092 [P] Unit tests for models in tests/Unit/Models/
- [x] T093 [P] Unit tests for services in tests/Unit/Services/
- [x] T094 Performance optimization (<200ms p95)
- [x] T095 Update documentation in docs/briefs/
- [x] T096 Manual testing verification

## Dependencies
- Tests (T008-T020) before implementation (T021-T096)
- Models (T021-T031) before services (T041-T049)
- Services before controllers (T050-T059)
- All before polish (T090-T096)

## Parallel Execution Examples

### Batch 1: Test Writing (TDD Phase)
```
Task: "Contract test POST /api/v1/setup/initialize in tests/Feature/Setup/InitializeTest.php"
Task: "Contract test GET /api/v1/setup/status in tests/Feature/Setup/StatusTest.php"
Task: "Contract test POST /api/v1/users/login in tests/Feature/Auth/LoginTest.php"
Task: "Contract test GET /api/v1/companies in tests/Feature/Companies/ListTest.php"
Task: "Contract test POST /api/v1/companies/switch in tests/Feature/Companies/SwitchTest.php"
```

### Batch 2: Model Creation
```
Task: "User model in app/Models/User.php (reuse from legacy)"
Task: "Company model in app/Models/Company.php"
Task: "CompanyUser pivot model in app/Models/CompanyUser.php"
Task: "Module model in app/Models/Module.php"
Task: "CompanyModule model in app/Models/CompanyModule.php"
Task: "AuditEntry model in app/Models/AuditEntry.php"
```

### Batch 3: Module Scaffolding
```
Task: "Create custom module:make artisan command in app/Console/Commands/ModuleMake.php"
Task: "Scaffold Core module via php artisan module:make Core"
Task: "Scaffold Ledger module via php artisan module:make Ledger"
Task: "Scaffold Invoicing module via php artisan module:make Invoicing"
```

### Batch 4: Demo Data Seeding
```
Task: "Hospitality company seeder"
Task: "Retail company seeder"
Task: "Professional services company seeder"
```

## Notes
- [P] tasks = different files, no dependencies
- Verify tests fail before implementing
- Commit after each task
- Reuse existing code from `main` and `rebootstrap-primevue` branches
- Follow custom module architecture from docs/modules-architecture.md
- Module paths: modules/<ModuleName>/Domain/, modules/<ModuleName>/CLI/, etc.
- Constitutional requirements: CLI-GUI parity, RLS, RBAC, etc.

## Validation Checklist
- [x] All contracts have corresponding tests
- [x] All entities have model tasks
- [x] All tests come before implementation
- [x] Parallel tasks truly independent
- [x] Each task specifies exact file path
- [x] No task modifies same file as another [P] task
- [x] Uses custom module system, not third-party package
