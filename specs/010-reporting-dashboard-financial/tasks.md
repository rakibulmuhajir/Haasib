# Tasks: Reporting Dashboard - Financial & KPI

**Input**: Design documents from `/specs/010-reporting-dashboard-financial/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: Not explicitly requested in the specification. Add story-specific test tasks later if product owners require TDD.

**Organization**: Tasks are grouped by phase, with user stories in priority order. Each user story is independently testable once its phase completes.

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Establish module scaffolding and configuration required before any reporting work can start.

- [ ] T001 Create reporting module service provider in `stack/modules/Reporting/Providers/ReportingServiceProvider.php`
- [ ] T002 Register reporting module namespace and provider in `stack/config/modules.php` and `stack/composer.json`
- [ ] T003 Add baseline module configuration (feature flag, permissions map) in `stack/modules/Reporting/config/module.php`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that all user stories depend on. Must be completed before story work.

- [ ] T004 Create migration `stack/database/migrations/2025_10_20_000500_create_reporting_core_tables.php` for `rpt.report_templates`, `rpt.reports`, `rpt.financial_statements`, `rpt.financial_statement_lines`
- [ ] T005 Create migration `stack/database/migrations/2025_10_20_000600_create_reporting_kpi_and_schedule_tables.php` for `rpt.kpi_definitions`, `rpt.kpi_snapshots`, `rpt.dashboard_layouts`, `rpt.report_schedules`, `rpt.report_deliveries`
- [ ] T006 Harden reporting schema with RLS, audit triggers, and refresh helpers in `stack/database/migrations/2025_10_20_000700_add_reporting_rls_and_audit.php`
- [ ] T007 Seed reporting permissions for Owner/Accountant/Viewer roles in `stack/database/seeders/CompanyPermissionsSeeder.php`
- [ ] T008 Configure cache + queue channels/TTL for reporting workloads in `stack/config/cache.php` and `stack/config/queue.php`
- [ ] T009 Register reporting command bus actions namespace in `stack/config/command-bus.php`

**Checkpoint**: Reporting schema, permissions, and infrastructure are in place. User story work can begin.

---

## Phase 3: User Story 1 – Real-Time Financial Dashboard (Priority: P1) 🎯 MVP

**Goal**: Deliver a live KPI dashboard with <5s freshness, including caching, command bus refresh, and Inertia UI.

**Independent Test**: With seeded KPI definitions, calling `GET /api/reporting/dashboard?layout_id={id}` returns populated widgets within SLA, and manual refresh via the UI invalidates caches and reflects new ledger activity.

- [ ] T010 [P] [US1] Implement `DashboardMetricsService` aggregations using materialized views in `stack/modules/Reporting/Services/DashboardMetricsService.php`
- [ ] T011 [P] [US1] Add dashboard cache helper enforcing 5s TTL in `stack/modules/Reporting/Services/DashboardCacheService.php`
- [ ] T012 [US1] Implement `reporting.dashboard.refresh` command + queued job in `stack/modules/Reporting/Actions/Dashboard/RefreshDashboardAction.php` and `stack/modules/Reporting/Jobs/RefreshDashboardJob.php`
- [ ] T013 [US1] Expose dashboard API + refresh endpoint in `stack/app/Http/Controllers/Reporting/DashboardController.php` and register routes in `stack/routes/api.php`
- [ ] T014 [US1] Build Inertia dashboard page with PrimeVue charts in `stack/resources/js/Pages/Reporting/Dashboard/Index.vue`
- [ ] T015 [P] [US1] Create front-end data service for dashboard calls in `stack/resources/js/services/reportingDashboard.ts`
- [ ] T016 [US1] Wire web route and navigation entry for reporting dashboard in `stack/routes/web.php` and `stack/resources/js/Layouts/AuthenticatedLayout.vue`

**Checkpoint**: The dashboard can be refreshed via command bus or UI, honoring SLA and permissions.

---

## Phase 4: User Story 2 – On-Demand Financial Statements & Trial Balance (Priority: P1)

**Goal**: Provide API + UI to generate income statement, balance sheet, cash flow, and trial balance with drill-down under 10 seconds.

**Independent Test**: Triggering `POST /api/reporting/reports` for income statement queues a job, and `GET /api/reporting/reports/{id}` returns finished payload with links to drill-down data; UI renders statements and trial balance filtered by period.

- [ ] T017 [P] [US2] Implement financial statement builder with comparative data in `stack/modules/Reporting/Services/FinancialStatementService.php`
- [ ] T018 [P] [US2] Implement trial balance query + variance analysis in `stack/modules/Reporting/Services/TrialBalanceService.php`
- [ ] T019 [US2] Add `reporting.report.generate` command + async job in `stack/modules/Reporting/Actions/Reports/GenerateReportAction.php` and `stack/modules/Reporting/Jobs/GenerateReportJob.php`
- [ ] T020 [US2] Implement report controller for create/show/delete in `stack/app/Http/Controllers/Reporting/ReportController.php` and register routes in `stack/routes/api.php`
- [ ] T021 [US2] Build transaction drill-down query helper in `stack/modules/Reporting/QueryBuilders/TransactionDrilldownQuery.php`
- [ ] T022 [US2] Create Inertia statements view with drill-down modals in `stack/resources/js/Pages/Reporting/Statements/Index.vue`
- [ ] T023 [P] [US2] Add front-end statements API wrapper in `stack/resources/js/services/reportingStatements.ts`

**Checkpoint**: Financial statements and trial balance can be generated, retrieved, and viewed independently of other stories.

---

## Phase 5: User Story 3 – Report Templates, Exports, and Scheduling (Priority: P2)

**Goal**: Allow power users to customize templates, export reports, and configure scheduled deliveries with audit coverage.

**Independent Test**: Creating a template via API, scheduling a monthly export, and verifying delivery status in UI succeeds without touching other stories.

- [ ] T024 [P] [US3] Implement `ReportTemplateService` with validation + visibility rules in `stack/modules/Reporting/Services/ReportTemplateService.php`
- [ ] T025 [US3] Add template controller for CRUD endpoints in `stack/app/Http/Controllers/Reporting/ReportTemplateController.php` and register routes in `stack/routes/api.php`
- [ ] T026 [US3] Implement scheduling commands/jobs (`reporting.schedule.run`) in `stack/modules/Reporting/Actions/Schedules/CreateReportScheduleAction.php` and `stack/modules/Reporting/Jobs/RunScheduledReportsJob.php`
- [ ] T027 [US3] Build schedule controller covering create/update/delete in `stack/app/Http/Controllers/Reporting/ReportScheduleController.php` and expose routes in `stack/routes/api.php`
- [ ] T028 [US3] Implement report delivery + download token flow in `stack/modules/Reporting/Actions/Reports/DeliverReportAction.php` and update export route handler in `stack/routes/api.php`
- [ ] T029 [US3] Build Inertia template management UI in `stack/resources/js/Pages/Reporting/Templates/Index.vue`
- [ ] T030 [P] [US3] Build schedule management UI with delivery logs in `stack/resources/js/Pages/Reporting/Schedules/Index.vue`

**Checkpoint**: Templates, exports, and schedules function independently and respect permissions.

---

## Phase 6: User Story 4 – Multi-Currency & Advanced Insights (Priority: P3)

**Goal**: Extend reporting to support currency overrides, aging KPIs, and budget vs actual comparisons.

**Independent Test**: Requesting a report with `currency=EUR` returns translated values with documented exchange rates, and dashboard widgets show aging + budget metrics for the selected period.

- [ ] T031 [P] [US4] Extend statement and trial balance services for currency conversion snapshots in `stack/modules/Reporting/Services/FinancialStatementService.php` and `stack/modules/Reporting/Services/TrialBalanceService.php`
- [ ] T032 [P] [US4] Implement KPI computation extensions for aging and budget metrics in `stack/modules/Reporting/Services/KpiComputationService.php`
- [ ] T033 [US4] Update API controllers to accept currency/comparison filters and aging endpoints in `stack/app/Http/Controllers/Reporting/ReportController.php` and `stack/app/Http/Controllers/Reporting/DashboardController.php`
- [ ] T034 [US4] Enhance dashboard & statements UI for multi-currency toggles and aging/budget widgets in `stack/resources/js/Pages/Reporting/Dashboard/Index.vue` and `stack/resources/js/Pages/Reporting/Statements/Index.vue`

**Checkpoint**: Advanced insights operate independently and can be toggled off if only MVP is required.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Consolidate documentation, monitoring, and release readiness once desired stories are complete.

- [ ] T035 [P] Update operational playbook with reporting metrics in `docs/monitoring/reporting-dashboard.md`
- [ ] T036 Document end-to-end workflow in `specs/010-reporting-dashboard-financial/quickstart.md`
- [ ] T037 Run reporting module smoke test script and capture results in `docs/tasks.md`

---

## Dependencies & Execution Order

1. **Setup (Phase 1)** → must finish before foundational work.
2. **Foundational (Phase 2)** → blocks all user stories; ensures schema, permissions, and command bus wiring exist.
3. **User Stories**:
   - **US1 (P1)** and **US2 (P1)** can begin immediately after Phase 2; tackle US1 first for MVP delivery.
   - **US3 (P2)** depends on completion of US2’s report generation infrastructure.
   - **US4 (P3)** depends on US1/US2 services to extend currency and KPI logic.
4. **Polish (Phase 7)** runs after chosen user stories close; primarily documentation and validation.

This forms the graph: `Setup → Foundational → {US1, US2} → US3 → US4 → Polish`, with US4 optionally deferred if MVP scope stops at US3.

---

## Parallel Execution Examples

### User Story 1
```bash
# Backend engineers
Implement DashboardMetricsService (T010)
Implement RefreshDashboardAction + job (T012)

