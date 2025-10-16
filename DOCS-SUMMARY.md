# Documentation Summary & Analysis

**Generated**: 2025-10-16  
**Purpose**: Comprehensive overview of project documentation for onboarding senior developers

---

## Executive Summary

**LedgerFly/Haasib** is a hybrid accounting platform combining CLI-first speed with GUI accessibility, targeting SMEs, freelancers, and accountants. The system delivers double-entry accounting rigor through a Laravel 12 + Vue 3 modular monolith with PostgreSQL row-level security, emphasizing simplicity, compliance, and multi-company support.

---

## 1. Application Type & Core Purpose

### What It Is
- **Hybrid Accounting SaaS Platform** with dual interfaces:
  - **CLI-first**: Natural language command palette for power users (invoice, payment, reconcile)
  - **GUI**: Wave-style minimalist interface built with Inertia.js + Vue 3 + PrimeVue
- **Multi-tenant B2B SaaS** supporting accountants managing multiple client companies
- **Compliance-grade system of record** with full audit trails and immutable ledger

### Problems It Solves
1. **Speed vs Accessibility Trade-off**: CLI for experts, GUI for everyone
2. **Data Integrity**: Double-entry ledger with DB-level isolation (Postgres RLS)
3. **Multi-company Management**: Accountants can context-switch across client companies
4. **Compliance & Auditability**: Immutable journals, append-only audit logs, export capabilities
5. **Extensibility**: Module-per-schema design allows custom business verticals

### Target Users
- Tech-savvy founders/freelancers (want automation + speed)
- Professional accountants (need auditability + compliance)
- Non-technical SME owners (seek simplicity + mobile access)

**Source**: `docs/prd.md`, `docs/briefs/haasib-technical-brief-and-progress_v2.1_2025-08-22.md`

---

## 2. Scope & Feature Coverage

### Core Accounting Modules

#### **General Ledger** (`ledger` schema)
- Chart of accounts, journal entries, double-entry posting engine
- Balanced transaction enforcement, reversing entries, credit notes
- Trial balance, materialized views for reporting

#### **Accounts Receivable** (`billing` schema)
- Customer management (CRM-lite: contacts, addresses, groups, credit limits)
- Invoice lifecycle: draft → sent → paid → void
- PDF generation, email delivery, templates, credit notes
- Aging reports, customer statements

#### **Accounts Payable** (`public` schema per tracker decisions)
- Vendor management (contacts, terms, tax IDs)
- Bill approval workflow with multi-user gating
- Payment processing with allocation against bills
- AP aging, vendor performance metrics

#### **Payment Processing**
- Manual payment entry with allocation UI
- CSV/bank feed batch imports with fuzzy matching
- Payment reversals, overpayment/credit handling
- Background queue processing for large batches
- Audit trail for all allocation changes

#### **Bank Reconciliation**
- CSV import with intelligent transaction matching
- Rule-based auto-matching (amount+date, reference lookup)
- Unmatched item queue, manual override capability
- Reconciliation reports and variance analysis

#### **Tax Management**
- Multi-jurisdiction tax rates (AE-VAT, PK-GST presets)
- Calculator service integrated into posting pipeline
- Tax liability tracking, compliance reporting

#### **Reporting Dashboard**
- Financial statements: P&L, Balance Sheet, Cash Flow
- AR/AP aging summaries with drill-down
- Real-time KPIs, trend charts (Chart.js)
- Custom date ranges, comparative periods

### CLI & Command Palette

#### **Architecture**
- Flat verb syntax (no module prefixes): `invoice`, `payment`, `reconcile`
- Natural language parsing: "got paid", "send invoice", "what's overdue?"
- Shared command bus with HTTP controllers (no logic duplication)
- Idempotency keys auto-injected for all mutations

#### **Command Categories**
- **Company & Setup**: `setup`, `company`, `users`, `switch`
- **Money In (AR)**: `invoice`, `payment`, `customers`, `aging`
- **Money Out (AP)**: `bill`, `expense`, `pay`, `vendors`, `owed`
- **Banking**: `accounts`, `import`, `reconcile`, `transfer`, `balance`
- **Reporting**: `dashboard`, `profit`, `balance-sheet`, `cash-flow`, `taxes`
- **System**: `help`, `history`, `templates`, `schedule`, `export`

