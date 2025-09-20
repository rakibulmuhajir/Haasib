# Invoicing Phase Database Tables Tracker

**Created:** 2025-09-13
**Module:** Accounts Receivable (Invoicing)
**Schema File:** `docs/schemas/11_ar.sql`
**Status:** Development Phase

## Tables to Create for Invoicing Phase

### Core Invoicing Tables ✅ COMPLETED
- [x] `invoices` - Main invoice records (2025_09_13_090000_create_invoices_table.php)
- [x] `invoice_items` - Invoice line items (2025_09_13_090100_create_invoice_items_table.php)
- [x] `invoice_item_taxes` - Multi-tax per line item (2025_09_13_090200_create_invoice_item_taxes_table.php)

### Payment Processing Tables ✅ COMPLETED
- [x] `payments` - Customer payment records (2025_09_13_090300_create_payments_table.php)
- [x] `payment_allocations` - Payment to invoice allocations (2025_09_13_090400_create_payment_allocations_table.php)
- [x] `accounts_receivable_mv` - AR summary/aging materialized view (2025_09_13_090500_create_accounts_receivable_table.php)

## Dependencies (Must exist first)

### CRM Module (`40_crm.sql`)
- [x] `crm.customers` - Customer entities
- [x] `crm.vendors` - Vendor entities
- [x] `crm.contacts` - Customer/Vendor contacts
- [x] `crm.interactions` - Customer activity tracking

### Core Accounting (`10_accounting.sql`)
- [x] `acct.fiscal_years` - Fiscal year definitions
- [x] `acct.accounting_periods` - Accounting periods
- [x] `acct.chart_of_accounts` - Chart of accounts
- [x] `acct.transactions` - General ledger transactions
- [x] `acct.journal_entries` - Journal entry lines

### Core System (`00_core.sql`)
- [x] `core.companies` - Multi-tenant companies
- [x] `core.currencies` - Currency definitions
- [x] `core.countries` - Country references
- [x] `core.user_accounts` - User accounts
- [x] `core.exchange_rates` - Currency exchange rates

### Inventory Module (`20_inventory.sql`) - Recommended for Full Functionality
- [ ] `inv.item_categories` - Product categories
- [ ] `inv.items` - Products/Services to invoice
- [ ] `inv.warehouses` - Storage locations
- [ ] `inv.stock_levels` - Inventory quantities
- [ ] `inv.stock_movements` - Inventory tracking

## Implementation Notes

### Key Features to Implement
1. **Multi-currency support** - Exchange rate handling
2. **Multi-tax per line item** - Complex tax scenarios
3. **Payment allocation** - Apply payments to multiple invoices
4. **Aging reports** - Based on `accounts_receivable_mv` materialized view
5. **Audit trail** - Created/updated by tracking
6. **Soft deletes** - `deleted_at` columns for data preservation

### Foreign Key Relationships
- `invoices.customer_id` → `crm.customers.customer_id`
- `invoice_items.item_id` → `inv.items.item_id`
- `payments.entity_id` → `crm.customers.customer_id`
- All tables link to `core.companies.company_id` (multi-tenant)

### Business Logic
- Invoice numbering (company-scoped)
- Invoice Status Workflow: `draft` → `sent` → `posted` → `void`
  - `draft`: Editable, no GL impact.
  - `sent`: Locked, awaiting payment.
  - `posted`: Paid or partially paid, GL impact posted.
  - `void`: Cancelled, triggers reversing journal entry.
- Payment Status Tracking: `unpaid` → `partial` → `paid` → `overpaid`
  - Calculated automatically based on allocations.
- Payment allocation validation
- Balance due calculations

### RBAC Permissions (to be created)
- `invoices.view`, `invoices.create`, `invoices.edit`, `invoices.delete`, `invoices.send`, `invoices.post`
- `payments.view`, `payments.create`, `payments.edit`, `payments.delete`, `payments.allocate`

## Progress Tracking

### Completed
- [x] Analysis of schema requirements
- [x] Dependencies identified
- [x] Core system migrations created (5 tables)
- [x] CRM module migrations created (4 tables)
- [x] Accounting module migrations created (5 tables)
- [x] Invoicing (AR) module migrations created (6 tables)
- [x] Total: 20 tables successfully migrated

