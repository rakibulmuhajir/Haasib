# Payments Phase Tracker

**Created:** 2025-09-22
**Module:** Accounts Payable (Payments)
**Schema File:** `docs/schemas/12_ap.sql`
**Status:** Development Phase

## Tables to Create for Payments Phase

### Core Payments Tables
- [ ] `vendors` - Vendor/supplier management
- [ ] `bills` - Bills received from vendors
- [ ] `bill_items` - Bill line items
- [ ] `bill_payments` - Bill payment records
- [ ] `payment_allocations` - Payment to bill allocations
- [ ] `accounts_payable_mv` - AP summary/aging materialized view

### Payment Processing Tables
- [ ] `payment_methods` - Payment method configuration
- [ ] `payment_terms` - Payment terms and conditions
- [ ] `recurring_bills` - Recurring bill templates
- [ ] `vendor_credits` - Vendor credit notes/refunds

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
2. **Multi-tax per line item** - Support for various tax regimes
3. **Payment allocation** - Apply payments to multiple bills
4. **Aging reports** - Based on `accounts_payable_mv` materialized view
5. **Audit trail** - Created/updated by tracking
6. **Soft deletes** - `deleted_at` columns for data preservation
7. **Recurring bills** - Automated bill generation
8. **Vendor credits** - Credit memo and refund handling

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
- Payment allocation validation
- Balance due calculations
- Early payment discounts
- Late payment penalties

### RBAC Permissions (to be created)
- `bills.view`, `bills.create`, `bills.edit`, `bills.delete`, `bills.approve`, `bills.pay`
- `payments.view`, `payments.create`, `payments.edit`, `payments.delete`, `payments.allocate`
- `vendors.view`, `vendors.create`, `vendors.edit`, `vendors.delete`

## Progress Tracking

### Planned
- [ ] Analysis of schema requirements
- [ ] Dependencies identified
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

Based on **Definition of Done** (dev-plan.md) and **Technical Brief** requirements

### 🎯 Phase 1: Core Payments Foundation
**Priority: HIGH** - Essential for accounts payable management

#### 1.1 Laravel Models & Factories
- [ ] Create Eloquent models for all payments tables
- [ ] Add relationships, mutators, accessors, business rules
- [ ] Create factories for testing with realistic data
- [ ] Implement Money object integration for financial calculations

#### 1.2 Domain Services
- [ ] `BillService` - CRUD operations, PDF generation, status workflow
- [ ] `PaymentService` - Payment processing and allocation logic
- [ ] `VendorService` - Vendor management and credit tracking
- [ ] `LedgerIntegrationService` - Posting to ledger and void/reversal support

#### 1.3 Business Logic Implementation
- [ ] Bill numbering (company-scoped with validation)
- [ ] Payment allocation validation (prevent over-allocation)
- [ ] Balance due calculations and automatic updates
- [ ] Status workflow enforcement (draft→received→approved→paid→void)
- [ ] Multi-currency support with exchange rate handling
- [ ] Early payment discount calculations
- [ ] Recurring bill automation

### 🎯 Phase 2: CommandBus Integration
**Priority: HIGH** - Following established pattern from invoicing

#### 2.1 Command Facade Implementation
- [ ] `App\Actions\Payments\BillCreate` - Create new bill with idempotency
- [ ] `App\Actions\Payments\BillUpdate` - Update bill details
- [ ] `App\Actions\Payments\BillApprove` - Approve bill for payment
- [ ] `App\Actions\Payments\BillPay` - Process bill payment
- [ ] `App\Actions\Payments\BillVoid` - Void/cancel bill
- [ ] `App\Actions\Payments\PaymentCreate` - Create payment allocation
- [ ] `App\Actions\Payments\VendorCreate` - Create new vendor
- [ ] `App\Actions\Payments\VendorUpdate` - Update vendor details