#### **UX Features**
- Hotkey activation (`Ctrl+\``) with half-screen overlay
- Context-aware prompts for missing parameters
- Tab completion with fuzzy entity search
- Command history, favorites, replay
- Template saving for recurring transactions

**Source**: `docs/cli.md`, `docs/clie-v2.md`, `docs/cli/company-commands.md`, `docs/payment-batch-cli-reference.md`

### Frontend Architecture

#### **Stack**
- Vue 3 Composition API (`<script setup lang="ts">`)
- Inertia.js v2 for SPA experience without API duplication
- PrimeVue v4 components with custom theming
- Tailwind CSS for utility-first styling
- Chart.js for dashboards

#### **Component Strategy**
- Reusable library under `resources/js/Components/UI/`
- DataTablePro with advanced filtering, sorting, virtual scroll
- Specialized pickers: CustomerPicker, CurrencyPicker, CountryPicker
- Form components with server-side validation integration
- Status badges, action menus, summary cards

#### **State & Services**
- Server-driven pagination/filtering (no heavy client state)
- Service layer abstractions (InvoiceService, PaymentService)
- Composables for shared logic
- i18n integration (English + Arabic RTL)

**Source**: `docs/frontend-architecture.md`, `docs/development-guide.md`, `docs/primevue-theming.md`

### API Surface

#### **Design Principles**
- Versioned path: `/api/v1/*`
- Sanctum authentication (SPA + token for mobile)
- snake_case JSON payloads, ISO-8601 UTC timestamps
- Envelope pattern: `{ data, meta, links }`

#### **Features**
- Pagination: `?page[number]=1&page[size]=25`
- Filtering: `?filter[status]=paid&filter[q]=search`
- Sorting: `?sort=-created_at,amount_cents`
- Sparse fields: `?fields[entity]=id,name` (bandwidth optimization)
- Delta sync: `/sync?updated_since=ISO8601` for offline clients
- Idempotency: `Idempotency-Key` header required for mutations
- Structured errors: `{ error: { code, message, details } }`

#### **Endpoints Documented**
- Company management, context switching
- Payment audit/reporting APIs
- Allocation quick guide for partial payments

**Source**: `docs/api-allocation-guide.md`, `docs/api/company-endpoints.md`, `docs/idempotency.md`

### Compliance & Security

#### **Multi-Tenancy**
- PostgreSQL Row-Level Security (RLS) on all tenant tables
- `company_id` columns with policies: `WHERE company_id = current_setting('app.current_company_id')::uuid`
- Middleware sets session-scoped GUC: `SET LOCAL app.current_company_id = ?`
- Octane-safe (per-request scope)

#### **RBAC**
- Spatie Laravel Permission integration
- Roles: owner, admin, accountant, viewer
- Gate abilities: `company.manageMembers`, `ledger.post`, `report.export`
- Pivot table: `auth.company_user` tracks role per company

#### **Audit Trail**
- `created_by`, `updated_by` columns on mutable tables
- Append-only audit log table with event sourcing
- ServiceContext DTO carries user/company/idempotency through all operations
- Exported audit reports for compliance review

#### **Payment Batch Compliance**
- CSV validation rules, duplicate detection
- Approval workflow with multi-user sign-off
- Reconciliation evidence chain
- Error handling with retry/rollback support

**Source**: `docs/PAYMENT-BATCH-COMPLIANCE-EVIDENCE.md`, `docs/CUSTOMER-LIFECYCLE-COMPLIANCE.md`, `docs/briefs/rbac_implementation_brief.md`

---

## 3. Mission, Vision & Strategic Direction

### North Star
> "Create the fastest accounting app experience with reliability and simplicity, empowering SMEs with tools that professionals love."

### Core Principles
1. **Simplicity Over Complexity**: No jargon, minimal clicks, intuitive flows
2. **Speed Without Sacrifice**: CLI speed + GUI safety nets
3. **Data Integrity First**: Double-entry rigor, DB-level constraints, immutable ledgers
4. **Multi-company Native**: Accountants managing 10+ clients with ease
5. **Compliance-Grade**: Audit trails that pass regulatory scrutiny