### Migration Status Summary
- **Core System**: 5 tables ✅
- **CRM Module**: 4 tables ✅
- **Accounting Module**: 5 tables ✅
- **User Accounts**: 1 table ✅
- **Invoicing (AR) Module**: 6 tables ✅
- **Total**: 21 tables created successfully ✅

---

## 📋 Development Roadmap

Based on **Definition of Done** (dev-plan.md) and **Technical Brief** requirements

### 🎯 Phase 1: Core Invoicing Foundation (Quick Path to Revenue)
**Priority: CRITICAL** - Aligns with "quick path to revenue" goal

#### 1.1 Laravel Models & Factories ✅ COMPLETED
- [x] Create Eloquent models for all invoicing tables
- [x] Add relationships, mutators, accessors, business rules
- [x] Create factories for testing with realistic data
- [x] Implement Money object integration for financial calculations

#### 1.2 Domain Services
- [x] `InvoiceService` - CRUD operations, PDF generation, status workflow
- [x] `PaymentService` - Payment processing and allocation logic
- [ ] `TaxCalculator` - Multi-tax calculations (AE-VAT, PK-GST presets)
- [x] `LedgerIntegrationService` - Posting to ledger and void/reversal support

#### 1.3 Business Logic Implementation ✅ COMPLETED
- [x] Invoice numbering (company-scoped with validation)
- [x] Payment allocation validation (prevent over-allocation)
- [x] Balance due calculations and automatic updates
- [x] Status workflow enforcement (draft→sent→posted→cancelled)
- [x] Multi-currency support with exchange rate handling

### 🎯 Phase 2: API Layer & Documentation
#### 2.1 REST API Endpoints
- [x] CRUD endpoints for all invoicing entities
- [x] Idempotency keys on all write operations
- [x] Rate limiting (60 requests/min per user)
- [~] Structured error codes and validation (baseline in place)
- [ ] OpenAPI/Swagger documentation

#### 2.2 Web CRUD Interface
- [~] Inertia/Vue components for invoice management (controllers wired; UI ongoing)
- [x] Server-side validation with flash messages
- [x] PDF generation and download
- [x] Bulk operations and search/filter

### 🎯 Phase 3: Ledger Integration & Financial Processing
#### 3.1 Double-Entry Posting
- [~] Automatic posting to ledger when invoices are paid (posting on invoice "posted" is implemented; payment-triggered posting TBD)
- [x] AR, revenue, and tax liability account updates
- [x] Credit note (reversal) generation for cancellations
- [x] `LedgerService::post($entry)` integration with balance validation

#### 3.2 Audit Trail & Compliance
- [x] Immutable financial records with audit logging (to `audit_logs`)
- [x] Soft delete support with credit note workflows
- [x] User action tracking for all financial operations
- [~] Compliance with accounting standards (baseline enforced)

### 🎯 Phase 4: Advanced Features
#### 4.1 Reporting & Analytics
- [ ] Refresh and query logic for `accounts_receivable_mv`
- [x] Trial balance integration with invoicing data (via ledger services)
- [ ] Real-time dashboards for AR metrics
- [x] CSV export functionality

#### 4.2 Payment Processing
- [ ] Manual payment workflow with approval process
- [ ] Bank reconciliation support (CSV import)
- [x] Payment allocation algorithms
- [ ] Unmatched payment queue management

#### 4.3 Multi-tenant & Security
- [x] RLS (Row Level Security) policies enforced
- [x] Company-scoped data isolation
- [~] Permission-based access control (policies in place; granular permissions TBD)
- [ ] Data encryption for sensitive information

---

## 📊 Timeline & Milestones

### **Milestone 1: Core Invoicing (2-3 weeks)**
- ✅ Database schema
- ⏳ Laravel models & factories
- ⏳ Domain services
- ⏳ Basic CRUD operations

### **Milestone 2: API & UI (2 weeks)**
- ✅ REST API with OpenAPI docs (served via L5-Swagger, CI publishes)
- ⏳ Inertia/Vue web interface
- ⏳ PDF generation
- ⏳ Basic testing coverage

