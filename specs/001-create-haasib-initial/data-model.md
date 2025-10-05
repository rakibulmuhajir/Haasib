# Data Model: Create Haasib - Initial Platform Setup

## Core Entities *(Reused & Refined from Legacy Modules)*

### User (users table)
```php
User {
    id: UUID (primary)
    name: string
    email: string (unique)
    username: string
    password: string (hashed)
    role: enum ('system_owner', 'company_owner', 'accountant', 'member')
    is_active: boolean
    created_at: timestamp
    updated_at: timestamp

    Relationships:
    - hasMany(CompanyUser) → companies
    - hasMany(AuditEntry) → audit_entries (created_by)
}
```

### Company (companies table)
```php
Company {
    id: UUID (primary)
    name: string
    industry: enum ('hospitality', 'retail', 'professional_services')
    base_currency: string (3-letter ISO)
    fiscal_year_start: date
    is_active: boolean
    created_at: timestamp
    updated_at: timestamp

    Relationships:
    - hasMany(CompanyUser) → users
    - hasManyThrough(User) → users via CompanyUser
}
```

### CompanyUser (company_users table)
```php
CompanyUser {
    id: UUID (primary)
    company_id: UUID (foreign, RLS scope)
    user_id: UUID (foreign)
    role: enum ('owner', 'accountant', 'member')
    is_active: boolean
    created_at: timestamp
    updated_at: timestamp

    Relationships:
    - belongsTo(Company)
    - belongsTo(User)

    Indexes:
    - unique(company_id, user_id)
    - index(user_id)
}
```

### Module (modules table)
```php
Module {
    id: UUID (primary)
    name: string (unique) // 'Accounting'
    version: string
    is_enabled: boolean
    created_at: timestamp
    updated_at: timestamp

    Relationships:
    - hasMany(CompanyModule)
}
```

### CompanyModule (company_modules table)
```php
CompanyModule {
    id: UUID (primary)
    company_id: UUID (foreign, RLS scope)
    module_id: UUID (foreign)
    is_enabled: boolean
    enabled_at: timestamp
    enabled_by: UUID (foreign to users.id)
    created_at: timestamp
    updated_at: timestamp

    Relationships:
    - belongsTo(Company)
    - belongsTo(Module)
    - belongsTo(User, foreign_key: enabled_by)

    Indexes:
    - unique(company_id, module_id)
}
```

> **Note**: The module + company-module schema mirrors the legacy implementation. We will import the existing migration/seed file from `rebootstrap-primevue` and update only where the schema blueprint requires tweaks.

### AuditEntry (audit_entries table)
```php
AuditEntry {
    id: UUID (primary)
    company_id: UUID (foreign, RLS scope)
    entity_type: string // 'User', 'Company', 'Invoice', etc.
    entity_id: UUID
    action: enum ('create', 'update', 'delete', 'activate', 'deactivate')
    old_values: json (nullable)
    new_values: json (nullable)
    idempotency_key: UUID
    user_id: UUID (foreign)
    ip_address: string
    user_agent: string
    created_at: timestamp

    Relationships:
    - belongsTo(Company)
    - belongsTo(User)

    Indexes:
    - index(company_id, entity_type, created_at)
    - index(idempotency_key)
}
```

## Industry-Specific Entities (Demo Data)

### Customer (customers table) - Accounting module (Invoicing domain)
```php
Customer {
    id: UUID (primary)
    company_id: UUID (foreign, RLS scope)
    name: string
    email: string
    phone: string
    industry_type: string // based on company industry
    created_at: timestamp
    updated_at: timestamp
}
```

### Invoice (invoices table) - Accounting module (Invoicing domain)
```php
Invoice {
    id: UUID (primary)
    company_id: UUID (foreign, RLS scope)
    customer_id: UUID (foreign)
    invoice_number: string
    issue_date: date
    due_date: date
    total_amount: decimal(15,2)
    status: enum ('draft', 'sent', 'paid', 'overdue', 'void')
    line_items: json
    created_at: timestamp
    updated_at: timestamp
}
```