### Strategic Pillars

#### **Hybrid UX Philosophy**
- CLI for repetitive tasks, bulk operations, scripting
- GUI for exploration, learning, visual confirmation
- Real-time sync: CLI shows equivalent GUI location, vice versa
- Conversational CLI: "Who owes me money?" not `ar:report --type=aging`

#### **Architecture Goals**
- Modular monolith (not microservices) for solo-dev velocity
- Schema-per-module isolation in single Postgres DB
- API-first design for future mobile apps
- Extensibility via module registry + domain events

#### **Market Strategy**
- Initial markets: UAE (AED) + Pakistan (PKR)
- English + Arabic (RTL) from day one
- Self-serve onboarding wizard
- Manual billing (Stripe later) to validate PMF

### Roadmap Phases

**Phase 0: Foundations** ✅ Complete
- Auth, multi-company, RBAC, tenancy middleware, health checks

**Phase 1: Ledger Core** ✅ Complete
- Chart of accounts, journal entries, posting engine, trial balance

**Phase 2: Invoicing (AR)** ✅ Complete
- Customers, invoices, PDF/email, credit notes, aging reports

**Phase 3: Payments** 🚧 In Progress
- Manual receipts, CSV batches, allocation UI, audit dashboards

**Phase 4: AP & Reconciliation** 📋 Planned
- Vendors, bills, approval workflow, bank CSV matching

**Phase 5: Reporting & Dashboards** 📋 Planned
- Materialized views, scheduled refreshes, export formats

**Phase 6: Tax & Compliance** 📋 Future
- Multi-jurisdiction calculators, liability tracking, filing prep

**Phase 7: Mobile & Offline** 📋 Future
- Sparse/delta APIs, offline queue, conflict resolution

**Source**: `docs/briefs/target-mvp.md`, `docs/briefs/00_core_phase_tracker.md`, `docs/briefs/01_invoicing_phase_tracker.md`, `docs/briefs/02_payments_phase_tracker.md`

---

## 4. Preparation, Setup & Architectural Decisions

### Development Environment

#### **Stack Requirements**
- PHP 8.2+, Composer
- Laravel 12 (Octane + Swoole for concurrency)
- Node 20+, npm/Vite
- PostgreSQL 16 (RLS support)
- Redis 7 (cache + queues)

#### **Local Setup Options**
1. **Native Stack** (preferred): Homebrew (macOS), apt (Linux), WSL2 (Windows)
2. **Laravel Herd**: Bundled PHP/Nginx (Mac/Windows)
3. **Laravel Sail** (Docker): Optional, not required

#### **Key Services**
- Octane workers (Swoole) for persistent app state
- Horizon for queue monitoring
- Telescope (local/staging only) for debugging
- Mailpit for local email testing
- MinIO for S3-compatible storage

**Source**: `docs/dev-plan.md`, `docs/onboarding-checklist.md`

### Database Architecture

#### **Schema Design**
```
auth.*         -- users, companies, company_user pivot, roles
ledger.*       -- ledger_accounts, journal_entries, journal_lines
billing.*      -- invoices, invoice_items, credit_notes
ops.*          -- bank_accounts, bank_transactions, currencies
audit.*        -- audit_log, idempotency_keys
public.*       -- vendors, bills, payments (per tracker decision)
app.*          -- RLS helper functions, shared views
```

#### **Conventions**
- **UUIDs** for all primary keys
- **company_id** on every tenant table (indexed)
- **Timestamps**: `created_at`, `updated_at`, soft deletes where appropriate
- **Audit columns**: `created_by`, `updated_by` (FK to users, nullable with `ON DELETE SET NULL`)
- **Constraints**: CHECK for amounts ≥ 0, status whitelists, FK integrity
- **Indexes**: `(company_id, created_at)` on event-like tables

#### **RLS Setup**
```sql
ALTER TABLE billing.invoices ENABLE ROW LEVEL SECURITY;

CREATE POLICY invoices_tenant_isolation ON billing.invoices
  USING (company_id = current_setting('app.current_company_id', true)::uuid)
  WITH CHECK (company_id = current_setting('app.current_company_id', true)::uuid);
```

