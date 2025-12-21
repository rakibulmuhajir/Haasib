# Master Contract Index — Haasib Accounting System

**Last Updated**: 2025-12-16
**Purpose**: Central registry of all schema contracts with implementation status

---

## Module Status Legend

| Status | Meaning |
|--------|---------|
| ✅ Complete | Schema + Migration + Model + Controller + Views |
| 🔶 Partial | Some components implemented |
| ⬜ Pending | Not yet started |
| 📋 Contract Only | Contract written, implementation pending |

---

## Core Foundation

| Module | Schema | Contract | Status | Notes |
|--------|--------|----------|--------|-------|
| **Auth & Users** | `auth` | [auth-contract.md](./auth-contract.md) | ✅ Complete | Users, companies, memberships, RBAC |
| **Currencies** | `public` | [currencies-schema.md](./currencies-schema.md) | ✅ Complete | ISO 4217 reference data |
| **Multi-Currency Rules** | - | [multicurrency-rules.md](./multicurrency-rules.md) | ✅ Complete | Exchange rate handling, precision rules |

---

## General Ledger (GL)

| Module | Schema | Contract | Status | Notes |
|--------|--------|----------|--------|-------|
| **Chart of Accounts** | `acct` | [coa-schema.md](./coa-schema.md) | ✅ Complete | COA views + integrity rules + default accounts workflow |
| **Fiscal Years & Periods** | `acct` | [gl-core-schema.md](./gl-core-schema.md) | ✅ Complete | Fiscal year/period pages + period close/reopen controls |
| **Transactions & Journal Entries** | `acct` | [gl-core-schema.md](./gl-core-schema.md) | ✅ Complete | Tables + models + manual journals UI implemented |
| **Posting Templates** | `acct` | [posting-schema.md](./posting-schema.md) | ✅ Complete | Tables + models + UI (create/edit/preview); default templates installer |

---

## MVP Readiness (Immediate Attention)

These are the non-✅ items that block an **Accounting MVP** (AR/AP + Banking + reliable books) based on the dependency graph and the Implementation Priority below.

| Area | Contract(s) | Current Status | MVP Impact | Immediate Deliverable |
|------|-------------|----------------|------------|-----------------------|
| **GL Core Tables** | [gl-core-schema.md](./gl-core-schema.md) | ✅ Complete | Fiscal year/period control wired end-to-end | Fiscal year/period Inertia pages + close/reopen controls |
| **Posting Engine** | [posting-schema.md](./posting-schema.md), [integration-plan.md](./integration-plan.md) | ✅ Complete | AR/AP documents post to GL via templates, link `transaction_id`, and void/delete create GL reversals (base-currency journals) | PostingService + templates |
| **COA UI/Views** | [coa-schema.md](./coa-schema.md) | ✅ Complete | Users can maintain accounts needed by posting | COA pages + seed/default accounts workflow |
| **Tax (If Required for MVP Scope)** | [tax-schema.md](./tax-schema.md) | 📋 Contract Only | Invoices/bills can’t compute VAT/GST correctly | Tax tables + document-level tax calculation + UI fields |

---

## Accounts Receivable (AR)

| Module | Schema | Contract | Status | Notes |
|--------|--------|----------|--------|-------|
| **Customers** | `acct` | [accounting-invoicing-contract.md](./accounting-invoicing-contract.md) | ✅ Complete | Customer master |
| **Invoices** | `acct` | [accounting-invoicing-contract.md](./accounting-invoicing-contract.md) | ✅ Complete | Sales invoices + line items |
| **Payments (AR)** | `acct` | [accounting-invoicing-contract.md](./accounting-invoicing-contract.md) | ✅ Complete | Customer payments + allocations |
| **Credit Notes** | `acct` | [accounting-invoicing-contract.md](./accounting-invoicing-contract.md) | ✅ Complete | Refunds/adjustments |
| **Recurring Schedules** | `acct` | [accounting-invoicing-contract.md](./accounting-invoicing-contract.md) | 🔶 Partial | Template-based generation |

---

## Accounts Payable (AP)

| Module | Schema | Contract | Status | Notes |
|--------|--------|----------|--------|-------|
| **Vendors** | `acct` | [ap-schema.md](./ap-schema.md) | 🔶 Partial | Vendor master |
| **Bills** | `acct` | [ap-schema.md](./ap-schema.md) | 🔶 Partial | Purchase invoices + line items |
| **Bill Payments** | `acct` | [ap-schema.md](./ap-schema.md) | 🔶 Partial | Vendor payments + allocations |
| **Vendor Credits** | `acct` | [ap-schema.md](./ap-schema.md) | 🔶 Partial | Debit notes/adjustments |
| **Recurring Bill Schedules** | `acct` | [ap-schema.md](./ap-schema.md) | ⬜ Pending | Template-based generation |