#### 2.2 CommandBus Features
- [ ] Idempotency keys on all write operations
- [ ] Rate limiting (60 requests/min per user)
- [ ] Structured error codes and validation
- [ ] Standardized response format
- [ ] Audit logging for all operations

#### 2.3 Web CRUD Interface
- [ ] Inertia/Vue components for bill and payment management
- [ ] Server-side validation with flash messages
- [ ] PDF generation and download for bills
- [ ] Bulk operations and search/filter
- [ ] Vendor portal integration (future)

### 🎯 Phase 3: Ledger Integration & Financial Processing
#### 3.1 Double-Entry Posting
- [ ] Automatic posting to ledger when bills are approved/paid
- [ ] AP, expense, and tax liability account updates
- [ ] Credit note (reversal) generation for cancellations
- [ ] `LedgerService::post($entry)` integration with balance validation

#### 3.2 Audit Trail & Compliance
- [ ] Immutable financial records with audit logging (to `audit_logs`)
- [ ] Soft delete support with credit note workflows
- [ ] User action tracking for all financial operations
- [ ] Compliance with accounting standards

### 🎯 Phase 4: Advanced Features
#### 4.1 Reporting & Analytics
- [ ] Refresh and query logic for `accounts_payable_mv`
- [ ] Cash flow forecasting based on payment schedules
- [ ] Real-time dashboards for AP metrics
- [ ] Vendor performance analytics
- [ ] CSV export functionality

#### 4.2 Payment Processing
- [ ] Bank integration for ACH/wire transfers
- [ ] Credit card processing integration
- [ ] Payment approval workflows
- [ ] Bank reconciliation support (CSV import)
- [ ] Payment scheduling automation

#### 4.3 Multi-tenant & Security
- [ ] RLS (Row Level Security) policies enforced
- [ ] Company-scoped data isolation
- [ ] Permission-based access control
- [ ] Data encryption for sensitive information
- [ ] Vendor self-service portal (future)

---

## 📊 Timeline & Milestones

### **Milestone 1: Core Payments (3-4 weeks)**
- [ ] Database schema
- [ ] Laravel models & factories
- [ ] Domain services
- [ ] Basic CRUD operations

### **Milestone 2: CommandBus & UI (2-3 weeks)**
- [ ] Command facades with idempotency
- [ ] Web interface integration
- [ ] PDF generation for bills
- [ ] Basic testing coverage

### **Milestone 3: Financial Integration (2-3 weeks)**
- [ ] Ledger posting integration
- [ ] Audit trail implementation
- [ ] Advanced business logic
- [ ] Comprehensive testing

### **Milestone 4: Production Ready (1-2 weeks)**
- [ ] Performance optimization
- [ ] Security audit
- [ ] Documentation completion
- [ ] Deployment readiness

---

## ✅ Detailed Delivery Checklist (Phase Matrix)

### Database & Schema
- [ ] Payments core tables (bills, items, payments, allocations)
- [ ] Vendor management tables
- [ ] Accounts Payable table with RLS
- [ ] Recurring bills and payment terms
- [ ] Idempotency keys and audit logs
- [ ] Materialized views for AP reporting

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
- [ ] Bill Actions (create, update, approve, pay, void)
- [ ] Payment Actions (create, allocate, void, refund)
- [ ] Vendor Actions (create, update, delete, credit)
- [ ] Command facades in `app/Actions/Payments/`
- [ ] Idempotency key support with unique indexes
- [ ] Standardized error envelope (includes `code`)
- [ ] Rate limiting and authorization

### Events & Listeners
- [ ] BillReceived/Approved/Paid/Voided (+ AP update, JE creation)
- [ ] PaymentProcessed/Allocated/Voided
- [ ] Void journal entries on bill void
- [ ] Posted journal entry updates (listeners)
- [ ] RecurringBillGenerated event

### Security & Multi-tenant
- [ ] RLS policies on core tables
- [ ] Company context middleware (Postgres GUC)
- [ ] Policies + Gates for bills, payments, and vendors
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