#### **Middleware Flow**
1. `SetTenantContext`: Reads session/header, sets `app.current_company_id`
2. `TransactionPerRequest`: Wraps POST/PUT/PATCH/DELETE in DB transaction
3. All queries automatically filtered by RLS policies

**Source**: `docs/dev-plan.md`, `docs/multi-schema-changes.md`, `docs/schema-v2.sql`

### Module Architecture (Legacy vs Current)

#### **Historical Approach** (Archived)
- `modules/<ModuleName>/` directory structure
- `config/modules.php` registry
- ModuleServiceProvider for each module
- CLI palette registry fragments

#### **Current Approach** (`stack/` workspace)
- Constitution v2.2.0 guardrails
- Modules register in `stack/modules/*`
- Domain-driven layout with actions, services, events, jobs
- Shared services in `app/`

**Migration Note**: Documentation shows "ARCHIVE NOTICE" on several guides (ServiceContext, modules scaffolding) directing to newer patterns in `stack/`.

**Source**: `docs/modules-architecture.md`, `docs/briefs/haasib-technical-brief-and-progress_v2.1_2025-08-22.md`

### ServiceContext Pattern

#### **Purpose**
Replace global `auth()` calls with explicit context injection for:
- Testability (no hidden dependencies)
- Queue jobs (context serialized with job)
- Audit logging (consistent user/company/idempotency tracking)

#### **Components**
1. **ServiceContext DTO**: Immutable carrier of `user`, `companyId`, `idempotencyKey`
2. **ServiceContextHelper**: Factory methods (`fromRequest`, `forSystem`, `forUser`)
3. **Middleware**: `AddServiceContextToRequest` injects into request attributes

#### **Migration Path**
```php
// Old: public function createPayment(Company $company, Money $amount): Payment
// New: public function createPayment(Company $company, Money $amount, ServiceContext $context): Payment

// Old: $userId = auth()->id();
// New: $userId = $context->getActingUser()?->id;
```

**Status**: Pattern now superseded by `App\Services\ContextService` in `stack/`, but docs preserved for historical reference.

**Source**: `docs/ServiceContext-Guide.md`, `docs/ServiceContext-PR-Review-Checklist.md`, `docs/ServiceContext-Test-Coverage-Plan.md`

### Testing Strategy

#### **Frameworks**
- **Pest** for unit + feature tests (PHPUnit under the hood)
- **Playwright** for E2E + CLI parity tests
- **Larastan** (PHPStan) for static analysis
- **Pint** for PSR-12 style enforcement

#### **Coverage Expectations**
- RLS tests: Verify cross-company isolation
- Balance tests: Double-entry math correctness
- CLI/GUI parity: Same command bus output via both interfaces
- Idempotency: Duplicate requests yield same result
- Permission gates: RBAC enforcement

#### **Test Helpers**
- `Tests\TestCase::setTenant($companyId)` for Gate checks
- Factory pattern for seeding test companies/users
- `CACHE_STORE=array` in testing environment

**Source**: `docs/manual_test.md`, `docs/cli-gui-parity-testing-guide.md`, `docs/testing/*`

### CI/CD & Deployment

#### **GitHub Actions Pipeline**
1. Install PHP/Node dependencies
2. Run Pint (formatting check)
3. Run Larastan (static analysis)
4. Run Pest (unit + feature tests)
5. Build frontend assets (Vite)
6. Run database migrations (dry-run)

#### **Deployment Flow**
1. Pre-deploy: `pg_dump` with encryption + checksum
2. Two-phase migrations (additive first, breaking second)
3. Zero-downtime symlink switch
4. Horizon supervisor restart
5. Health check validation (`/health` endpoint)

#### **Backup & DR**
- Nightly encrypted backups to S3
- Weekly restore drill with trial balance verification
- Runbooks for rollback procedures

**Source**: `docs/dev-plan.md`, `docs/briefs/haasib-technical-brief-and-progress_v2.1_2025-08-22.md`

### Observability

#### **Error Tracking**
- Sentry for exceptions + performance traces
- Breadcrumbs on all financial mutations
- Structured error codes (e.g., `INVOICE_NOT_FOUND`, `PAYMENT_DUPLICATE`)

#### **Metrics**
- API p95/p99 latency
- Queue depth + processing time
- DB slow query log
- RLS policy hit rates