### **Milestone 3: Financial Integration (2 weeks)**
- ✅ Ledger posting integration (invoice post; payment completion auto-post)
- ✅ Audit trail implementation
- ⏳ Advanced business logic
- ⏳ Comprehensive testing

### **Milestone 4: Production Ready (1-2 weeks)**
- ⏳ Performance optimization
- ⏳ Security audit
- ⏳ Documentation completion
- ⏳ Deployment readiness

---

## ✅ Detailed Delivery Checklist (Phase Matrix)

### Database & Schema
- [x] Invoices core tables (headers, items, taxes)
- [x] Payments and allocations
- [x] Accounts Receivable table with RLS
- [x] Ledger schema (entries, lines) + RLS + triggers
- [x] Idempotency keys and audit logs
- [x] Items table + FK to invoice_items.item_id
- [~] Materialized views for AR reporting (pending)

### Models & State Machines
- [x] Invoice model with state machine (draft → sent → posted → cancelled)
- [x] Payment model with completion state and allocation helpers
- [x] PaymentAllocation with void/refund flows and invoice payment sync
- [x] JournalEntry/JournalLine models with post/void flows

### Services
- [x] InvoiceService (CRUD, send/post/cancel, PDF, duplicate)
- [x] PaymentService (create/process, allocate/auto-allocate, void/refund)
- [x] LedgerService (create/post/void journal entries)
- [x] LedgerIntegrationService (invoice post; payment post; allocation post)
- [x] CurrencyService (conversion, formatting)

### API Layer
- [x] Invoice API (CRUD + actions; idempotency; company scoping; rate limits)
- [x] Payment API (CRUD + allocate/auto-allocate/void; stats)
- [x] Standardized error envelope (includes `code`)
- [x] ApiResponder trait for consistency
- [x] OpenAPI served in-app via L5-Swagger (/api/documentation)
- [x] CI workflow to publish OpenAPI JSON from YAML

### Events & Listeners
- [x] InvoiceSent/Posted/Cancelled (+ AR update, JE creation)
- [x] Void journal entries on invoice cancel
- [x] Posted journal entry updates (listeners)

### Security & Multi-tenant
- [x] RLS policies on core tables
- [x] Company context middleware (Postgres GUC)
- [x] Policies + Gates for invoices and payments

### Tests
- [x] Auth flows, core feature coverage
- [x] Idempotency: invoice create/update/delete/actions
- [x] Invoice validations and ledger posting
- [x] Payment completion → auto-post to ledger
- [x] Allocation flows: partial allocation posting; allocation void; refunds
- [x] Error envelope (422 code)

### Docs & Ops
- [x] OpenAPI YAML (`docs/openapi/invoicing.yaml`)
- [x] L5-Swagger UI + `openapi:publish` console command
- [x] GitHub Action to publish `storage/api-docs` artifact
- [ ] User guides, runbooks, migration notes (pending)

---

## 🧭 Next Phases – Ready-to-Use Tracker Template

Copy this structure for upcoming modules (e.g., AP/Bills, Inventory, VMS):

### 1) Schema
- Tables, FKs, RLS, constraints, MVs (list per-table status)

### 2) Domain Models & State
- Models, casts, relationships, state machines

### 3) Services
- CRUD, workflows, integrations, side-effects

### 4) API
- Endpoints, validation, error envelope, idempotency, rate limits
- OpenAPI annotations/YAML + UI

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

1. **Create Invoice model** with relationships to Customer, Company, Currency
2. **Create InvoiceItem model** with line item calculations and tax support
3. **Create Payment model** with allocation logic
4. **Create Factories** for comprehensive testing
5. **Implement Money object** integration for precise financial calculations

**Definition of Done Status:**
- ✅ DB schema (COMPLETE)
- ⏳ Domain services + tests
- ⏳ Web CRUD + validation
- ⏳ API v1 + OpenAPI + idempotency
- ⏳ Audit trail for financial entities
- ⏳ Metrics and monitoring

## Risk Assessment
- **High:** Missing dependencies will break invoicing functionality
- **Medium:** Complex tax calculations may require additional logic
- **Low:** Multi-currency handling is well-defined in schema

---
*This document should be updated as tables are created and dependencies are resolved.*
