# Payments Phase Tracker

**Created:** 2025-09-22
**Module:** Accounts Payable (Payments)
**Schema File:** `docs/schemas/12_ap.sql`
**Status:** Phase 002 Planning — public schema rollout in progress

## Tables to Create for Payments Phase

### Core Payments Tables (all `public.*`)
- [ ] `public.vendors` - Vendor/supplier management
- [ ] `public.bills` - Bills received from vendors
- [ ] `public.bill_items` - Bill line items
- [ ] `public.bill_payments` - Bill payment records
- [ ] `public.payment_allocations` - Payment to bill allocations
- [ ] `public.accounts_payable_mv` - AP summary/aging materialized view

### Payment Processing Tables (future-ready, `public.*`)
- [ ] `public.payment_methods` - Payment method configuration
- [ ] `public.payment_terms` - Payment terms and conditions
- [ ] `public.recurring_bills` - Recurring bill templates
- [ ] `public.vendor_credits` - Vendor credit notes/refunds

## Dependencies (Must exist first)

### Core System (`00_core.sql`)
- [x] `core.companies` - Multi-tenant companies
- [x] `core.currencies` - Currency definitions
- [x] `core.countries` - Country references
- [x] `core.user_accounts` - User accounts
- [x] `core.exchange_rates` - Currency exchange rates

### Core Accounting (`10_accounting.sql`)
- [x] `acct.fiscal_years` - Fiscal year definitions
- [x] `acct.accounting_periods` - Accounting periods
- [x] `acct.chart_of_accounts` - Chart of accounts
- [x] `acct.transactions` - General ledger transactions
- [x] `acct.journal_entries` - Journal entry lines

### CRM Module (`40_crm.sql`)
- [x] `crm.vendors` - Vendor entities
- [x] `crm.contacts` - Vendor contacts
- [x] `crm.interactions` - Vendor activity tracking

## Implementation Notes

### Key Features to Implement
1. **Multi-currency support** - Exchange rate handling for vendor bills
2. **Idempotent payments & bills** - Company-scoped `idempotency_key` columns for all mutating flows
3. **Multi-tax per line item** - Support for various tax regimes
4. **Payment allocation** - Apply payments to multiple bills
5. **Aging reports** - Based on `accounts_payable_mv` materialized view
6. **Audit trail & attachments** - `created_by/updated_by`, document uploads, and event logging
7. **Soft deletes** - `deleted_at` columns for data preservation
8. **Recurring bills** - Automated bill generation (phase follow-up)
9. **Vendor credits** - Credit memo and refund handling

### Foreign Key Relationships
- `bills.vendor_id` → `crm.vendors.vendor_id`
- `bill_items.item_id` → `inv.items.item_id` (if using inventory)
- `bill_payments.entity_id` → `crm.vendors.vendor_id`
- All tables link to `core.companies.company_id` (multi-tenant)

### Business Logic
- Bill numbering (company-scoped)
- Bill Status Workflow: `draft` → `received` → `approved` → `paid` → `void`
  - `draft`: Editable, no GL impact
  - `received`: Locked, awaiting approval
  - `approved`: Ready for payment
  - `paid`: Payment processed, GL impact posted
  - `void`: Cancelled, triggers reversing journal entry
- Payment Status Tracking: `unpaid` → `partial` → `paid` → `overpaid`
  - Calculated automatically based on allocations
- Payment allocation validation (partial/over payments guarded by totals)
- Balance due calculations and automatic status transitions
- Early payment discounts and late payment penalties
- Attachment handling for bills, approvals, and payment receipts
- Reconciliation metadata for bank CSV matching

### RBAC Permissions (to be created)
- `bills.view`, `bills.create`, `bills.edit`, `bills.delete`, `bills.approve`, `bills.pay`
- `payments.view`, `payments.create`, `payments.edit`, `payments.delete`, `payments.allocate`, `payments.reconcile`
- `vendors.view`, `vendors.create`, `vendors.edit`, `vendors.delete`

## Progress Tracking

### Planned / In Progress
- [x] Analysis of schema requirements (Phase 002 brief updated)
- [x] Dependencies identified (core, CRM, ledger prerequisites)
- [ ] Recast SQL to `public.*` (update `12_ap.sql` + migrations)
- [ ] Core system migrations (5 tables)
- [ ] CRM module migrations (4 tables)
- [ ] Accounting module migrations (5 tables)
- [ ] Payments (AP) module migrations (10 tables)

### Migration Status Summary
- **Core System**: 5 tables ✅
- **CRM Module**: 4 tables ✅
- **Accounting Module**: 5 tables ✅
- **Payments (AP) Module**: 10 tables 📋

---

## 📋 Development Roadmap

Based on **Definition of Done** (dev-plan.md) and **Technical Brief** Section 9.4 update

### 🎯 Phase 002.A: Schema & Data Layer
**Priority: Critical** — unblock downstream work