#### **Health Endpoint**
```json
GET /health
{
  "status": "ok",
  "version": "1.2.3-abc123",
  "services": {
    "db": "ok",
    "redis": "ok",
    "queue": "ok"
  }
}
```

**Source**: `docs/monitoring/*`, `docs/ServiceContext-Monitoring.md`, `docs/ServiceContext-Metrics.md`

---

## 5. Documentation Structure

### Root-Level Documents

| File | Purpose |
|------|---------|
| `prd.md` | Product Requirements Document (original vision) |
| `dev-plan.md` | A→Z development roadmap (archived, see `stack/` for current) |
| `cli.md`, `clie-v2.md` | CLI syntax, philosophy, command reference |
| `development-guide.md` | Component usage patterns, best practices |
| `frontend-architecture.md` | Component library strategy, extraction plans |
| `modules-architecture.md` | Module scaffolding guide (archived) |
| `ServiceContext-Guide.md` | Context pattern implementation (archived) |
| `idempotency.md` | Idempotency key rules for APIs |
| `onboarding-checklist.md` | New developer setup steps |
| `tasks.md` | Active task tracker |
| `TEAM_MEMORY.md` | Knowledge base, gotchas, decisions |

### Subdirectories

#### **`api/`**
- `company-endpoints.md`: Multi-company API patterns
- `payment-audit-reporting.md`: Audit query endpoints

#### **`briefs/`**
- `haasib-technical-brief-and-progress_v2.1_2025-08-22.md`: Engineering handbook (canonical reference)
- `00_core_phase_tracker.md`: Foundations phase checklist
- `01_invoicing_phase_tracker.md`: Invoicing module progress
- `02_payments_phase_tracker.md`: Payments module progress
- `target-mvp.md`: MVP scope definition
- `rbac_implementation_brief.md`: RBAC design decisions
- `story-of-accounting.md`: Accounting fundamentals primer

#### **`cli/`**
- `company-commands.md`: Company management CLI reference

#### **`components/`**
- Component audit results, extraction checklists

#### **`monitoring/`**
- Alerting policies, dashboard definitions

#### **`openapi/`**
- Generated/hand-written OpenAPI specs

#### **`request-journeys/`**
- Step-by-step user flow diagrams

#### **`schemas/`**
- Module-specific DDL files (historical)
- `schema-v2.sql`: Full DB schema snapshot

#### **`specs/`**
- Detailed feature specifications

#### **`testing/`**
- Test standards, coverage reports

### Documentation Quality Notes

#### **Strengths**
- Comprehensive coverage of architecture, conventions, and workflows
- Clear "archive notices" distinguishing legacy vs current patterns
- Detailed compliance/audit evidence trails
- CLI philosophy well-articulated with examples

#### **Gaps Identified**
- Some docs reference "merged_output.txt" (missing)
- Constitution v2.2.0 (mentioned) not fully documented in `/docs`
- Newer `stack/` architecture patterns not backported to older guides
- No centralized API docs (Swagger/Scribe mentioned but not in `/docs`)

#### **Maintenance Status**
- Active: `briefs/*`, `TEAM_MEMORY.md`, `tasks.md`
- Archived but retained: `ServiceContext-Guide.md`, `modules-architecture.md`, `dev-plan.md`
- Historical reference: `schema-v2.sql`, phase trackers

**Source**: All files in `/docs`, metadata analysis

---

## 6. Key Technical Decisions

### 1. Monolith Over Microservices
**Decision**: Laravel modular monolith  
**Rationale**: Solo-dev velocity, simplified deployment, shared DB transactions  
**Trade-off**: Module boundaries must be disciplined to avoid coupling  
**Source**: `docs/dev-plan.md`, engineering handbook

### 2. PostgreSQL RLS for Multi-Tenancy
**Decision**: Database-enforced isolation with row-level security  
**Rationale**: Defense-in-depth, works even if app logic fails  
**Trade-off**: More complex migrations, potential performance overhead  
**Mitigation**: Indexed `company_id`, policy caching, session-scoped GUCs  
**Source**: `docs/dev-plan.md`, `docs/multi-schema-changes.md`