---

## Banking & Cash Management

| Module | Schema | Contract | Status | Notes |
|--------|--------|----------|--------|-------|
| **Banks (Reference)** | `bank` | [banking-schema.md](./banking-schema.md) | ✅ Complete | Bank catalog |
| **Company Bank Accounts** | `bank` | [banking-schema.md](./banking-schema.md) | ✅ Complete | Company's accounts |
| **Bank Transactions** | `bank` | [banking-schema.md](./banking-schema.md) | ✅ Complete | Feed/manual entries |
| **Bank Reconciliations** | `bank` | [banking-schema.md](./banking-schema.md) | ✅ Complete | Statement matching |

---

## Tax Management

| Module | Schema | Contract | Status | Notes |
|--------|--------|----------|--------|-------|
| **Jurisdictions** | `tax` | [tax-schema.md](./tax-schema.md) | 📋 Contract Only | Tax regions |
| **Tax Rates & Groups** | `tax` | [tax-schema.md](./tax-schema.md) | 📋 Contract Only | VAT/GST rates |
| **Company Tax Settings** | `tax` | [tax-schema.md](./tax-schema.md) | 📋 Contract Only | Per-tenant toggles |
| **Tax Registrations** | `tax` | [tax-schema.md](./tax-schema.md) | 📋 Contract Only | VAT numbers |

---

## Inventory & Products

| Module | Schema | Contract | Status | Notes |
|--------|--------|----------|--------|-------|
| **Item Categories** | `inv` | [inventory-schema.md](./inventory-schema.md) | ✅ Complete | Product categories |
| **Items/Products** | `inv` | [inventory-schema.md](./inventory-schema.md) | ✅ Complete | SKU master |
| **Warehouses** | `inv` | [inventory-schema.md](./inventory-schema.md) | ✅ Complete | Storage locations |
| **Stock Levels** | `inv` | [inventory-schema.md](./inventory-schema.md) | ✅ Complete | Qty per location |
| **Stock Movements** | `inv` | [inventory-schema.md](./inventory-schema.md) | ✅ Complete | In/out/adjust |
| **Inventory Costing** | `inv` | [inventory-schema.md](./inventory-schema.md) | ✅ Complete | WA/FIFO, COGS |

---

## Payroll & HR

| Module | Schema | Contract | Status | Notes |
|--------|--------|----------|--------|-------|
| **Employees** | `pay` | [payroll-schema.md](./payroll-schema.md) | ✅ Complete | Employee master |
| **Payroll Periods** | `pay` | [payroll-schema.md](./payroll-schema.md) | ✅ Complete | Pay cycles |
| **Payroll Runs** | `pay` | [payroll-schema.md](./payroll-schema.md) | ✅ Complete | Batch processing |
| **Payslips** | `pay` | [payroll-schema.md](./payroll-schema.md) | ✅ Complete | Per-employee detail |
| **Earning/Deduction Types** | `pay` | [payroll-schema.md](./payroll-schema.md) | ✅ Complete | Salary components |
| **Benefits & Leave** | `pay` | [payroll-schema.md](./payroll-schema.md) | ✅ Complete | Insurance, PTO |

---

## Reporting

| Module | Schema | Contract | Status | Notes |
|--------|--------|----------|--------|-------|
| **Report Templates** | `rpt` | [reporting-schema.md](./reporting-schema.md) | 📋 Contract Only | Metadata-driven reports |
| **Generated Reports** | `rpt` | [reporting-schema.md](./reporting-schema.md) | 📋 Contract Only | File storage |
| **Financial Statements** | `rpt` | [reporting-schema.md](./reporting-schema.md) | 📋 Contract Only | Auditable snapshots |
| **Report Functions** | `rpt` | [reporting-schema.md](./reporting-schema.md) | 📋 Contract Only | Trial balance, P&L, Balance Sheet |

---

## CRM (Customer Relationship)

| Module | Schema | Contract | Status | Notes |
|--------|--------|----------|--------|-------|
| **Contacts** | `crm` | [crm-schema.md](./crm-schema.md) | 📋 Contract Only | Linked to customers/vendors |
| **Interactions** | `crm` | [crm-schema.md](./crm-schema.md) | 📋 Contract Only | Activity log |

---

## Visitor Management (Travel)

