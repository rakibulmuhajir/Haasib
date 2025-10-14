# Data Model - Company Registration Multi-Company Creation

**Feature**: Company Registration - Multi-Company Creation  
**Date**: 2025-10-07  
**Status**: Draft  

## Core Entities

### Company
```sql
CREATE TABLE auth.companies (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    country VARCHAR(2),
    country_id UUID REFERENCES auth.countries(id),
    base_currency VARCHAR(3) NOT NULL,
    currency_id UUID REFERENCES auth.currencies(id),
    exchange_rate_id INTEGER,
    language VARCHAR(10) DEFAULT 'en',
    locale VARCHAR(10) DEFAULT 'en_US',
    settings JSONB DEFAULT '{}',
    created_by_user_id UUID REFERENCES auth.users(id),
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

### Company User Relationships
```sql
CREATE TABLE auth.company_user (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id UUID NOT NULL REFERENCES auth.companies(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    role VARCHAR(20) NOT NULL CHECK (role IN ('owner', 'admin', 'accountant', 'viewer')),
    invited_by_user_id UUID REFERENCES auth.users(id),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(company_id, user_id)
);
```

### Fiscal Years
```sql
CREATE TABLE accounting.fiscal_years (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id UUID NOT NULL REFERENCES auth.companies(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_current BOOLEAN DEFAULT false,
    is_locked BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    CONSTRAINT valid_date_range CHECK (end_date > start_date),
    CONSTRAINT unique_current_year UNIQUE (company_id, is_current) DEFERRABLE INITIALLY DEFERRED
);
```

### Accounting Periods
```sql
CREATE TABLE accounting.accounting_periods (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    fiscal_year_id UUID NOT NULL REFERENCES accounting.fiscal_years(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    period_number INTEGER NOT NULL,
    is_current BOOLEAN DEFAULT false,
    is_locked BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    CONSTRAINT unique_period_number UNIQUE (fiscal_year_id, period_number),
    CONSTRAINT valid_period_dates CHECK (end_date > start_date)
);
```

### Chart of Accounts
```sql
CREATE TABLE accounting.chart_of_accounts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id UUID NOT NULL REFERENCES auth.companies(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    is_template BOOLEAN DEFAULT false,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

### Account Types
```sql
CREATE TABLE accounting.account_types (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL,
    normal_balance VARCHAR(10) NOT NULL CHECK (normal_balance IN ('debit', 'credit')),
    code_prefix VARCHAR(10) NOT NULL,
    order_index INTEGER NOT NULL,
    is_system BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT NOW()
);
```

### Accounts
```sql
CREATE TABLE accounting.accounts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    chart_of_accounts_id UUID NOT NULL REFERENCES accounting.chart_of_accounts(id) ON DELETE CASCADE,
    account_type_id UUID NOT NULL REFERENCES accounting.account_types(id),
    parent_account_id UUID REFERENCES accounting.accounts(id),
    code VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT true,
    is_contra BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    CONSTRAINT unique_account_code UNIQUE (chart_of_accounts_id, code),
    CONSTRAINT valid_parent_account CHECK (parent_account_id IS NULL OR parent_account_id != id)
);
```

### Account Groups
```sql
CREATE TABLE accounting.account_groups (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    chart_of_accounts_id UUID NOT NULL REFERENCES accounting.chart_of_accounts(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    parent_group_id UUID REFERENCES accounting.account_groups(id),
    account_type_id UUID REFERENCES accounting.account_types(id),
    order_index INTEGER NOT NULL DEFAULT 0,
    is_system BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

### Group Account Mappings
```sql
CREATE TABLE accounting.account_group_accounts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    account_group_id UUID NOT NULL REFERENCES accounting.account_groups(id) ON DELETE CASCADE,
    account_id UUID NOT NULL REFERENCES accounting.accounts(id) ON DELETE CASCADE,
    order_index INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    UNIQUE(account_group_id, account_id)
);
```

## Company Invitations
```sql
CREATE TABLE auth.company_invitations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id UUID NOT NULL REFERENCES auth.companies(id) ON DELETE CASCADE,
    email VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL CHECK (role IN ('owner', 'admin', 'accountant', 'viewer')),
    token VARCHAR(255) UNIQUE NOT NULL,
    invited_by_user_id UUID NOT NULL REFERENCES auth.users(id),
    accepted_by_user_id UUID REFERENCES auth.users(id),
    status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'accepted', 'rejected', 'expired')),
    expires_at TIMESTAMP NOT NULL,
    accepted_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

## Relationships and Indexes

### Key Relationships
- **Companies → Users**: Many-to-many via `auth.company_user`
- **Companies → Fiscal Years**: One-to-many
- **Fiscal Years → Accounting Periods**: One-to-many  
- **Companies → Chart of Accounts**: One-to-many
- **Chart of Accounts → Accounts**: One-to-many
- **Accounts → Account Groups**: Many-to-many via mapping table

### Important Indexes
```sql
-- Company lookups
CREATE INDEX idx_companies_slug ON auth.companies(slug);
CREATE INDEX idx_companies_active ON auth.companies(is_active);

-- User company relationships
CREATE INDEX idx_company_user_company ON auth.company_user(company_id);
CREATE INDEX idx_company_user_user ON auth.company_user(user_id);
CREATE INDEX idx_company_user_role ON auth.company_user(role);

-- Fiscal year queries
CREATE INDEX idx_fiscal_years_company ON accounting.fiscal_years(company_id);
CREATE INDEX idx_fiscal_years_current ON accounting.fiscal_years(company_id, is_current);

-- Account queries
CREATE INDEX idx_accounts_chart ON accounting.accounts(chart_of_accounts_id);
CREATE INDEX idx_accounts_type ON accounting.accounts(account_type_id);
CREATE INDEX idx_accounts_active ON accounting.accounts(is_active);

-- Invitation lookups
CREATE INDEX idx_invitations_token ON auth.company_invitations(token);
CREATE INDEX idx_invitations_email ON auth.company_invitations(email);
```

## RLS Policies

### Company Access
```sql
-- Users can only access companies they belong to
CREATE POLICY company_access_policy ON auth.companies
    USING (id IN (
        SELECT company_id FROM auth.company_user 
        WHERE user_id = auth.uid()
    ));
```

### Company User Management
```sql
-- Users can see company memberships for companies they belong to
CREATE POLICY company_user_access_policy ON auth.company_user
    USING (company_id IN (
        SELECT company_id FROM auth.company_user 
        WHERE user_id = auth.uid()
    ));
```

## Data Validation Rules

### Company Constraints
- Name must be unique per active company
- Slug must be globally unique
- Currency must be valid and active
- Creator must be valid user

### Fiscal Year Constraints  
- Date ranges must be logical
- Only one current fiscal year per company
- No overlapping fiscal years

### Account Constraints
- Account codes must be unique within chart of accounts
- Account types must be valid system types
- Parent-child relationships must not create cycles

### User Role Constraints
- Each user can have only one role per company
- Role must be valid enum value
- Inviter must have appropriate permissions

## Default Data Seeds

### System Account Types
```sql
INSERT INTO accounting.account_types (name, normal_balance, code_prefix, order_index) VALUES
('Assets', 'debit', '1', 1),
('Liabilities', 'credit', '2', 2),
('Equity', 'credit', '3', 3),
('Revenue', 'credit', '4', 4),
('Expenses', 'debit', '5', 5);
```

### Default Account Groups
```sql
-- Current Assets
INSERT INTO accounting.account_groups (name, account_type_id, order_index, is_system) VALUES
('Current Assets', (SELECT id FROM accounting.account_types WHERE name = 'Assets'), 1, true),
('Non-current Assets', (SELECT id FROM accounting.account_types WHERE name = 'Assets'), 2, true),
('Current Liabilities', (SELECT id FROM accounting.account_types WHERE name = 'Liabilities'), 1, true),
('Non-current Liabilities', (SELECT id FROM accounting.account_types WHERE name = 'Liabilities'), 2, true),
('Equity', (SELECT id FROM accounting.account_types WHERE name = 'Equity'), 1, true),
('Revenue', (SELECT id FROM accounting.account_types WHERE name = 'Revenue'), 1, true),
('Operating Expenses', (SELECT id FROM accounting.account_types WHERE name = 'Expenses'), 1, true);
```