### 3. Schema-per-Module
**Decision**: Separate Postgres schemas (`auth.*`, `ledger.*`, `billing.*`)  
**Rationale**: Namespace isolation, clear module boundaries, parallel migration development  
**Trade-off**: Cross-schema JOINs, FKs require schema qualification  
**Current State**: Hybrid (some tables in `public`, recent decision to consolidate)  
**Source**: `docs/multi-schema-changes.md`, payment tracker

### 4. CLI-First UX
**Decision**: Hybrid CLI + GUI with command bus shared logic  
**Rationale**: Speed for power users, accessibility for everyone, differentiation  
**Implementation**: Flat verb syntax, natural language parser, Ctrl+\` hotkey  
**Trade-off**: Requires parity testing, dual UX maintenance  
**Source**: `docs/prd.md`, `docs/cli.md`

### 5. Inertia.js Over SPA-with-API
**Decision**: Inertia + Vue 3 SSR instead of separate API + SPA  
**Rationale**: Avoids API duplication, simpler auth, faster iteration  
**Trade-off**: Mobile app needs separate `/api/v1` endpoints anyway  
**Mitigation**: API built in parallel, reuses same services  
**Source**: `docs/dev-plan.md`, technical brief

### 6. Idempotency Keys Everywhere
**Decision**: Required `Idempotency-Key` header on all mutations  
**Rationale**: Safe retries, network resilience, duplicate prevention  
**Implementation**: Middleware checks, service-layer deduplication, 24-hour retention  
**Source**: `docs/idempotency.md`, API guides

### 7. ServiceContext DTO Pattern
**Decision**: Explicit context injection vs global `auth()`  
**Rationale**: Testability, queue job context, audit trail consistency  
**Evolution**: Now superseded by `ContextService` in `stack/`  
**Migration**: Legacy guides archived but preserved  
**Source**: `docs/ServiceContext-Guide.md`, archive notices

### 8. Manual Payments First, Gateways Later
**Decision**: Manual entry + CSV imports before Stripe/local gateway integration  
**Rationale**: Validate reconciliation UX, avoid premature gateway lock-in  
**Trade-off**: Manual workflows slower, but builds strong audit foundation  
**Source**: Payment compliance docs, phase trackers

### 9. Octane (Swoole) for Performance
**Decision**: Laravel Octane with Swoole worker model  
**Rationale**: Sub-100ms p50 server response times, persistent state  
**Considerations**: Safe middleware patterns, avoid globals, `set local` scoping  
**Source**: `docs/dev-plan.md`, technical brief

### 10. PrimeVue Over Custom Components
**Decision**: PrimeVue v4 component library with custom theme  
**Rationale**: Comprehensive components (DataTable, Calendar, Dialog), RTL support  
**Trade-off**: Larger bundle size, learning curve  
**Mitigation**: Tree-shaking, lazy loading, custom theme layer  
**Source**: `docs/primevue-theming.md`, `docs/mig-to-prime.md`

### 11. Materialized Views for Reporting
**Decision**: PostgreSQL MVs for trial balance, aging, P&L/BS  
**Rationale**: Complex aggregations pre-computed, fast query times  
**Refresh Strategy**: `CONCURRENTLY` after posting batches, nightly for others  
**Trade-off**: Eventual consistency (acceptable for reports)  
**Source**: Technical brief, dev plan

### 12. Sparse/Delta APIs for Mobile
**Decision**: Optional sparse fields + delta sync for offline clients  
**Rationale**: Bandwidth-aware, mobile-friendly, sync conflict resolution  
**Implementation**: `?fields[entity]=id,name`, `?updated_since=ISO8601`  
**Source**: `docs/dev-plan.md`, API conventions

---

## 7. Current State Assessment

### Completed Milestones ✅
- **Foundations**: Auth, multi-company, RBAC, tenancy middleware, health checks
- **Ledger Core**: Chart of accounts, journal entries, posting engine, trial balance
- **Invoicing**: CRUD, PDF/email, credit notes, aging reports, Inertia UI
- **CLI Foundations**: Command bus, palette UI, DevOps commands, tenant context

### In Progress 🚧
- **Payments Module**: Manual receipts, CSV batch processing, allocation UI, audit dashboards
- **Component Library**: Extraction from invoicing module, DataTablePro standardization
- **Frontend Cleanup**: PrimeVue migration, theming, i18n integration

### Planned 📋
- **Accounts Payable**: Vendors, bills, approval workflow, payment disbursement
- **Bank Reconciliation**: CSV import, fuzzy matching, unmatched queue
- **Tax Module**: Multi-jurisdiction calculators, liability tracking
- **Reporting v2**: Materialized views, scheduled refreshes, export formats
- **Mobile API**: Sparse fields, delta sync, offline queue

### Known Gaps & Debt
1. **Documentation Drift**: Some legacy docs not updated post-`stack/` migration
2. **API Docs**: OpenAPI specs mentioned but not fully published
3. **E2E Test Coverage**: Playwright suite incomplete
4. **Constitution v2.2**: Referenced but full spec not in `/docs`
5. **Module Consolidation**: Schema-per-module vs `public.*` inconsistency

### Architecture Evolution Markers
- Multiple "ARCHIVE NOTICE" headers signal active refactoring
- ServiceContext → ContextService migration
- Legacy `modules/` → `stack/modules/*` reorganization
- Dev plan v1 → Constitution v2.2.0 governance

---

## 8. Recommendations for New Senior Developers

### Week 1: Orientation
1. Read `docs/prd.md` for product vision
2. Review `docs/briefs/haasib-technical-brief-and-progress_v2.1_2025-08-22.md` (canonical engineering reference)
3. Skim phase trackers to understand current milestone
4. Run through `docs/onboarding-checklist.md`

### Week 2: Technical Deep-Dive
1. Study RLS setup in `docs/dev-plan.md` + `docs/multi-schema-changes.md`
2. Explore CLI philosophy in `docs/cli.md` + `docs/clie-v2.md`
3. Review component patterns in `docs/development-guide.md`
4. Trace request journey: middleware → controller → service → ledger posting

### Week 3: Hands-On
1. Run local setup (Octane + Postgres + Redis)
2. Seed demo tenant, explore GUI + CLI
3. Write a Pest test for RLS isolation
4. Contribute to component extraction (low-risk, high-learning)

### Gotchas to Watch
- **Archive Notices**: Always check for "ARCHIVE NOTICE" headers; they redirect to canonical sources
- **Schema Qualification**: Use `auth.users`, `ledger.journal_entries` (not bare table names)
- **RLS Context**: Middleware sets `app.current_company_id`; jobs must set explicitly
- **Octane Globals**: Avoid global state; use `set local` for per-request config
- **Idempotency**: All mutations require `Idempotency-Key`; tests must supply

### High-Value Contribution Areas
1. **Test Coverage**: RLS isolation, CLI/GUI parity, idempotency
2. **Documentation**: Backport `stack/` patterns to `/docs`
3. **Component Library**: Extract reusable UI patterns
4. **OpenAPI Specs**: Generate/validate API documentation
5. **E2E Tests**: Playwright coverage for critical flows

---

## 9. Technical Glossary

| Term | Definition |
|------|------------|
| **RLS** | Row-Level Security (Postgres feature for per-row access control) |
| **ServiceContext** | DTO carrying user/company/idempotency through operations (legacy pattern) |
| **ContextService** | Current implementation of context management in `stack/` |
| **Command Bus** | Shared action dispatcher used by CLI, HTTP controllers, jobs |
| **Palette** | CLI overlay UI (Ctrl+\` hotkey) with command completion |
| **Idempotency Key** | UUID ensuring duplicate requests yield same result |
| **GUC** | Grand Unified Configuration (Postgres session variables) |
| **Octane** | Laravel performance layer using persistent workers (Swoole/RoadRunner) |
| **Horizon** | Laravel queue dashboard + monitoring |
| **Inertia** | Bridge between Laravel backend + Vue frontend (no separate API) |
| **MV** | Materialized View (pre-computed aggregations in Postgres) |
| **Sparse Fields** | API parameter to return subset of columns (bandwidth optimization) |
| **Delta Sync** | API pattern to fetch only records updated since timestamp |
| **Constitution v2.2** | Current architectural governance framework (in `stack/`) |

---

## 10. Contact Points & Resources

### Internal Knowledge Bases
- `docs/TEAM_MEMORY.md`: Institutional knowledge, past decisions
- `docs/tasks.md`: Active work tracker
- Phase trackers in `docs/briefs/`: Module-specific progress

### External References
- Laravel 12 docs: https://laravel.com/docs
- Vue 3 docs: https://vuejs.org/guide/
- PrimeVue docs: https://primevue.org
- Postgres RLS guide: https://postgresql.org/docs/current/ddl-rowsecurity.html

### Architectural Decisions
- PRD: Product vision & user personas
- Engineering Handbook: Technical north star
- Dev Plan: Historical roadmap (archived, see Constitution v2.2)

---

## Appendix: Document Inventory

### By Category

**Product & Strategy** (6 files)
- prd.md, briefs/target-mvp.md, briefs/haasib-technical-brief-and-progress_v2.1_2025-08-22.md, briefs/story-of-accounting.md, cli-PRD.md, next-feature-briefs.md

**Architecture & Development** (11 files)
- dev-plan.md, modules-architecture.md, frontend-architecture.md, development-guide.md, multi-schema-changes.md, migrations-patch.md, admin-refactoring-plan.md, mig-to-prime.md, primevue-theming.md, primevue-inventory.md, vue-i18n-implementation-guide.md

**CLI & Commands** (5 files)
- cli.md, clie-v2.md, cli-PRD.md, cli-gui-parity-testing-guide.md, cli/company-commands.md, payment-batch-cli-reference.md

**API & Integration** (4 files)
- api-allocation-guide.md, api/company-endpoints.md, api/payment-audit-reporting.md, idempotency.md

**ServiceContext Suite** (6 files)
- ServiceContext-Guide.md, ServiceContext-PR-Review-Checklist.md, ServiceContext-Test-Coverage-Plan.md, ServiceContext-Metrics.md, ServiceContext-Monitoring.md, ServiceContext-Error-Budget-Review.md

**Compliance & Evidence** (2 files)
- PAYMENT-BATCH-COMPLIANCE-EVIDENCE.md, CUSTOMER-LIFECYCLE-COMPLIANCE.md

**Implementation Guides** (3 files)
- PAYMENT-BATCH-IMPLEMENTATION-SUMMARY.md, payment-batch-quickstart.md, briefs/001-implementation-summary.md

**Testing & QA** (3 files)
- manual_test.md, cli-gui-parity-testing-guide.md, testing/ directory

**Operations** (5 files)
- onboarding-checklist.md, demo-tenant-plan.md, tasks.md, TEAM_MEMORY.md, monitoring/ directory

**Phase Tracking** (4 files)
- briefs/00_core_phase_tracker.md, briefs/01_invoicing_phase_tracker.md, briefs/02_payments_phase_tracker.md, briefs/rbac_implementation_brief.md

**Database** (3 files)
- schema-v2.sql, schemas/ directory, spec-reference.md

**Other** (4 files)
- T003-implementation-plan.md, lightbearer-ai-playbook.md, dosdonts/ directory, request-journeys/ directory

**Total**: 54 files + 14 subdirectories

---

## Conclusion

LedgerFly/Haasib represents a mature, well-architected accounting platform with clear product vision, rigorous technical foundations, and comprehensive documentation. The hybrid CLI+GUI approach differentiates it in the SME accounting market, while PostgreSQL RLS and double-entry rigor provide enterprise-grade reliability.

**Current focus**: Completing payments module, standardizing component library, and preparing for AP/reconciliation phases.

**Documentation health**: Strong but shows signs of active refactoring (archive notices). New developers should prioritize recent briefs over older root-level guides when conflicts arise.

**Recommended next steps for leadership**:
1. Consolidate "archived" vs "current" documentation split
2. Publish Constitution v2.2.0 spec in `/docs` for transparency
3. Establish doc review cadence to prevent drift
4. Generate OpenAPI specs programmatically from routes

---

**Document Metadata**  
**Last Updated**: 2025-10-16  
**Analyzed Files**: 54 documents across 14 directories  
**Total Documentation**: ~200,000 words  
**Key Sources**: prd.md, haasib-technical-brief-and-progress_v2.1_2025-08-22.md, dev-plan.md, cli.md, ServiceContext-Guide.md, phase trackers