| Module | Schema | Contract | Status | Notes |
|--------|--------|----------|--------|-------|
| **Groups** | `vms` | [vms-schema.md](./vms-schema.md) | 📋 Contract Only | Travel groups |
| **Visitors** | `vms` | [vms-schema.md](./vms-schema.md) | 📋 Contract Only | Traveler profiles |
| **Services** | `vms` | [vms-schema.md](./vms-schema.md) | 📋 Contract Only | Visa/hotel/flight |
| **Bookings** | `vms` | [vms-schema.md](./vms-schema.md) | 📋 Contract Only | Orders |
| **Vouchers & Itineraries** | `vms` | [vms-schema.md](./vms-schema.md) | 📋 Contract Only | Travel documents |

---

## System & Infrastructure

| Module | Schema | Contract | Status | Notes |
|--------|--------|----------|--------|-------|
| **Settings** | `sys` | [system-schema.md](./system-schema.md) | 📋 Contract Only | Company config |
| **API Keys** | `sys` | [system-schema.md](./system-schema.md) | 📋 Contract Only | Authentication |
| **Webhooks** | `sys` | [system-schema.md](./system-schema.md) | 📋 Contract Only | Outbound events |
| **Audit Log** | `sys` | [system-schema.md](./system-schema.md) | 📋 Contract Only | Change tracking |
| **Background Jobs** | `sys` | [system-schema.md](./system-schema.md) | 📋 Contract Only | Queue management |
| **Notifications** | `sys` | [system-schema.md](./system-schema.md) | 📋 Contract Only | User alerts |

---

## Implementation Priority

### Phase 1: GL Foundation (Required for AR/AP posting)
1. `gl-core-schema.md` - Fiscal years, periods, transactions, journal entries
2. `posting-schema.md` + `integration-plan.md` - Posting engine + validation checklist
3. `coa-schema.md` - Finish COA views + defaults needed by posting
4. `banking-schema.md` - Bank accounts for payments (schema complete; integration depends on posting)

### Phase 2: Compliance
5. `tax-schema.md` - VAT/GST handling

### Phase 3: Operations (As Needed)
6. `inventory-schema.md` - If selling products
7. `payroll-schema.md` - HR/payroll processing

### Phase 4: Extensions
8. `reporting-schema.md` - Financial reports
9. `crm-schema.md` - Enhanced CRM
10. `vms-schema.md` - Travel agency vertical
11. `system-schema.md` - Infrastructure

---

## Schema Dependency Graph

```
public.currencies ─────────────────────────────────────────┐
                                                           │
auth.companies ────────────────────────────────────────────┤
       │                                                   │
       ├── acct.accounts (COA) ◄───────────────────────────┤
       │         │                                         │
       │         ▼                                         │
       ├── acct.fiscal_years ──► acct.accounting_periods   │
       │         │                       │                 │
       │         ▼                       ▼                 │
       │    acct.transactions ◄── acct.journal_entries     │
       │         ▲                       ▲                 │
       │         │                       │                 │
       ├── acct.customers ──► acct.invoices ───────────────┤
       │         │                  │                      │
       │         ▼                  ▼                      │
       │    acct.payments ──► acct.payment_allocations     │
       │                                                   │
       ├── acct.vendors ──► acct.bills ────────────────────┤
       │         │                │                        │
       │         ▼                ▼                        │
       │    acct.bill_payments ► acct.bill_payment_alloc   │
       │                                                   │
       ├── bank.company_bank_accounts ◄────────────────────┤
       │         │                                         │
       │         ▼                                         │
       │    bank.bank_transactions                         │
       │         │                                         │
       │         ▼                                         │
       │    bank.bank_reconciliations                      │
       │                                                   │
       ├── tax.jurisdictions ──► tax.tax_rates             │
       │                              │                    │
       │                              ▼                    │
       │                         tax.tax_groups            │
       │                                                   │
       ├── inv.items ──► inv.stock_levels                  │
       │         │              │                          │
       │         ▼              ▼                          │
       │    inv.stock_movements ──► inv.cogs_entries       │
       │                                                   │
       └── pay.employees ──► pay.payslips                  │
                                   │                       │
                                   ▼                       │
                            pay.payslip_lines              │
```

---

## Contract File Naming Convention

| Pattern | Example | Purpose |
|---------|---------|---------|
| `{domain}-schema.md` | `ap-schema.md` | Full schema contract |
| `{domain}-contract.md` | `auth-contract.md` | Legacy naming (migrate) |
| `{domain}-rules.md` | `multicurrency-rules.md` | Cross-cutting rules |
| `{domain}-specs.md` | `revenue-command-specs.md` | CLI/command specs |

---

## Updating This Index

When implementing a module:
1. Update status from 📋 → 🔶 when starting
2. Update status from 🔶 → ✅ when complete
3. Add notes about any deviations from contract
4. Update dependency graph if new relationships added