### Payment (payments table) - Accounting module (Payments domain)
```php
Payment {
    id: UUID (primary)
    company_id: UUID (foreign, RLS scope)
    invoice_id: UUID (foreign)
    amount: decimal(15,2)
    payment_date: date
    method: string
    status: enum ('pending', 'completed', 'failed')
    created_at: timestamp
    updated_at: timestamp
}
```

## Row Level Security (RLS) Policies

```sql
-- For all tenant-scoped tables
ALTER TABLE company_users ENABLE ROW LEVEL SECURITY;
CREATE POLICY company_users_tenant_policy ON company_users
    USING (company_id = current_setting('app.current_company', true)::uuid);

ALTER TABLE company_modules ENABLE ROW LEVEL SECURITY;
CREATE POLICY company_modules_tenant_policy ON company_modules
    USING (company_id = current_setting('app.current_company', true)::uuid);

ALTER TABLE audit_entries ENABLE ROW LEVEL SECURITY;
CREATE POLICY audit_entries_tenant_policy ON audit_entries
    USING (company_id = current_setting('app.current_company', true)::uuid);
```

## Demo Data Structure

### Hospitality Company Demo Data
- 15 customers (corporate, individual, travel agencies)
- 60 invoices (20 per month for 3 months)
  - Room bookings: 40% (nightly rates, seasonal pricing)
  - Restaurant sales: 30% (daily revenue, peak periods)
  - Event services: 20% (weddings, conferences)
  - Miscellaneous: 10% (laundry, parking, etc.)
- 45 payments (75% payment rate, various payment methods)

### Retail Company Demo Data
- 20 customers (B2B, B2C, wholesale)
- 90 invoices (30 per month for 3 months)
  - Product sales: 70% (inventory, seasonal trends)
  - Returns: 15% (refund processing)
  - Bulk orders: 10% (discount pricing)
  - Services: 5% (delivery, installation)
- 70 payments (78% payment rate)

### Professional Services Demo Data
- 12 clients (retainer, project-based, hourly)
- 36 invoices (12 per month for 3 months)
  - Hourly billing: 50% (consulting, support)
  - Project milestones: 30% (fixed price)
  - Retainers: 15% (monthly recurring)
  - Expenses: 5% (pass-through costs)
- 34 payments (94% payment rate)

## Indexes for Performance

```sql
-- Core tables
CREATE INDEX idx_company_users_company_role ON company_users(company_id, role);
CREATE INDEX idx_audit_entries_entity ON audit_entries(entity_type, entity_id, company_id);
CREATE INDEX idx_modules_name ON modules(name);

-- Demo data tables
CREATE INDEX idx_customers_company ON customers(company_id);
CREATE INDEX idx_invoices_company_date ON invoices(company_id, issue_date);
CREATE INDEX idx_invoices_status ON invoices(status) WHERE company_id IS NOT NULL;
CREATE INDEX idx_payments_company_date ON payments(company_id, payment_date);
```

## Constraints and Validations

```sql
-- Business constraints
ALTER TABLE companies ADD CONSTRAINT chk_industry
    CHECK (industry IN ('hospitality', 'retail', 'professional_services'));

ALTER TABLE invoices ADD CONSTRAINT chk_amount_positive
    CHECK (total_amount >= 0);

ALTER TABLE payments ADD CONSTRAINT chk_payment_amount
    CHECK (amount > 0);

-- Unique constraints
ALTER TABLE users ADD CONSTRAINT uniq_users_email UNIQUE (email);
ALTER TABLE users ADD CONSTRAINT uniq_users_username UNIQUE (username);
ALTER TABLE company_users ADD CONSTRAINT uniq_company_user UNIQUE (company_id, user_id);
ALTER TABLE company_modules ADD CONSTRAINT uniq_company_module UNIQUE (company_id, module_id);
```