- [ ] Rewrite `docs/schemas/12_ap.sql` for `public.*` tables (drop `acct_ap` schema usage)
- [ ] Mirror the schema updates in Laravel migrations (2025_09_23_* files)
- [ ] Add company-scoped idempotency columns + unique indexes for bills and payments
- [ ] Apply CHECK constraints for monetary/tax fields and ensure defaults match DoD
- [ ] Enable RLS on every tenant table using `app.current_company`
- [ ] Define supporting lookup tables (`public.payment_methods`, `public.payment_terms`)
- [ ] Create/refresh materialized view definition for `public.accounts_payable_mv`
- [ ] Document seed/factory expectations for vendors, bills, and payments

### 🎯 Phase 002.B: Domain Services & Command Facades
**Priority: High** — match invoicing architecture

- [ ] Implement `BillService`, `PaymentService`, `VendorService`, `LedgerIntegrationService`
- [ ] Ship command facades (`BillCreate|Update|Approve|Pay|Void`, `PaymentCreate|Void`, `VendorCreate|Update`)
- [ ] Guarantee per-request transactions + idempotency guards in facades
- [ ] Integrate Money value objects for calculations and rounding
- [ ] Cover factories + seeders to unlock unit/service tests

### 🎯 Phase 002.C: Workflow, UI & API
**Priority: High** — deliver operator tooling

- [ ] Build Inertia screens for vendors, bills, approvals, and payments (reuse `DataTablePro`)
- [ ] Expose `/api/v1/payables` endpoints with OpenAPI docs + structured errors
- [ ] Support attachment uploads, approval gating, and partial/over payment allocation UI
- [ ] Wire lookup composables for payment methods/terms and bank references
- [ ] Provide idempotent public API example + Postman snippet for QA

### 🎯 Phase 002.D: Ledger & Reconciliation
**Priority: High** — maintain accounting integrity

- [ ] Post balanced entries on bill approval and payment completion (AP ↔ expense/cash)
- [ ] Handle voids/credits with reversing entries and domain events
- [ ] Store reconciliation metadata for bank CSV matching (ties into Section 9.5)
- [ ] Surface reconciliation state in UI/API and restrict to `payments.reconcile`
- [ ] Add monitoring (metrics + Sentry breadcrumbs) for posting failures

### 🎯 Phase 002.E: Quality, Tests & Ops
**Priority: Sustaining**

- [ ] Unit + feature coverage for services, facades, posting flows
- [ ] Scenario tests for multi-currency, partial payments, discounts, credits
- [ ] OpenAPI (`docs/openapi/payments.yaml`) + runbook updates
- [ ] CI hooks for schema drift detection + `accounts_payable_mv` refresh test
- [ ] Production readiness checklist (RLS validation, permission audit, backups)

## 📊 Timeline & Milestones

### **Milestone A: Public Schema & Constraints (1–1.5 weeks)**
- [ ] `12_ap.sql` + migrations rewritten for `public.*`
- [ ] Idempotency columns/indexes in place
- [ ] CHECK constraints + RLS verified locally

### **Milestone B: Services & Command Facades (1.5–2 weeks)**
- [ ] Core services implemented with tests
- [ ] Command facades wired with idempotency + transactions
- [ ] Factories/seed data supporting service tests

### **Milestone C: UI & API Delivery (2 weeks)**
- [ ] Inertia screens for vendors/bills/payments
- [ ] `/api/v1/payables` endpoints + OpenAPI
- [ ] Attachment + approval workflows operational

### **Milestone D: Posting & Reconciliation (1–1.5 weeks)**
- [ ] Ledger postings for bills/payments + reversing flows
- [ ] Reconciliation metadata surfaced + permissioned
- [ ] Monitoring hooks for posting/recon failures

### **Milestone E: Quality & Launch Readiness (1 week)**
- [ ] Scenario + integration tests green
- [ ] Runbooks, docs, and CI checks updated
- [ ] Production readiness checklist signed off

---

## ✅ Detailed Delivery Checklist (Phase Matrix)

### Database & Schema
- [ ] `public` core tables (bills, items, payments, allocations)
- [ ] `public.vendors` + lookup tables (methods, terms)
- [ ] Accounts payable MV + summary tables with RLS
- [ ] Recurring bills and payment terms scaffolding
- [ ] Company-scoped idempotency keys and audit columns
- [ ] Materialized view refresh strategy for AP reporting

### Models & State Machines
- [ ] Bill model with state machine (draft → received → approved → paid → void)
- [ ] BillPayment model with completion state and allocation helpers
- [ ] PaymentAllocation model with void/refund flows
- [ ] Vendor model with credit tracking
- [ ] RecurringBill model with automation logic

### Services
- [ ] BillService (CRUD, approve/pay/void, PDF, duplicate)
- [ ] PaymentService (create/process, allocate/auto-allocate, void/refund)
- [ ] VendorService (CRUD, credit management, performance tracking)
- [ ] LedgerIntegrationService (bill approval; payment post; allocation post)
- [ ] CurrencyService (conversion, formatting)
- [ ] RecurringBillService (automation, scheduling)

