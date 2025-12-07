# Master Contract Index — Haasib Accounting System

**Last Updated**: 2025-12-07
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
| **Chart of Accounts** | `acct` | [coa-schema.md](./coa-schema.md) | 🔶 Partial | Migration done, views in progress |
| **Fiscal Years & Periods** | `acct` | [gl-core-schema.md](./gl-core-schema.md) | 📋 Contract Only | Foundation for period close |
| **Transactions & Journal Entries** | `acct` | [gl-core-schema.md](./gl-core-schema.md) | 📋 Contract Only | Double-entry backbone |
| **Posting Templates** | `acct` | [posting-schema.md](./posting-schema.md) | 📋 Contract Only | Auto-post AR/AP to GL |

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
| **Banks (Reference)** | `bank` | [banking-schema.md](./banking-schema.md) | 📋 Contract Only | Bank catalog |
| **Company Bank Accounts** | `bank` | [banking-schema.md](./banking-schema.md) | 📋 Contract Only | Company's accounts |
| **Bank Transactions** | `bank` | [banking-schema.md](./banking-schema.md) | 📋 Contract Only | Feed/manual entries |
| **Bank Reconciliations** | `bank` | [banking-schema.md](./banking-schema.md) | 📋 Contract Only | Statement matching |

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
| **Item Categories** | `inv` | [inventory-schema.md](./inventory-schema.md) | 📋 Contract Only | Product categories |
| **Items/Products** | `inv` | [inventory-schema.md](./inventory-schema.md) | 📋 Contract Only | SKU master |
| **Warehouses** | `inv` | [inventory-schema.md](./inventory-schema.md) | 📋 Contract Only | Storage locations |
| **Stock Levels** | `inv` | [inventory-schema.md](./inventory-schema.md) | 📋 Contract Only | Qty per location |
| **Stock Movements** | `inv` | [inventory-schema.md](./inventory-schema.md) | 📋 Contract Only | In/out/adjust |
| **Inventory Costing** | `inv` | [inventory-schema.md](./inventory-schema.md) | 📋 Contract Only | WA/FIFO, COGS |

---

## Payroll & HR

| Module | Schema | Contract | Status | Notes |
|--------|--------|----------|--------|-------|
| **Employees** | `pay` | [payroll-schema.md](./payroll-schema.md) | 📋 Contract Only | Employee master |
| **Payroll Periods** | `pay` | [payroll-schema.md](./payroll-schema.md) | 📋 Contract Only | Pay cycles |
| **Payroll Runs** | `pay` | [payroll-schema.md](./payroll-schema.md) | 📋 Contract Only | Batch processing |
| **Payslips** | `pay` | [payroll-schema.md](./payroll-schema.md) | 📋 Contract Only | Per-employee detail |
| **Earning/Deduction Types** | `pay` | [payroll-schema.md](./payroll-schema.md) | 📋 Contract Only | Salary components |
| **Benefits & Leave** | `pay` | [payroll-schema.md](./payroll-schema.md) | 📋 Contract Only | Insurance, PTO |

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
2. `posting-schema.md` - Auto-post templates
3. `banking-schema.md` - Bank accounts for payments

### Phase 2: Compliance
4. `tax-schema.md` - VAT/GST handling

### Phase 3: Operations (As Needed)
5. `inventory-schema.md` - If selling products
6. `payroll-schema.md` - HR/payroll processing

### Phase 4: Extensions
7. `reporting-schema.md` - Financial reports
8. `crm-schema.md` - Enhanced CRM
9. `vms-schema.md` - Travel agency vertical
10. `system-schema.md` - Infrastructure

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
