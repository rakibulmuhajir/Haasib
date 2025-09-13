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
- [x] `accounts_receivable` - AR summary/aging table (2025_09_13_090500_create_accounts_receivable_table.php)

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
4. **Aging reports** - Based on `accounts_receivable` table
5. **Audit trail** - Created/updated by tracking
6. **Soft deletes** - `deleted_at` columns for data preservation

### Foreign Key Relationships
- `invoices.customer_id` → `crm.customers.customer_id`
- `invoice_items.item_id` → `inv.items.item_id`
- `payments.entity_id` → `crm.customers.customer_id`
- All tables link to `core.companies.company_id` (multi-tenant)

### Business Logic
- Invoice numbering (company-scoped)
- Payment allocation validation
- Balance due calculations
- Status workflow (draft → sent → posted → cancelled)
- Payment status tracking (unpaid → partial → paid → overpaid)

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

#### 1.1 Laravel Models & Factories ⏳ **IN PROGRESS**
- [ ] Create Eloquent models for all invoicing tables
- [ ] Add relationships, mutators, accessors, business rules
- [ ] Create factories for testing with realistic data
- [ ] Implement Money object integration for financial calculations

#### 1.2 Domain Services
- [ ] `InvoiceService` - CRUD operations, PDF generation, status workflow
- [ ] `PaymentService` - Payment processing and allocation logic
- [ ] `TaxCalculator` - Multi-tax calculations (AE-VAT, PK-GST presets)
- [ ] `LedgerIntegrationService` - Posting to ledger on paid invoices

#### 1.3 Business Logic Implementation
- [ ] Invoice numbering (company-scoped with validation)
- [ ] Payment allocation validation (prevent over-allocation)
- [ ] Balance due calculations and automatic updates
- [ ] Status workflow enforcement (draft→sent→posted→cancelled)
- [ ] Multi-currency support with exchange rate handling

### 🎯 Phase 2: API Layer & Documentation
#### 2.1 REST API Endpoints
- [ ] CRUD endpoints for all invoicing entities
- [ ] Idempotency keys on all write operations
- [ ] Rate limiting (60 requests/min per user)
- [ ] Structured error codes and validation
- [ ] OpenAPI/Swagger documentation

#### 2.2 Web CRUD Interface
- [ ] Inertia/Vue components for invoice management
- [ ] Server-side validation with flash messages
- [ ] PDF generation and download
- [ ] Bulk operations and search/filter

### 🎯 Phase 3: Ledger Integration & Financial Processing
#### 3.1 Double-Entry Posting
- [ ] Automatic posting to ledger when invoices are paid
- [ ] AR, revenue, and tax liability account updates
- [ ] Credit note generation for voids/cancellations
- [ ] `LedgerService::post($entry)` integration with balance validation

#### 3.2 Audit Trail & Compliance
- [ ] Immutable financial records with audit logging
- [ ] Soft delete support with credit note workflows
- [ ] User action tracking for all financial operations
- [ ] Compliance with accounting standards

### 🎯 Phase 4: Advanced Features
#### 4.1 Reporting & Analytics
- [ ] Materialized views for aging reports (`aging_report_mv`)
- [ ] Trial balance integration with invoicing data
- [ ] Real-time dashboards for AR metrics
- [ ] CSV export functionality

#### 4.2 Payment Processing
- [ ] Manual payment workflow with approval process
- [ ] Bank reconciliation support (CSV import)
- [ ] Payment allocation algorithms
- [ ] Unmatched payment queue management

#### 4.3 Multi-tenant & Security
- [ ] RLS (Row Level Security) policies enforced
- [ ] Company-scoped data isolation
- [ ] Permission-based access control
- [ ] Data encryption for sensitive information

---

## 📊 Timeline & Milestones

### **Milestone 1: Core Invoicing (2-3 weeks)**
- ✅ Database schema
- ⏳ Laravel models & factories 
- ⏳ Domain services
- ⏳ Basic CRUD operations

### **Milestone 2: API & UI (2 weeks)**
- ⏳ REST API with OpenAPI docs
- ⏳ Inertia/Vue web interface
- ⏳ PDF generation
- ⏳ Basic testing coverage

### **Milestone 3: Financial Integration (2 weeks)**
- ⏳ Ledger posting integration
- ⏳ Audit trail implementation
- ⏳ Advanced business logic
- ⏳ Comprehensive testing

### **Milestone 4: Production Ready (1-2 weeks)**
- ⏳ Performance optimization
- ⏳ Security audit
- ⏳ Documentation completion
- ⏳ Deployment readiness

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