### CommandBus Layer
- [ ] Bill Actions (create, update, approve, pay, void, credit)
- [ ] Payment Actions (create, allocate, reconcile, void, refund)
- [ ] Vendor Actions (create, update, delete, credit)
- [ ] Command facades in `app/Actions/Payments/`
- [ ] Idempotency key support with unique indexes
- [ ] Standardized error envelope (includes `code`)
- [ ] Rate limiting and authorization

### Events & Listeners
- [ ] BillReceived/Approved/Paid/Voided (+ AP update, JE creation)
- [ ] PaymentProcessed/Allocated/Reconciled/Voided
- [ ] Void journal entries on bill void
- [ ] Posted journal entry updates (listeners)
- [ ] RecurringBillGenerated event

### Security & Multi-tenant
- [ ] RLS policies on core tables
- [ ] Company context middleware (Postgres GUC)
- [ ] Policies + Gates for bills, payments, and vendors (incl. `payments.reconcile`)
- [ ] Attachment permission and storage hardening
- [ ] Vendor access controls (if implementing vendor portal)

### Tests
- [ ] Auth flows, core feature coverage
- [ ] Idempotency: bill create/update/delete/actions
- [ ] Bill validations and ledger posting
- [ ] Payment completion → auto-post to ledger
- [ ] Allocation flows: partial allocation posting; allocation void; refunds
- [ ] Early payment discount calculations
- [ ] Recurring bill generation logic
- [ ] Error envelope (422 code)

### Docs & Ops
- [ ] OpenAPI YAML (`docs/openapi/payments.yaml`)
- [ ] L5-Swagger UI + `openapi:publish` console command
- [ ] GitHub Action to publish `storage/api-docs` artifact
- [ ] User guides, runbooks, migration notes

---

## 🏗️ CommandBus Integration Plan

### Planned Architecture
Following the **Command Facade + Domain Service** pattern established in invoicing:

#### Pattern Structure
```
Controller → Command Facade (Action) → Domain Service → Models
```

#### Key Components to Implement

1. **Command Facade Layer (`App\Actions\Payments/`)**
   - `BillCreate` - Handle bill creation with idempotency
   - `BillUpdate` - Update bill details through service
   - `BillApprove` - Approve bill workflow
   - `BillPay` - Process payment with allocation
   - `PaymentCreate` - Create payment records
   - `VendorCreate` - Vendor management actions

2. **Service Layer (`App\Services/`)**
   - `BillService` - Complex bill operations
   - `PaymentService` - Payment processing logic
   - `VendorService` - Vendor management
   - All services to support both web and API interfaces

3. **Implementation Requirements**
   - **Idempotency Support**: Unique indexes on idempotency_key + company_id
   - **Service Integration**: Delegate to existing services while adding HTTP concerns
   - **Test Coverage**: Feature and unit tests for all actions

#### Expected Benefits
- **Consistent Architecture**: Same pattern as invoicing
- **Code Reusability**: Shared patterns and utilities
- **Maintainability**: Clear separation of concerns
- **Testability**: Independent layer testing

---

## 🧭 Next Phases – Ready-to-Use Tracker Template

Copy this structure for upcoming modules (e.g., Inventory, VMS, Payroll):

### 1) Schema
- Tables, FKs, RLS, constraints, MVs (list per-table status)

### 2) Domain Models & State
- Models, casts, relationships, state machines

### 3) Services
- CRUD, workflows, integrations, side-effects

### 4) CommandBus Layer
- Command facades, idempotency, rate limits, standardized responses

### 5) Events & Listeners
- Domain events, ledger and AR/AP syncs, notifications

### 6) Security
- Policies, gates, roles/permissions, RLS & GUC usage

### 7) Tests
- Unit + Feature: CRUD, workflows, validations, integrations

### 8) Docs & Ops
- README, runbooks, CI workflows (OpenAPI, schema checks, coding standards)

---

## 🚀 Immediate Next Steps

**Start with Phase 1.1: Laravel Models & Factories**

1. **Create Bill model** with relationships to Vendor, Company, Currency
2. **Create BillItem model** with line item calculations and tax support
3. **Create BillPayment model** with allocation logic
4. **Create Vendor model** with credit tracking and performance metrics
5. **Create Factories** for comprehensive testing
6. **Implement Money object** integration for precise financial calculations

**Definition of Done Status:**
- [ ] DB schema
- [ ] Domain services + tests
- [ ] Web CRUD + validation
- [ ] CommandBus integration
- [ ] Audit trail for financial entities
- [ ] Metrics and monitoring

## Risk Assessment
- **High:** Missing dependencies will break payments functionality
- **Medium:** Complex approval workflows may require additional logic
- **Medium:** Recurring bill automation needs careful scheduling
- **Low:** Multi-currency handling is well-defined in schema

---

*This document should be updated as tables are created and dependencies are resolved.*