# Frontend engineers
Create reportingDashboard.ts service (T015)
Build Dashboard Index.vue (T014)
```

### User Story 2
```bash
# Backend engineers
Implement FinancialStatementService (T017)
Implement GenerateReportAction + job (T019)

# Frontend engineers
Build Statements Index.vue (T022)
Wire statements service wrapper (T023)
```

### User Story 3
```bash
# Backend engineers
Implement ReportTemplateService + controller (T024, T025)
Implement scheduling job + controller (T026, T027)

# Frontend engineers
Build Templates page (T029)
Build Schedules page (T030)
```

### User Story 4
```bash
# Backend engineers
Extend currency conversion logic (T031)
Implement advanced KPI computations (T032)

# Frontend engineers
Update dashboards for currency toggles (T034)
```

---

## Implementation Strategy

1. **MVP First**: Complete Phases 1–4 (through US1) to deliver a working dashboard with live data refresh—this is the suggested MVP scope.
2. **Core Reporting**: Expand to US2 to unlock statements/trial balance, enabling finance teams to adopt the platform for month-end closes.
3. **Customization & Automation**: Deliver US3 for template management and scheduling once core flows stabilize.
4. **Advanced Insights**: Implement US4 last to add multi-currency and analytical depth without jeopardizing earlier delivery.
5. **Polish**: Wrap with documentation, monitoring hooks, and smoke validation to ensure operability.

---

**Validation**: All tasks follow the required checklist format (`- [ ] T### [P?] [Story?] Description with file path`). Each user story phase includes independent test criteria, clear dependencies, and parallel work examples. The plan enables incremental delivery while keeping stories independently testable.
