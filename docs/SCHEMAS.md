# Haasib Database Schemas Reference

**Complete guide to all PostgreSQL schemas, tables, relationships, and data models**

---

## Quick Navigation

1. **[Schema Overview](#schema-overview)** - All 11 schemas at a glance
2. **[auth Schema](#auth-schema)** - Users, companies, permissions
3. **[acct Schema](#acct-schema)** - General ledger, invoices, bills
4. **[bank Schema](#bank-schema)** - Bank accounts, transactions
5. **[inv Schema](#inv-schema)** - Inventory, stock, items
6. **[pay Schema](#pay-schema)** - Payroll, employees, leaves
7. **[Other Schemas](#other-schemas)** - Tax, reports, CRM, etc.
8. **[Common Queries](#common-queries)** - Sample queries

---

## Schema Overview

Haasib uses **PostgreSQL with 11 separate schemas** for logical data separation:

```
haasib_production (database)
├── auth       (8 tables)   - Users, companies, permissions, roles
├── acct       (18 tables)  - General ledger, AR, AP, invoices, bills
├── bank       (4 tables)   - Bank accounts, transactions, reconciliation
├── inv        (6 tables)   - Inventory, items, warehouses, stock
├── pay        (9 tables)   - Payroll, employees, payslips, leaves
├── tax        (6 tables)   - Tax config, rates, jurisdictions
├── rpt        (5 tables)   - Reports, templates, exports
├── crm        (4 tables)   - Contacts, interactions, leads
├── vms        (3 tables)   - Visitors, travel requests
├── sys        (3 tables)   - Settings, webhooks, audit logs
└── public     (1 table)    - Currencies (reference)
```

### Key Characteristics

- **Multi-tenancy:** All tables have `company_id` for data isolation
- **UUIDs:** Primary keys are UUIDs (string type), not auto-increment
- **Timestamps:** All tables have `created_at`, `updated_at`
- **RLS (Row-Level Security):** Policies ensure company-level data isolation
- **Soft Deletes:** Some tables use `deleted_at` for logical deletion

---

## auth Schema

**Purpose:** Authentication, user management, roles, permissions

### Tables

#### users
```sql
CREATE TABLE auth.users (
    id UUID PRIMARY KEY,
    name VARCHAR,
    email VARCHAR UNIQUE,
    email_verified_at TIMESTAMP,
    password VARCHAR,
    remember_token VARCHAR,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```
- **Purpose:** User account records
- **Key Fields:**
  - `id` - UUID, primary key
  - `email` - Unique email address
  - `password` - Hashed password
  - `email_verified_at` - Email verification timestamp
- **Related:** User can belong to multiple companies via `company_user`

#### companies
```sql
CREATE TABLE auth.companies (
    id UUID PRIMARY KEY,
    name VARCHAR,
    slug VARCHAR UNIQUE,
    owner_id UUID REFERENCES auth.users(id),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```
- **Purpose:** Tenant organizations
- **Key Fields:**
  - `slug` - URL-friendly identifier
  - `owner_id` - Company founder
- **Related:** One company has many users, many invoices, many GL accounts

#### company_user
```sql
CREATE TABLE auth.company_user (
    id UUID PRIMARY KEY,
    company_id UUID REFERENCES auth.companies(id),
    user_id UUID REFERENCES auth.users(id),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```
- **Purpose:** User membership in companies
- **Key Fields:**
  - `company_id` - Company reference
  - `user_id` - User reference
- **Note:** User can have roles through this relationship

#### permissions
```sql
CREATE TABLE auth.permissions (
    id UUID PRIMARY KEY,
    name VARCHAR UNIQUE,
    guard_name VARCHAR DEFAULT 'web',
    created_at TIMESTAMP
);
```
- **Purpose:** Permission definitions (from Spatie Laravel Permission)
- **Examples:** `invoice.create`, `customer.view`, etc.
- **Total Count:** 151+ permissions

#### roles
```sql
CREATE TABLE auth.roles (
    id UUID PRIMARY KEY,
    name VARCHAR,
    guard_name VARCHAR DEFAULT 'web',
    created_at TIMESTAMP
);
```
- **Purpose:** Role definitions
- **Examples:** Owner, Admin, Manager, Accountant, Viewer
- **Usage:** Assigned to users; has many permissions

#### role_has_permissions
```sql
CREATE TABLE auth.role_has_permissions (
    permission_id UUID REFERENCES auth.permissions(id),
    role_id UUID REFERENCES auth.roles(id),
    PRIMARY KEY (permission_id, role_id)
);
```
- **Purpose:** Associates permissions with roles
- **Example:** Admin role has `invoice.create`, `invoice.view`, etc.

#### model_has_permissions
```sql
CREATE TABLE auth.model_has_permissions (
    permission_id UUID REFERENCES auth.permissions(id),
    model_type VARCHAR,
    model_id UUID,
    PRIMARY KEY (permission_id, model_type, model_id)
);
```
- **Purpose:** Direct user permissions (without going through role)
- **Usage:** Rare; for one-off permission grants

#### company_invitations
```sql
CREATE TABLE auth.company_invitations (
    id UUID PRIMARY KEY,
    company_id UUID REFERENCES auth.companies(id),
    email VARCHAR,
    token VARCHAR UNIQUE,
    accepted_at TIMESTAMP,
    created_at TIMESTAMP
);
```
- **Purpose:** Tracks company invitations
- **Workflow:** User sends invitation → Token generated → Recipient accepts

---

## acct Schema

**Purpose:** General ledger, accounting core, AR (invoices), AP (bills)

### Core GL Tables

#### accounts
```sql
CREATE TABLE acct.accounts (
    id UUID PRIMARY KEY,
    company_id UUID REFERENCES auth.companies(id),
    code VARCHAR UNIQUE,
    name VARCHAR,
    account_type VARCHAR (asset|liability|equity|revenue|expense),
    parent_account_id UUID REFERENCES acct.accounts(id),
    is_placeholder BOOLEAN,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```
- **Purpose:** Chart of Accounts
- **Key Fields:**
  - `code` - Account number (e.g., "1000", "2000")
  - `account_type` - GL account classification
  - `parent_account_id` - Hierarchical structure
  - `is_placeholder` - If true, cannot have GL entries
- **Relationships:** Has many GL transactions and journal entries

#### fiscal_years
```sql
CREATE TABLE acct.fiscal_years (
    id UUID PRIMARY KEY,
    company_id UUID REFERENCES auth.companies(id),
    name VARCHAR,
    start_date DATE,
    end_date DATE,
    is_closed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP
);
```
- **Purpose:** Financial year definitions
- **Example:** "FY 2025" from Jan 1 to Dec 31, 2025
- **Related:** Contains accounting periods

#### accounting_periods
```sql
CREATE TABLE acct.accounting_periods (
    id UUID PRIMARY KEY,
    fiscal_year_id UUID REFERENCES acct.fiscal_years(id),
    name VARCHAR,
    start_date DATE,
    end_date DATE,
    is_closed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP
);
```
- **Purpose:** Monthly/quarterly periods within fiscal year
- **Example:** Period 1, Period 2, etc.
- **Usage:** For financial reporting

#### transactions (GL Transactions)
```sql
CREATE TABLE acct.transactions (
    id UUID PRIMARY KEY,
    company_id UUID REFERENCES auth.companies(id),
    transaction_type VARCHAR (invoice|bill|payment|journal),
    reference_id UUID,
    transaction_date DATE,
    description VARCHAR,
    amount DECIMAL(15,2),
    status VARCHAR (draft|posted|voided),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```
- **Purpose:** Core GL transaction ledger (immutable)
- **Key Fields:**
  - `transaction_type` - What created the transaction
  - `reference_id` - Link to source (invoice, bill, etc.)
  - `status` - Never modified once posted
- **Note:** This is the audit trail

#### journal_entries (GL Detail)
```sql
CREATE TABLE acct.journal_entries (
    id UUID PRIMARY KEY,
    transaction_id UUID REFERENCES acct.transactions(id),
    account_id UUID REFERENCES acct.accounts(id),
    debit_amount DECIMAL(15,2) DEFAULT 0,
    credit_amount DECIMAL(15,2) DEFAULT 0,
    description VARCHAR,
    created_at TIMESTAMP
);
```
- **Purpose:** Debit/credit entries for each GL transaction
- **Rule:** For each transaction: sum(debits) = sum(credits)
- **Example:** Invoice → 2 journal entries (Receivable Dr, Revenue Cr)

### AR (Receivables) Tables

#### customers
```sql
CREATE TABLE acct.customers (
    id UUID PRIMARY KEY,
    company_id UUID REFERENCES auth.companies(id),
    name VARCHAR,
    email VARCHAR,
    phone VARCHAR,
    address VARCHAR,
    tax_id VARCHAR,
    currency_code VARCHAR,
    credit_limit DECIMAL(15,2),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```
- **Purpose:** Customer master records
- **Related:** Has many invoices, payments

#### invoices
```sql
CREATE TABLE acct.invoices (
    id UUID PRIMARY KEY,
    company_id UUID REFERENCES auth.companies(id),
    customer_id UUID REFERENCES acct.customers(id),
    invoice_number VARCHAR UNIQUE,
    invoice_date DATE,
    due_date DATE,
    amount DECIMAL(15,2),
    tax_amount DECIMAL(15,2),
    status VARCHAR (draft|sent|paid|overdue|voided),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```
- **Purpose:** Sales invoices
- **Related:** Has many line items, payments

#### invoice_line_items
```sql
CREATE TABLE acct.invoice_line_items (
    id UUID PRIMARY KEY,
    invoice_id UUID REFERENCES acct.invoices(id),
    description VARCHAR,
    quantity DECIMAL(15,4),
    unit_price DECIMAL(15,2),
    line_total DECIMAL(15,2),
    tax_amount DECIMAL(15,2),
    created_at TIMESTAMP
);
```
- **Purpose:** Individual items on invoice

#### payments
```sql
CREATE TABLE acct.payments (
    id UUID PRIMARY KEY,
    company_id UUID REFERENCES auth.companies(id),
    customer_id UUID REFERENCES acct.customers(id),
    payment_date DATE,
    amount DECIMAL(15,2),
    payment_method VARCHAR (cash|check|ach|card),
    status VARCHAR (pending|applied|voided),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```
- **Purpose:** Customer payments
- **Related:** Has payment allocations

#### payment_allocations
```sql
CREATE TABLE acct.payment_allocations (
    id UUID PRIMARY KEY,
    payment_id UUID REFERENCES acct.payments(id),
    invoice_id UUID REFERENCES acct.invoices(id),
    allocated_amount DECIMAL(15,2),
    created_at TIMESTAMP
);
```
- **Purpose:** Associates payment to invoice
- **Example:** $1000 payment applied to $500 Invoice A + $500 Invoice B

#### credit_notes
```sql
CREATE TABLE acct.credit_notes (
    id UUID PRIMARY KEY,
    company_id UUID REFERENCES auth.companies(id),
    customer_id UUID REFERENCES acct.customers(id),
    credit_number VARCHAR UNIQUE,
    credit_date DATE,
    amount DECIMAL(15,2),
    reason VARCHAR,
    status VARCHAR (draft|issued|applied|voided),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```
- **Purpose:** Customer refunds/adjustments

### AP (Payables) Tables

#### vendors
```sql
CREATE TABLE acct.vendors (
    id UUID PRIMARY KEY,
    company_id UUID REFERENCES auth.companies(id),
    name VARCHAR,
    email VARCHAR,
    phone VARCHAR,
    address VARCHAR,
    tax_id VARCHAR,
    currency_code VARCHAR,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```
- **Purpose:** Supplier/vendor master records

#### bills
```sql
CREATE TABLE acct.bills (
    id UUID PRIMARY KEY,
    company_id UUID REFERENCES auth.companies(id),
    vendor_id UUID REFERENCES acct.vendors(id),
    bill_number VARCHAR UNIQUE,
    bill_date DATE,
    due_date DATE,
    amount DECIMAL(15,2),
    tax_amount DECIMAL(15,2),
    status VARCHAR (draft|received|paid|overdue|voided),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```
- **Purpose:** Purchase bills/invoices

#### bill_line_items
```sql
CREATE TABLE acct.bill_line_items (
    id UUID PRIMARY KEY,
    bill_id UUID REFERENCES acct.bills(id),
    description VARCHAR,
    quantity DECIMAL(15,4),
    unit_price DECIMAL(15,2),
    line_total DECIMAL(15,2),
    tax_amount DECIMAL(15,2),
    created_at TIMESTAMP
);
```
- **Purpose:** Individual items on bill

#### bill_payments
```sql
CREATE TABLE acct.bill_payments (
    id UUID PRIMARY KEY,
    company_id UUID REFERENCES auth.companies(id),
    vendor_id UUID REFERENCES acct.vendors(id),
    payment_date DATE,
    amount DECIMAL(15,2),
    payment_method VARCHAR,
    status VARCHAR (pending|applied|voided),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```
- **Purpose:** Vendor payments

#### bill_payment_allocations
```sql
CREATE TABLE acct.bill_payment_allocations (
    id UUID PRIMARY KEY,
    bill_payment_id UUID REFERENCES acct.bill_payments(id),
    bill_id UUID REFERENCES acct.bills(id),
    allocated_amount DECIMAL(15,2),
    created_at TIMESTAMP
);
```
- **Purpose:** Associates vendor payment to bill

---

## bank Schema

**Purpose:** Bank account and reconciliation management

### Tables

#### banks
```sql
CREATE TABLE bank.banks (
    id UUID PRIMARY KEY,
    name VARCHAR,
    code VARCHAR,
    country_code VARCHAR,
    created_at TIMESTAMP
);
```
- **Purpose:** Reference data (bank list)

#### company_bank_accounts
```sql
CREATE TABLE bank.company_bank_accounts (
    id UUID PRIMARY KEY,
    company_id UUID REFERENCES auth.companies(id),
    bank_id UUID REFERENCES bank.banks(id),
    account_number VARCHAR,
    currency_code VARCHAR,
    gl_account_id UUID REFERENCES acct.accounts(id),
    balance DECIMAL(15,2),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```
- **Purpose:** Company's bank accounts

#### bank_transactions
```sql
CREATE TABLE bank.bank_transactions (
    id UUID PRIMARY KEY,
    company_id UUID REFERENCES auth.companies(id),
    company_bank_account_id UUID,
    transaction_date DATE,
    description VARCHAR,
    debit_amount DECIMAL(15,2),
    credit_amount DECIMAL(15,2),
    balance DECIMAL(15,2),
    source VARCHAR (statement|manual),
    status VARCHAR (pending|matched|unmatched),
    created_at TIMESTAMP
);
```
- **Purpose:** Bank statement transactions

#### bank_reconciliations
```sql
CREATE TABLE bank.bank_reconciliations (
    id UUID PRIMARY KEY,
    company_id UUID REFERENCES auth.companies(id),
    company_bank_account_id UUID,
    statement_date DATE,
    statement_balance DECIMAL(15,2),
    reconciled_balance DECIMAL(15,2),
    status VARCHAR (in_progress|complete),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```
- **Purpose:** Monthly bank reconciliation

---

## inv Schema

**Purpose:** Inventory, products, stock management

### Tables

#### item_categories
```sql
CREATE TABLE inv.item_categories (
    id UUID PRIMARY KEY,
    company_id UUID REFERENCES auth.companies(id),
    name VARCHAR,
    parent_category_id UUID REFERENCES inv.item_categories(id),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```
- **Purpose:** Product categories (hierarchical)

#### items
```sql
CREATE TABLE inv.items (
    id UUID PRIMARY KEY,
    company_id UUID REFERENCES auth.companies(id),
    sku VARCHAR UNIQUE,
    name VARCHAR,
    description VARCHAR,
    category_id UUID REFERENCES inv.item_categories(id),
    unit_of_measure VARCHAR,
    unit_cost DECIMAL(15,2),
    unit_price DECIMAL(15,2),
    tax_applicable BOOLEAN,
    is_active BOOLEAN,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```
- **Purpose:** Product/item master records

#### warehouses
```sql
CREATE TABLE inv.warehouses (
    id UUID PRIMARY KEY,
    company_id UUID REFERENCES auth.companies(id),
    name VARCHAR,
    location VARCHAR,
    is_default BOOLEAN,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```
- **Purpose:** Storage locations

#### stock_levels
```sql
CREATE TABLE inv.stock_levels (
    id UUID PRIMARY KEY,
    item_id UUID REFERENCES inv.items(id),
    warehouse_id UUID REFERENCES inv.warehouses(id),
    quantity_on_hand DECIMAL(15,4),
    quantity_available DECIMAL(15,4),
    quantity_committed DECIMAL(15,4),
    last_count_date DATE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```
- **Purpose:** Current inventory per location

#### stock_movements
```sql
CREATE TABLE inv.stock_movements (
    id UUID PRIMARY KEY,
    company_id UUID REFERENCES auth.companies(id),
    item_id UUID REFERENCES inv.items(id),
    warehouse_id UUID REFERENCES inv.warehouses(id),
    movement_type VARCHAR (in|out|adjust),
    quantity DECIMAL(15,4),
    reference_type VARCHAR (invoice|bill|manual),
    reference_id UUID,
    created_at TIMESTAMP
);
```
- **Purpose:** Audit trail of stock changes

#### cogs_entries
```sql
CREATE TABLE inv.cogs_entries (
    id UUID PRIMARY KEY,
    company_id UUID REFERENCES auth.companies(id),
    item_id UUID REFERENCES inv.items(id),
    transaction_id UUID,
    quantity DECIMAL(15,4),
    unit_cost DECIMAL(15,2),
    total_cost DECIMAL(15,2),
    created_at TIMESTAMP
);
```
- **Purpose:** Cost of goods sold tracking

---

## pay Schema

**Purpose:** Payroll and human resources

### Tables

#### employees
```sql
CREATE TABLE pay.employees (
    id UUID PRIMARY KEY,
    company_id UUID REFERENCES auth.companies(id),
    employee_id VARCHAR UNIQUE,
    first_name VARCHAR,
    last_name VARCHAR,
    email VARCHAR,
    phone VARCHAR,
    position VARCHAR,
    department VARCHAR,
    hire_date DATE,
    salary_frequency VARCHAR (monthly|weekly|bi-weekly),
    base_salary DECIMAL(15,2),
    is_active BOOLEAN,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```
- **Purpose:** Employee master records

#### payroll_periods
```sql
CREATE TABLE pay.payroll_periods (
    id UUID PRIMARY KEY,
    company_id UUID REFERENCES auth.companies(id),
    period_name VARCHAR,
    start_date DATE,
    end_date DATE,
    payment_date DATE,
    is_closed BOOLEAN,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```
- **Purpose:** Pay cycle definitions

#### payroll_runs
```sql
CREATE TABLE pay.payroll_runs (
    id UUID PRIMARY KEY,
    company_id UUID REFERENCES auth.companies(id),
    payroll_period_id UUID REFERENCES pay.payroll_periods(id),
    total_gross DECIMAL(15,2),
    total_deductions DECIMAL(15,2),
    total_net DECIMAL(15,2),
    status VARCHAR (draft|processed|paid|voided),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```
- **Purpose:** Batch payroll processing

#### payslips
```sql
CREATE TABLE pay.payslips (
    id UUID PRIMARY KEY,
    company_id UUID REFERENCES auth.companies(id),
    employee_id UUID REFERENCES pay.employees(id),
    payroll_run_id UUID REFERENCES pay.payroll_runs(id),
    gross_amount DECIMAL(15,2),
    total_deductions DECIMAL(15,2),
    net_amount DECIMAL(15,2),
    status VARCHAR (draft|approved|paid|voided),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```
- **Purpose:** Individual paychecks

#### payslip_lines
```sql
CREATE TABLE pay.payslip_lines (
    id UUID PRIMARY KEY,
    payslip_id UUID REFERENCES pay.payslips(id),
    line_type VARCHAR (earning|deduction),
    line_name VARCHAR,
    amount DECIMAL(15,2),
    created_at TIMESTAMP
);
```
- **Purpose:** Earnings and deductions per payslip

#### earning_types
```sql
CREATE TABLE pay.earning_types (
    id UUID PRIMARY KEY,
    company_id UUID REFERENCES auth.companies(id),
    name VARCHAR,
    code VARCHAR,
    is_taxable BOOLEAN,
    is_active BOOLEAN,
    created_at TIMESTAMP
);
```
- **Purpose:** Salary component types (salary, bonus, OT, etc.)

#### deduction_types
```sql
CREATE TABLE pay.deduction_types (
    id UUID PRIMARY KEY,
    company_id UUID REFERENCES auth.companies(id),
    name VARCHAR,
    code VARCHAR,
    deduction_type VARCHAR (tax|insurance|other),
    is_active BOOLEAN,
    created_at TIMESTAMP
);
```
- **Purpose:** Tax and insurance deduction types

#### leave_types
```sql
CREATE TABLE pay.leave_types (
    id UUID PRIMARY KEY,
    company_id UUID REFERENCES auth.companies(id),
    name VARCHAR,
    code VARCHAR,
    max_days_per_year DECIMAL(5,2),
    is_paid BOOLEAN,
    created_at TIMESTAMP
);
```
- **Purpose:** PTO categories (vacation, sick, etc.)

#### leave_requests
```sql
CREATE TABLE pay.leave_requests (
    id UUID PRIMARY KEY,
    company_id UUID REFERENCES auth.companies(id),
    employee_id UUID REFERENCES pay.employees(id),
    leave_type_id UUID REFERENCES pay.leave_types(id),
    start_date DATE,
    end_date DATE,
    status VARCHAR (pending|approved|rejected|used),
    approved_by UUID REFERENCES auth.users(id),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```
- **Purpose:** Time off requests

---

## Other Schemas

### tax Schema (6 tables)
- `tax_jurisdictions` - Tax regions/countries
- `tax_rates` - Tax rate definitions
- `tax_groups` - Groups of tax rates
- `tax_registrations` - Company tax IDs per jurisdiction
- `tax_exemptions` - Exemption rules
- `company_tax_settings` - Company-specific tax config

See: `docs/contracts/tax-schema.md`

### rpt Schema (5 tables)
- `reports` - Predefined report templates
- `report_sections` - Report layout/structure
- `generated_reports` - Executed reports
- `report_exports` - Saved exports (PDF, Excel)
- `dashboards` - Dashboard templates

See: `docs/contracts/reporting-schema.md`

### crm Schema (4 tables)
- `contacts` - Contact records
- `interactions` - Customer interactions
- `leads` - Sales leads
- `lead_stages` - Pipeline stages

See: `docs/contracts/crm-schema.md`

### vms Schema (3 tables)
- `visitors` - Visitor records
- `visits` - Visit logs
- `travel_requests` - Travel approvals

See: `docs/contracts/vms-schema.md`

### sys Schema (3 tables)
- `system_settings` - Global system config
- `webhooks` - Integration webhooks
- `audit_logs` - System audit trail

See: `docs/contracts/system-schema.md`

### public Schema (1 table)
- `currencies` - ISO 4217 currency reference

---

## Common Queries

### Get all invoices for a company
```sql
SELECT i.* FROM acct.invoices i
WHERE i.company_id = 'company-uuid'
ORDER BY i.invoice_date DESC;
```

### Get AR aging (invoices not fully paid)
```sql
SELECT
    c.name,
    i.invoice_number,
    i.amount,
    COALESCE(SUM(pa.allocated_amount), 0) as paid,
    i.amount - COALESCE(SUM(pa.allocated_amount), 0) as outstanding,
    CURRENT_DATE - i.due_date as days_overdue
FROM acct.invoices i
LEFT JOIN acct.payment_allocations pa ON i.id = pa.invoice_id
LEFT JOIN acct.customers c ON i.customer_id = c.id
WHERE i.company_id = 'company-uuid'
AND i.status != 'voided'
GROUP BY i.id, c.id
HAVING i.amount - COALESCE(SUM(pa.allocated_amount), 0) > 0;
```

### Get account balance as of date
```sql
SELECT
    a.code,
    a.name,
    COALESCE(SUM(CASE WHEN je.debit_amount > 0
        THEN je.debit_amount ELSE 0 END), 0) -
    COALESCE(SUM(CASE WHEN je.credit_amount > 0
        THEN je.credit_amount ELSE 0 END), 0) as balance
FROM acct.accounts a
LEFT JOIN acct.journal_entries je ON a.id = je.account_id
LEFT JOIN acct.transactions t ON je.transaction_id = t.id
WHERE a.company_id = 'company-uuid'
AND t.transaction_date <= 'date'
AND t.status = 'posted'
GROUP BY a.id
ORDER BY a.code;
```

### Get stock level by warehouse
```sql
SELECT
    w.name as warehouse,
    i.sku,
    i.name,
    sl.quantity_on_hand,
    sl.quantity_available,
    sl.quantity_committed
FROM inv.stock_levels sl
JOIN inv.items i ON sl.item_id = i.id
JOIN inv.warehouses w ON sl.warehouse_id = w.id
WHERE i.company_id = 'company-uuid'
AND sl.quantity_on_hand > 0;
```

---

## References

- **Migrations:** `build/database/migrations/` (40+ files)
- **Models:** `build/app/Models/`
- **Contracts:** `docs/contracts/`
- **File:** `build/database/migrations/2025_11_29_000000_create_general_ledger_tables.php`
