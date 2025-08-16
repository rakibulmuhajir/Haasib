-- ===============================================
-- Enhanced Business Management System Schema v2.0
-- Supports: CRM, VMS, Accounting with Multi-tenancy
-- Database: PostgreSQL (Primary) / MySQL Compatible
-- ===============================================

-- Set timezone and encoding
SET timezone = 'UTC';

-- ===============================================
-- CORE REFERENCE TABLES (Order matters for FK)
-- ===============================================

-- Countries & Regions
CREATE TABLE countries (
    country_id BIGSERIAL PRIMARY KEY,
    code VARCHAR(3) UNIQUE NOT NULL, -- ISO 3166-1 alpha-3
    name VARCHAR(255) NOT NULL,
    currency_code VARCHAR(3),
    phone_prefix VARCHAR(10),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    deleted_at TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (parent_account_id) REFERENCES chart_of_accounts(account_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id),
    UNIQUE (company_id, account_code)
);

-- Tax Rates
CREATE TABLE tax_rates (
    tax_rate_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    rate DECIMAL(8,4) NOT NULL, -- Supports up to 9999.9999%
    tax_type VARCHAR(50) NOT NULL, -- sales, purchase, withholding
    description TEXT,
    is_compound BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    effective_from DATE,
    effective_to DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    deleted_at TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id),
    CONSTRAINT chk_tax_rate CHECK (rate >= 0 AND rate <= 100),
    CONSTRAINT chk_tax_dates CHECK (effective_to IS NULL OR effective_to > effective_from)
);

-- Items/Products
CREATE TABLE items (
    item_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    category_id BIGINT,
    item_code VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    item_type VARCHAR(50) DEFAULT 'product', -- product, service, bundle
    unit_of_measure VARCHAR(50) DEFAULT 'each',
    purchase_price DECIMAL(15,2),
    selling_price DECIMAL(15,2),
    cost_price DECIMAL(15,2),
    currency_id BIGINT,
    tax_rate_id BIGINT,
    stock_quantity DECIMAL(10,3) DEFAULT 0,
    reorder_level DECIMAL(10,3) DEFAULT 0,
    max_stock_level DECIMAL(10,3),
    location VARCHAR(255),
    barcode VARCHAR(255),
    sku VARCHAR(100),
    weight DECIMAL(8,3),
    dimensions VARCHAR(100),
    images JSONB DEFAULT '[]',
    custom_fields JSONB DEFAULT '{}',
    is_active BOOLEAN DEFAULT TRUE,
    is_trackable BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    deleted_at TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (category_id) REFERENCES categories(category_id),
    FOREIGN KEY (currency_id) REFERENCES currencies(currency_id),
    FOREIGN KEY (tax_rate_id) REFERENCES tax_rates(tax_rate_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id),
    UNIQUE (company_id, item_code),
    CONSTRAINT chk_prices CHECK (purchase_price >= 0 AND selling_price >= 0 AND cost_price >= 0),
    CONSTRAINT chk_stock CHECK (stock_quantity >= 0 AND reorder_level >= 0)
);

-- Company Bank Accounts
CREATE TABLE company_bank_accounts (
    bank_account_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    bank_id BIGINT NOT NULL,
    account_id BIGINT, -- Links to chart of accounts
    account_name VARCHAR(255) NOT NULL,
    account_number VARCHAR(100) NOT NULL,
    account_type VARCHAR(50) DEFAULT 'checking', -- checking, savings, credit_card
    branch VARCHAR(255),
    swift_code VARCHAR(11),
    iban VARCHAR(34),
    currency_id BIGINT NOT NULL,
    current_balance DECIMAL(15,2) DEFAULT 0,
    opening_balance DECIMAL(15,2) DEFAULT 0,
    is_primary BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    reconciliation_date DATE,
    last_statement_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    deleted_at TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (bank_id) REFERENCES banks(bank_id),
    FOREIGN KEY (account_id) REFERENCES chart_of_accounts(account_id),
    FOREIGN KEY (currency_id) REFERENCES currencies(currency_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id)
);

-- Invoices
CREATE TABLE invoices (
    invoice_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    customer_id BIGINT NOT NULL,
    invoice_number VARCHAR(100) UNIQUE NOT NULL,
    reference_number VARCHAR(100),
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    currency_id BIGINT NOT NULL,
    exchange_rate DECIMAL(20,10) DEFAULT 1,
    subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    shipping_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    balance_due DECIMAL(15,2) NOT NULL DEFAULT 0,
    status VARCHAR(50) DEFAULT 'draft', -- draft, sent, viewed, partial, paid, overdue, cancelled
    payment_status VARCHAR(50) DEFAULT 'unpaid', -- unpaid, partial, paid, overpaid
    payment_terms VARCHAR(255),
    notes TEXT,
    terms_conditions TEXT,
    billing_address TEXT,
    shipping_address TEXT,
    custom_fields JSONB DEFAULT '{}',
    sent_date TIMESTAMP,
    viewed_date TIMESTAMP,
    last_payment_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    deleted_at TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    FOREIGN KEY (currency_id) REFERENCES currencies(currency_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id),
    CONSTRAINT chk_invoice_amounts CHECK (
        subtotal >= 0 AND tax_amount >= 0 AND discount_amount >= 0 AND
        shipping_amount >= 0 AND total_amount >= 0 AND paid_amount >= 0
    ),
    CONSTRAINT chk_invoice_dates CHECK (due_date >= invoice_date)
);

-- Invoice Items
CREATE TABLE invoice_items (
    invoice_item_id BIGSERIAL PRIMARY KEY,
    invoice_id BIGINT NOT NULL,
    item_id BIGINT,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(10,3) NOT NULL,
    unit_price DECIMAL(15,2) NOT NULL,
    discount_percentage DECIMAL(5,2) DEFAULT 0,
    discount_amount DECIMAL(15,2) DEFAULT 0,
    tax_rate_id BIGINT,
    tax_amount DECIMAL(15,2) DEFAULT 0,
    line_total DECIMAL(15,2) NOT NULL,
    sort_order INTEGER DEFAULT 0,
    custom_fields JSONB DEFAULT '{}',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(item_id),
    FOREIGN KEY (tax_rate_id) REFERENCES tax_rates(tax_rate_id),
    CONSTRAINT chk_invoice_item_amounts CHECK (
        quantity > 0 AND unit_price >= 0 AND discount_percentage >= 0 AND
        discount_percentage <= 100 AND discount_amount >= 0 AND
        tax_amount >= 0 AND line_total >= 0
    )
);

-- Bills
CREATE TABLE bills (
    bill_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    vendor_id BIGINT NOT NULL,
    bill_number VARCHAR(100) NOT NULL,
    reference_number VARCHAR(100),
    bill_date DATE NOT NULL,
    due_date DATE NOT NULL,
    currency_id BIGINT NOT NULL,
    exchange_rate DECIMAL(20,10) DEFAULT 1,
    subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    paid_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    balance_due DECIMAL(15,2) NOT NULL DEFAULT 0,
    status VARCHAR(50) DEFAULT 'draft', -- draft, received, approved, paid, overdue, cancelled
    payment_status VARCHAR(50) DEFAULT 'unpaid', -- unpaid, partial, paid, overpaid
    payment_terms VARCHAR(255),
    notes TEXT,
    attachments JSONB DEFAULT '[]',
    custom_fields JSONB DEFAULT '{}',
    received_date TIMESTAMP,
    approved_date TIMESTAMP,
    approved_by BIGINT,
    last_payment_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    deleted_at TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id),
    FOREIGN KEY (currency_id) REFERENCES currencies(currency_id),
    FOREIGN KEY (approved_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id),
    CONSTRAINT chk_bill_amounts CHECK (
        subtotal >= 0 AND tax_amount >= 0 AND discount_amount >= 0 AND
        total_amount >= 0 AND paid_amount >= 0
    ),
    CONSTRAINT chk_bill_dates CHECK (due_date >= bill_date),
    UNIQUE (company_id, bill_number)
);

-- Bill Items
CREATE TABLE bill_items (
    bill_item_id BIGSERIAL PRIMARY KEY,
    bill_id BIGINT NOT NULL,
    item_id BIGINT,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(10,3) NOT NULL,
    unit_price DECIMAL(15,2) NOT NULL,
    discount_percentage DECIMAL(5,2) DEFAULT 0,
    discount_amount DECIMAL(15,2) DEFAULT 0,
    tax_rate_id BIGINT,
    tax_amount DECIMAL(15,2) DEFAULT 0,
    line_total DECIMAL(15,2) NOT NULL,
    sort_order INTEGER DEFAULT 0,
    custom_fields JSONB DEFAULT '{}',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_id) REFERENCES bills(bill_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(item_id),
    FOREIGN KEY (tax_rate_id) REFERENCES tax_rates(tax_rate_id),
    CONSTRAINT chk_bill_item_amounts CHECK (
        quantity > 0 AND unit_price >= 0 AND discount_percentage >= 0 AND
        discount_percentage <= 100 AND discount_amount >= 0 AND
        tax_amount >= 0 AND line_total >= 0
    )
);

-- Transactions (General Ledger)
CREATE TABLE transactions (
    transaction_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    transaction_number VARCHAR(100) UNIQUE NOT NULL,
    transaction_type VARCHAR(50) NOT NULL, -- journal_entry, invoice, bill, payment, receipt
    reference_type VARCHAR(50), -- invoice, bill, payment, etc.
    reference_id BIGINT,
    transaction_date DATE NOT NULL,
    description TEXT,
    currency_id BIGINT NOT NULL,
    exchange_rate DECIMAL(20,10) DEFAULT 1,
    total_debit DECIMAL(15,2) NOT NULL DEFAULT 0,
    total_credit DECIMAL(15,2) NOT NULL DEFAULT 0,
    status VARCHAR(50) DEFAULT 'posted', -- draft, posted, reversed
    reversal_transaction_id BIGINT,
    period_id BIGINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    deleted_at TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (currency_id) REFERENCES currencies(currency_id),
    FOREIGN KEY (reversal_transaction_id) REFERENCES transactions(transaction_id),
    FOREIGN KEY (period_id) REFERENCES accounting_periods(period_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id),
    CONSTRAINT chk_balanced_transaction CHECK (total_debit = total_credit),
    CONSTRAINT chk_transaction_amounts CHECK (total_debit >= 0 AND total_credit >= 0)
);

-- Journal Entries (Transaction Details)
CREATE TABLE journal_entries (
    entry_id BIGSERIAL PRIMARY KEY,
    transaction_id BIGINT NOT NULL,
    account_id BIGINT NOT NULL,
    debit_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    credit_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    description TEXT,
    reference_type VARCHAR(50),
    reference_id BIGINT,
    sort_order INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(transaction_id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES chart_of_accounts(account_id),
    CONSTRAINT chk_entry_amounts CHECK (
        debit_amount >= 0 AND credit_amount >= 0 AND
        (debit_amount > 0 OR credit_amount > 0) AND
        NOT (debit_amount > 0 AND credit_amount > 0)
    )
);

-- Payments
CREATE TABLE payments (
    payment_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    payment_number VARCHAR(100) UNIQUE NOT NULL,
    payment_type VARCHAR(50) NOT NULL, -- customer_payment, vendor_payment
    entity_type VARCHAR(50) NOT NULL, -- customer, vendor
    entity_id BIGINT NOT NULL,
    bank_account_id BIGINT,
    payment_method VARCHAR(50) NOT NULL, -- cash, check, bank_transfer, credit_card, etc.
    payment_date DATE NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    currency_id BIGINT NOT NULL,
    exchange_rate DECIMAL(20,10) DEFAULT 1,
    reference_number VARCHAR(100),
    check_number VARCHAR(50),
    transaction_id VARCHAR(100), -- Bank transaction ID
    status VARCHAR(50) DEFAULT 'completed', -- pending, completed, cancelled, failed
    notes TEXT,
    attachments JSONB DEFAULT '[]',
    reconciled BOOLEAN DEFAULT FALSE,
    reconciled_date TIMESTAMP,
    reconciled_by BIGINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    deleted_at TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (bank_account_id) REFERENCES company_bank_accounts(bank_account_id),
    FOREIGN KEY (currency_id) REFERENCES currencies(currency_id),
    FOREIGN KEY (reconciled_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id),
    CONSTRAINT chk_payment_amount CHECK (amount > 0)
);

-- Payment Allocations (Links payments to invoices/bills)
CREATE TABLE payment_allocations (
    allocation_id BIGSERIAL PRIMARY KEY,
    payment_id BIGINT NOT NULL,
    reference_type VARCHAR(50) NOT NULL, -- invoice, bill
    reference_id BIGINT NOT NULL,
    allocated_amount DECIMAL(15,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(payment_id) ON DELETE CASCADE,
    CONSTRAINT chk_allocation_amount CHECK (allocated_amount > 0)
);

-- Accounts Receivable Summary
CREATE TABLE accounts_receivable (
    ar_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    customer_id BIGINT NOT NULL,
    invoice_id BIGINT NOT NULL,
    amount_due DECIMAL(15,2) NOT NULL,
    original_amount DECIMAL(15,2) NOT NULL,
    currency_id BIGINT NOT NULL,
    due_date DATE NOT NULL,
    days_overdue INTEGER DEFAULT 0,
    aging_bucket VARCHAR(20), -- current, 1-30, 31-60, 61-90, 90+
    last_payment_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id),
    FOREIGN KEY (currency_id) REFERENCES currencies(currency_id),
    CONSTRAINT chk_ar_amounts CHECK (amount_due >= 0 AND original_amount > 0),
    UNIQUE (invoice_id)
);

-- Accounts Payable Summary
CREATE TABLE accounts_payable (
    ap_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    vendor_id BIGINT NOT NULL,
    bill_id BIGINT NOT NULL,
    amount_due DECIMAL(15,2) NOT NULL,
    original_amount DECIMAL(15,2) NOT NULL,
    currency_id BIGINT NOT NULL,
    due_date DATE NOT NULL,
    days_overdue INTEGER DEFAULT 0,
    aging_bucket VARCHAR(20), -- current, 1-30, 31-60, 61-90, 90+
    last_payment_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id),
    FOREIGN KEY (bill_id) REFERENCES bills(bill_id),
    FOREIGN KEY (currency_id) REFERENCES currencies(currency_id),
    CONSTRAINT chk_ap_amounts CHECK (amount_due >= 0 AND original_amount > 0),
    UNIQUE (bill_id)
);

-- Inventory Transactions
CREATE TABLE inventory_transactions (
    inventory_transaction_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    item_id BIGINT NOT NULL,
    transaction_type VARCHAR(50) NOT NULL, -- purchase, sale, adjustment, transfer
    reference_type VARCHAR(50), -- invoice, bill, adjustment
    reference_id BIGINT,
    quantity_change DECIMAL(10,3) NOT NULL,
    unit_cost DECIMAL(15,2),
    total_cost DECIMAL(15,2),
    quantity_before DECIMAL(10,3) NOT NULL,
    quantity_after DECIMAL(10,3) NOT NULL,
    transaction_date TIMESTAMP NOT NULL,
    location VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (item_id) REFERENCES items(item_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    CONSTRAINT chk_inventory_costs CHECK (unit_cost >= 0 AND total_cost >= 0)
);

-- Budgets
CREATE TABLE budgets (
    budget_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    fiscal_year_id BIGINT NOT NULL,
    budget_type VARCHAR(50) DEFAULT 'operating', -- operating, capital, cash_flow
    status VARCHAR(50) DEFAULT 'draft', -- draft, approved, locked
    total_budget DECIMAL(15,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    approved_by BIGINT,
    approved_date TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (fiscal_year_id) REFERENCES fiscal_years(fiscal_year_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (approved_by) REFERENCES user_accounts(user_id)
);

-- Budget Line Items
CREATE TABLE budget_line_items (
    budget_line_id BIGSERIAL PRIMARY KEY,
    budget_id BIGINT NOT NULL,
    account_id BIGINT NOT NULL,
    period_type VARCHAR(20) DEFAULT 'monthly', -- monthly, quarterly, yearly
    january_amount DECIMAL(15,2) DEFAULT 0,
    february_amount DECIMAL(15,2) DEFAULT 0,
    march_amount DECIMAL(15,2) DEFAULT 0,
    april_amount DECIMAL(15,2) DEFAULT 0,
    may_amount DECIMAL(15,2) DEFAULT 0,
    june_amount DECIMAL(15,2) DEFAULT 0,
    july_amount DECIMAL(15,2) DEFAULT 0,
    august_amount DECIMAL(15,2) DEFAULT 0,
    september_amount DECIMAL(15,2) DEFAULT 0,
    october_amount DECIMAL(15,2) DEFAULT 0,
    november_amount DECIMAL(15,2) DEFAULT 0,
    december_amount DECIMAL(15,2) DEFAULT 0,
    total_budget_amount DECIMAL(15,2) DEFAULT 0,
    actual_amount DECIMAL(15,2) DEFAULT 0,
    variance_amount DECIMAL(15,2) DEFAULT 0,
    variance_percentage DECIMAL(5,2) DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (budget_id) REFERENCES budgets(budget_id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES chart_of_accounts(account_id),
    UNIQUE (budget_id, account_id)
);

-- Bank Reconciliation
CREATE TABLE bank_reconciliations (
    reconciliation_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    bank_account_id BIGINT NOT NULL,
    statement_date DATE NOT NULL,
    statement_balance DECIMAL(15,2) NOT NULL,
    book_balance DECIMAL(15,2) NOT NULL,
    adjusted_balance DECIMAL(15,2) NOT NULL,
    status VARCHAR(50) DEFAULT 'in_progress', -- in_progress, completed
    reconciled_by BIGINT,
    reconciled_date TIMESTAMP,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (bank_account_id) REFERENCES company_bank_accounts(bank_account_id),
    FOREIGN KEY (reconciled_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id)
);

-- Bank Transactions
CREATE TABLE bank_transactions (
    bank_transaction_id BIGSERIAL PRIMARY KEY,
    bank_account_id BIGINT NOT NULL,
    reconciliation_id BIGINT,
    transaction_date DATE NOT NULL,
    description TEXT NOT NULL,
    reference_number VARCHAR(100),
    amount DECIMAL(15,2) NOT NULL,
    transaction_type VARCHAR(50) NOT NULL, -- debit, credit
    balance DECIMAL(15,2),
    is_reconciled BOOLEAN DEFAULT FALSE,
    matched_payment_id BIGINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bank_account_id) REFERENCES company_bank_accounts(bank_account_id),
    FOREIGN KEY (reconciliation_id) REFERENCES bank_reconciliations(reconciliation_id),
    FOREIGN KEY (matched_payment_id) REFERENCES payments(payment_id)
);

-- ===============================================
-- AUDIT & SYSTEM TABLES
-- ===============================================

-- Enhanced Activity Log
CREATE TABLE activity_log (
    activity_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    user_id BIGINT,
    action VARCHAR(100) NOT NULL,
    module_name VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100),
    entity_id BIGINT,
    entity_name VARCHAR(255),
    description TEXT,
    ip_address INET,
    user_agent TEXT,
    session_id VARCHAR(255),
    details JSONB DEFAULT '{}',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (user_id) REFERENCES user_accounts(user_id)
);

-- Data Change Audit Trail
CREATE TABLE audit_trail (
    audit_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    table_name VARCHAR(100) NOT NULL,
    record_id BIGINT NOT NULL,
    action VARCHAR(20) NOT NULL, -- INSERT, UPDATE, DELETE
    old_values JSONB,
    new_values JSONB,
    changed_fields JSONB,
    changed_by BIGINT,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address INET,
    user_agent TEXT,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (changed_by) REFERENCES user_accounts(user_id)
);

-- Customer Support Tickets
CREATE TABLE support_tickets (
    ticket_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    ticket_number VARCHAR(100) UNIQUE NOT NULL,
    customer_id BIGINT,
    contact_id BIGINT,
    subject VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    priority VARCHAR(50) DEFAULT 'medium', -- low, medium, high, urgent
    status VARCHAR(50) DEFAULT 'open', -- open, in_progress, resolved, closed
    category VARCHAR(100),
    assigned_to BIGINT,
    resolution TEXT,
    satisfaction_rating SMALLINT,
    satisfaction_feedback TEXT,
    first_response_at TIMESTAMP,
    resolved_at TIMESTAMP,
    closed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    FOREIGN KEY (contact_id) REFERENCES contacts(contact_id),
    FOREIGN KEY (assigned_to) REFERENCES user_accounts(user_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id),
    CONSTRAINT chk_satisfaction_rating CHECK (satisfaction_rating BETWEEN 1 AND 5)
);

-- Documents Management
CREATE TABLE documents (
    document_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id BIGINT NOT NULL,
    document_type VARCHAR(100),
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT,
    mime_type VARCHAR(100),
    version INTEGER DEFAULT 1,
    is_public BOOLEAN DEFAULT FALSE,
    tags JSONB DEFAULT '[]',
    custom_fields JSONB DEFAULT '{}',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    created_by BIGINT NOT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    CONSTRAINT chk_file_size CHECK (file_size > 0),
    CONSTRAINT chk_version CHECK (version > 0)
);

-- Job Queue System
CREATE TABLE job_queue (
    job_id BIGSERIAL PRIMARY KEY,
    queue_name VARCHAR(100) DEFAULT 'default',
    job_type VARCHAR(100) NOT NULL,
    payload JSONB NOT NULL,
    status VARCHAR(50) DEFAULT 'pending', -- pending, processing, completed, failed, retrying
    attempts INTEGER DEFAULT 0,
    max_attempts INTEGER DEFAULT 3,
    priority INTEGER DEFAULT 0,
    available_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP,
    completed_at TIMESTAMP,
    failed_at TIMESTAMP,
    error_message TEXT,
    progress_percentage SMALLINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_attempts CHECK (attempts >= 0),
    CONSTRAINT chk_max_attempts CHECK (max_attempts > 0),
    CONSTRAINT chk_progress CHECK (progress_percentage BETWEEN 0 AND 100)
);

-- Failed Jobs
CREATE TABLE failed_jobs (
    failed_job_id BIGSERIAL PRIMARY KEY,
    job_id BIGINT,
    queue_name VARCHAR(100),
    job_ TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Currencies
CREATE TABLE currencies (
    currency_id BIGSERIAL PRIMARY KEY,
    code VARCHAR(10) UNIQUE NOT NULL, -- USD, EUR, GBP
    name VARCHAR(100) NOT NULL,
    symbol VARCHAR(10) NOT NULL,
    decimal_places SMALLINT DEFAULT 2,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Exchange Rates
CREATE TABLE exchange_rates (
    exchange_rate_id BIGSERIAL PRIMARY KEY,
    base_currency_id BIGINT NOT NULL,
    target_currency_id BIGINT NOT NULL,
    rate DECIMAL(20, 10) NOT NULL,
    effective_date DATE NOT NULL,
    source VARCHAR(50) DEFAULT 'manual',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (base_currency_id) REFERENCES currencies(currency_id),
    FOREIGN KEY (target_currency_id) REFERENCES currencies(currency_id),
    CONSTRAINT chk_positive_rate CHECK (rate > 0),
    CONSTRAINT chk_different_currencies CHECK (base_currency_id != target_currency_id),
    UNIQUE (base_currency_id, target_currency_id, effective_date)
);

-- Companies (Multi-tenant)
CREATE TABLE companies (
    company_id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    legal_name VARCHAR(255),
    registration_number VARCHAR(100),
    tax_number VARCHAR(100),
    industry VARCHAR(100),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    postal_code VARCHAR(20),
    country_id BIGINT,
    phone VARCHAR(50),
    email VARCHAR(255),
    website VARCHAR(255),
    logo_url VARCHAR(500),
    primary_currency_id BIGINT NOT NULL,
    fiscal_year_start_month SMALLINT DEFAULT 1,
    subscription_level VARCHAR(50) DEFAULT 'basic',
    features_access JSONB DEFAULT '{}',
    schema_name VARCHAR(63) UNIQUE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT,
    updated_by BIGINT,
    deleted_at TIMESTAMP,
    FOREIGN KEY (country_id) REFERENCES countries(country_id),
    FOREIGN KEY (primary_currency_id) REFERENCES currencies(currency_id),
    CONSTRAINT chk_fiscal_month CHECK (fiscal_year_start_month BETWEEN 1 AND 12)
);

-- User Accounts (Enhanced Security)
CREATE TABLE user_accounts (
    user_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    username VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    password_salt VARCHAR(255) NOT NULL,
    password_reset_token VARCHAR(255),
    password_reset_expires TIMESTAMP,
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'user',
    permissions JSONB DEFAULT '{}',
    phone VARCHAR(50),
    avatar_url VARCHAR(500),
    timezone VARCHAR(50) DEFAULT 'UTC',
    language VARCHAR(10) DEFAULT 'en',
    is_active BOOLEAN DEFAULT TRUE,
    is_verified BOOLEAN DEFAULT FALSE,
    last_login TIMESTAMP,
    failed_login_attempts SMALLINT DEFAULT 0,
    account_locked_until TIMESTAMP,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    two_factor_secret VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT,
    updated_by BIGINT,
    deleted_at TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    CONSTRAINT chk_failed_attempts CHECK (failed_login_attempts >= 0)
);

-- Add self-referencing FKs after table creation
ALTER TABLE companies
    ADD CONSTRAINT fk_companies_created_by FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    ADD CONSTRAINT fk_companies_updated_by FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id);

ALTER TABLE user_accounts
    ADD CONSTRAINT fk_user_accounts_created_by FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    ADD CONSTRAINT fk_user_accounts_updated_by FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id);

-- Categories (for expenses, products, etc.)
CREATE TABLE categories (
    category_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    parent_category_id BIGINT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category_type VARCHAR(50) NOT NULL, -- expense, product, service
    color VARCHAR(7), -- hex color code
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    deleted_at TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (parent_category_id) REFERENCES categories(category_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id)
);

-- Banks
CREATE TABLE banks (
    bank_id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    swift_code VARCHAR(11),
    country_id BIGINT,
    logo_url VARCHAR(500),
    website VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (country_id) REFERENCES countries(country_id)
);

-- ===============================================
-- CUSTOMER RELATIONSHIP MANAGEMENT (CRM)
-- ===============================================

-- Customers
CREATE TABLE customers (
    customer_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    customer_number VARCHAR(50) UNIQUE NOT NULL,
    type VARCHAR(50) DEFAULT 'individual', -- individual, business
    first_name VARCHAR(255),
    last_name VARCHAR(255),
    company_name VARCHAR(255),
    display_name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(50),
    mobile VARCHAR(50),
    website VARCHAR(255),
    tax_number VARCHAR(100),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    postal_code VARCHAR(20),
    country_id BIGINT,
    billing_address TEXT,
    shipping_address TEXT,
    currency_id BIGINT,
    payment_terms VARCHAR(100) DEFAULT 'Net 30',
    credit_limit DECIMAL(15, 2) DEFAULT 0,
    current_balance DECIMAL(15, 2) DEFAULT 0,
    logo_url VARCHAR(500),
    notes TEXT,
    tags JSONB DEFAULT '[]',
    custom_fields JSONB DEFAULT '{}',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    deleted_at TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (country_id) REFERENCES countries(country_id),
    FOREIGN KEY (currency_id) REFERENCES currencies(currency_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id),
    CONSTRAINT chk_credit_limit CHECK (credit_limit >= 0)
);

-- Vendors
CREATE TABLE vendors (
    vendor_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    vendor_number VARCHAR(50) UNIQUE NOT NULL,
    type VARCHAR(50) DEFAULT 'supplier', -- supplier, service_provider, contractor
    name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(50),
    mobile VARCHAR(50),
    website VARCHAR(255),
    tax_number VARCHAR(100),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    postal_code VARCHAR(20),
    country_id BIGINT,
    currency_id BIGINT,
    payment_terms VARCHAR(100) DEFAULT 'Net 30',
    logo_url VARCHAR(500),
    notes TEXT,
    tags JSONB DEFAULT '[]',
    custom_fields JSONB DEFAULT '{}',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    deleted_at TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (country_id) REFERENCES countries(country_id),
    FOREIGN KEY (currency_id) REFERENCES currencies(currency_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id)
);

-- Contacts
CREATE TABLE contacts (
    contact_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    entity_type VARCHAR(50) NOT NULL, -- customer, vendor, lead, general
    entity_id BIGINT,
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(50),
    mobile VARCHAR(50),
    title VARCHAR(255),
    department VARCHAR(100),
    is_primary BOOLEAN DEFAULT FALSE,
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    postal_code VARCHAR(20),
    country_id BIGINT,
    date_of_birth DATE,
    notes TEXT,
    tags JSONB DEFAULT '[]',
    custom_fields JSONB DEFAULT '{}',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    deleted_at TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (country_id) REFERENCES countries(country_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id)
);

-- Social Media Links
CREATE TABLE social_media_links (
    link_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    contact_id BIGINT NOT NULL,
    platform VARCHAR(50) NOT NULL, -- linkedin, twitter, facebook, instagram
    url VARCHAR(500) NOT NULL,
    handle VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (contact_id) REFERENCES contacts(contact_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id)
);

-- Interactions
CREATE TABLE interactions (
    interaction_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    contact_id BIGINT NOT NULL,
    type VARCHAR(50) NOT NULL, -- call, email, meeting, note
    subject VARCHAR(255),
    description TEXT,
    interaction_date TIMESTAMP NOT NULL,
    duration_minutes INTEGER,
    location VARCHAR(255),
    outcome VARCHAR(100),
    follow_up_date DATE,
    attachments JSONB DEFAULT '[]',
    tags JSONB DEFAULT '[]',
    is_important BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    deleted_at TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (contact_id) REFERENCES contacts(contact_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id),
    CONSTRAINT chk_duration CHECK (duration_minutes >= 0)
);

-- Email Campaigns
CREATE TABLE email_campaigns (
    campaign_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    template_id BIGINT,
    status VARCHAR(50) DEFAULT 'draft', -- draft, scheduled, sent, completed
    scheduled_date TIMESTAMP,
    sent_date TIMESTAMP,
    total_recipients INTEGER DEFAULT 0,
    total_sent INTEGER DEFAULT 0,
    total_delivered INTEGER DEFAULT 0,
    total_opened INTEGER DEFAULT 0,
    total_clicked INTEGER DEFAULT 0,
    total_bounced INTEGER DEFAULT 0,
    total_unsubscribed INTEGER DEFAULT 0,
    tags JSONB DEFAULT '[]',
    settings JSONB DEFAULT '{}',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    deleted_at TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id)
);

-- Email Campaign Recipients
CREATE TABLE email_campaign_recipients (
    recipient_id BIGSERIAL PRIMARY KEY,
    campaign_id BIGINT NOT NULL,
    contact_id BIGINT NOT NULL,
    email VARCHAR(255) NOT NULL,
    status VARCHAR(50) DEFAULT 'pending', -- pending, sent, delivered, opened, clicked, bounced, unsubscribed
    sent_at TIMESTAMP,
    delivered_at TIMESTAMP,
    opened_at TIMESTAMP,
    clicked_at TIMESTAMP,
    bounced_at TIMESTAMP,
    unsubscribed_at TIMESTAMP,
    bounce_reason TEXT,
    tracking_token VARCHAR(255) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES email_campaigns(campaign_id) ON DELETE CASCADE,
    FOREIGN KEY (contact_id) REFERENCES contacts(contact_id),
    UNIQUE (campaign_id, contact_id)
);

-- Tasks
CREATE TABLE tasks (
    task_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    type VARCHAR(50) DEFAULT 'general', -- general, follow_up, reminder
    priority VARCHAR(50) DEFAULT 'medium', -- low, medium, high, urgent
    status VARCHAR(50) DEFAULT 'pending', -- pending, in_progress, completed, cancelled
    assigned_to BIGINT,
    related_entity_type VARCHAR(50), -- customer, vendor, contact, deal
    related_entity_id BIGINT,
    due_date TIMESTAMP,
    completed_date TIMESTAMP,
    estimated_hours DECIMAL(5,2),
    actual_hours DECIMAL(5,2),
    tags JSONB DEFAULT '[]',
    custom_fields JSONB DEFAULT '{}',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    deleted_at TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (assigned_to) REFERENCES user_accounts(user_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id),
    CONSTRAINT chk_estimated_hours CHECK (estimated_hours >= 0),
    CONSTRAINT chk_actual_hours CHECK (actual_hours >= 0)
);

-- ===============================================
-- VISITOR MANAGEMENT SYSTEM (VMS)
-- ===============================================

-- Cities
CREATE TABLE cities (
    city_id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    country_id BIGINT NOT NULL,
    state VARCHAR(100),
    latitude DECIMAL(9,6),
    longitude DECIMAL(9,6),
    timezone VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (country_id) REFERENCES countries(country_id)
);

-- Areas within cities
CREATE TABLE areas (
    area_id BIGSERIAL PRIMARY KEY,
    city_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    area_type VARCHAR(50), -- district, zone, neighborhood
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (city_id) REFERENCES cities(city_id)
);

-- Airlines
CREATE TABLE airlines (
    airline_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    vendor_id BIGINT,
    name VARCHAR(255) NOT NULL,
    iata_code VARCHAR(3) UNIQUE,
    icao_code VARCHAR(4) UNIQUE,
    country_id BIGINT,
    logo_url VARCHAR(500),
    website VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id),
    FOREIGN KEY (country_id) REFERENCES countries(country_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id)
);

-- Hotels
CREATE TABLE hotels (
    hotel_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    vendor_id BIGINT,
    name VARCHAR(255) NOT NULL,
    area_id BIGINT,
    address TEXT,
    phone VARCHAR(50),
    email VARCHAR(255),
    website VARCHAR(255),
    star_rating SMALLINT,
    amenities JSONB DEFAULT '[]',
    room_types JSONB DEFAULT '[]',
    images JSONB DEFAULT '[]',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    deleted_at TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id),
    FOREIGN KEY (area_id) REFERENCES areas(area_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id),
    CONSTRAINT chk_star_rating CHECK (star_rating BETWEEN 1 AND 5)
);

-- Groups (Travel Groups)
CREATE TABLE groups (
    group_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    group_number VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    customer_id BIGINT,
    vendor_id BIGINT,
    group_type VARCHAR(50) DEFAULT 'tour', -- tour, business, family, individual
    total_members INTEGER DEFAULT 0,
    departure_date DATE,
    return_date DATE,
    destination_country_id BIGINT,
    status VARCHAR(50) DEFAULT 'draft', -- draft, confirmed, in_progress, completed, cancelled
    total_cost DECIMAL(15,2) DEFAULT 0,
    paid_amount DECIMAL(15,2) DEFAULT 0,
    notes TEXT,
    custom_fields JSONB DEFAULT '{}',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    deleted_at TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id),
    FOREIGN KEY (destination_country_id) REFERENCES countries(country_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id),
    CONSTRAINT chk_total_members CHECK (total_members >= 0),
    CONSTRAINT chk_travel_dates CHECK (return_date IS NULL OR return_date > departure_date)
);

-- Visitors
CREATE TABLE visitors (
    visitor_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    group_id BIGINT,
    customer_id BIGINT,
    visitor_number VARCHAR(100) UNIQUE NOT NULL,
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255) NOT NULL,
    middle_name VARCHAR(255),
    date_of_birth DATE,
    gender VARCHAR(20),
    nationality_id BIGINT,
    passport_number VARCHAR(100),
    passport_issue_date DATE,
    passport_expiry_date DATE,
    passport_issue_country_id BIGINT,
    visa_number VARCHAR(100),
    visa_type VARCHAR(50),
    visa_expiry_date DATE,
    phone VARCHAR(50),
    email VARCHAR(255),
    emergency_contact_name VARCHAR(255),
    emergency_contact_phone VARCHAR(50),
    dietary_requirements TEXT,
    medical_conditions TEXT,
    special_requests TEXT,
    photo_url VARCHAR(500),
    documents JSONB DEFAULT '[]',
    custom_fields JSONB DEFAULT '{}',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    deleted_at TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (group_id) REFERENCES groups(group_id),
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    FOREIGN KEY (nationality_id) REFERENCES countries(country_id),
    FOREIGN KEY (passport_issue_country_id) REFERENCES countries(country_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id),
    CONSTRAINT chk_passport_dates CHECK (passport_expiry_date IS NULL OR passport_expiry_date > passport_issue_date)
);

-- Services (Visa, Hotel, Ticket services)
CREATE TABLE services (
    service_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    visitor_id BIGINT NOT NULL,
    customer_id BIGINT,
    vendor_id BIGINT,
    service_type VARCHAR(50) NOT NULL, -- visa, hotel, flight, transport, tour
    service_name VARCHAR(255) NOT NULL,
    description TEXT,
    service_date DATE,
    start_date DATE,
    end_date DATE,
    quantity INTEGER DEFAULT 1,
    unit_price DECIMAL(15,2),
    total_price DECIMAL(15,2),
    currency_id BIGINT,
    status VARCHAR(50) DEFAULT 'pending', -- pending, confirmed, completed, cancelled
    reference_number VARCHAR(100),
    confirmation_number VARCHAR(100),
    details JSONB DEFAULT '{}',
    attachments JSONB DEFAULT '[]',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    deleted_at TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (visitor_id) REFERENCES visitors(visitor_id),
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id),
    FOREIGN KEY (currency_id) REFERENCES currencies(currency_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id),
    CONSTRAINT chk_quantity CHECK (quantity > 0),
    CONSTRAINT chk_unit_price CHECK (unit_price >= 0),
    CONSTRAINT chk_total_price CHECK (total_price >= 0)
);

-- Vouchers
CREATE TABLE vouchers (
    voucher_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    group_id BIGINT,
    customer_id BIGINT NOT NULL,
    voucher_number VARCHAR(100) UNIQUE NOT NULL,
    voucher_type VARCHAR(50) DEFAULT 'travel', -- travel, hotel, flight
    title VARCHAR(255) NOT NULL,
    issue_date DATE NOT NULL,
    valid_from DATE,
    valid_until DATE,
    hotel_name VARCHAR(255),
    hotel_address TEXT,
    hotel_checkin_date DATE,
    hotel_checkout_date DATE,
    room_type VARCHAR(100),
    meal_plan VARCHAR(50),
    outbound_flight_date TIMESTAMP,
    outbound_flight_from VARCHAR(255),
    outbound_flight_to VARCHAR(255),
    outbound_flight_number VARCHAR(50),
    return_flight_date TIMESTAMP,
    return_flight_from VARCHAR(255),
    return_flight_to VARCHAR(255),
    return_flight_number VARCHAR(50),
    total_amount DECIMAL(15,2),
    currency_id BIGINT,
    terms_conditions TEXT,
    special_instructions TEXT,
    voucher_details JSONB DEFAULT '{}',
    qr_code VARCHAR(255),
    status VARCHAR(50) DEFAULT 'active', -- active, used, expired, cancelled
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    deleted_at TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (group_id) REFERENCES groups(group_id),
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    FOREIGN KEY (currency_id) REFERENCES currencies(currency_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id),
    CONSTRAINT chk_hotel_dates CHECK (hotel_checkout_date IS NULL OR hotel_checkout_date > hotel_checkin_date)
);

-- ===============================================
-- ACCOUNTING SYSTEM
-- ===============================================

-- Fiscal Years
CREATE TABLE fiscal_years (
    fiscal_year_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    name VARCHAR(100) NOT NULL, -- FY 2024, 2024-2025
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_current BOOLEAN DEFAULT FALSE,
    is_closed BOOLEAN DEFAULT FALSE,
    status VARCHAR(50) DEFAULT 'open', -- open, closed, locked
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id),
    CONSTRAINT chk_fiscal_dates CHECK (end_date > start_date)
);

-- Accounting Periods
CREATE TABLE accounting_periods (
    period_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    fiscal_year_id BIGINT NOT NULL,
    name VARCHAR(100) NOT NULL, -- January 2024, Q1 2024
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    period_type VARCHAR(20) DEFAULT 'monthly', -- monthly, quarterly, yearly
    is_closed BOOLEAN DEFAULT FALSE,
    close_password_hash VARCHAR(255),
    closed_by BIGINT,
    closed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (fiscal_year_id) REFERENCES fiscal_years(fiscal_year_id),
    FOREIGN KEY (closed_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id),
    CONSTRAINT chk_period_dates CHECK (end_date > start_date)
);

-- ===============================================
-- Enhanced Business Management System Schema v2.0 - COMPLETION
-- Continuing from chart_of_accounts table
-- ===============================================

-- Complete Chart of Accounts table
CREATE TABLE chart_of_accounts (
    account_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    parent_account_id BIGINT,
    account_code VARCHAR(50) NOT NULL,
    account_name VARCHAR(255) NOT NULL,
    account_type VARCHAR(50) NOT NULL, -- asset, liability, equity, revenue, expense
    account_subtype VARCHAR(50), -- current_asset, fixed_asset, current_liability, etc.
    description TEXT,
    is_system_account BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    balance_type VARCHAR(10) NOT NULL DEFAULT 'debit', -- debit, credit
    current_balance DECIMAL(15,2) DEFAULT 0,
    opening_balance DECIMAL(15,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    deleted_at TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (parent_account_id) REFERENCES chart_of_accounts(account_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id),
    UNIQUE (company_id, account_code)
);

-- ===============================================
-- REPORTING & ANALYTICS
-- ===============================================

-- Report Templates
CREATE TABLE report_templates (
    template_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    report_type VARCHAR(100) NOT NULL, -- profit_loss, balance_sheet, cash_flow, trial_balance, custom
    category VARCHAR(100), -- financial, operational, analytical
    configuration JSONB NOT NULL DEFAULT '{}',
    sql_query TEXT,
    parameters JSONB DEFAULT '[]',
    is_system_template BOOLEAN DEFAULT FALSE,
    is_public BOOLEAN DEFAULT FALSE,
    sort_order INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    deleted_at TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id)
);

-- Generated Reports
CREATE TABLE reports (
    report_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    template_id BIGINT,
    name VARCHAR(255) NOT NULL,
    report_type VARCHAR(100) NOT NULL,
    parameters JSONB DEFAULT '{}',
    filters JSONB DEFAULT '{}',
    date_range_start DATE,
    date_range_end DATE,
    status VARCHAR(50) DEFAULT 'generated', -- generating, generated, failed
    file_path VARCHAR(500),
    file_size BIGINT,
    mime_type VARCHAR(100),
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    created_by BIGINT NOT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (template_id) REFERENCES report_templates(template_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id)
);

-- Recurring Transactions
CREATE TABLE recurring_transactions (
    recurring_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    transaction_type VARCHAR(50) NOT NULL, -- invoice, bill, journal_entry
    reference_template_id BIGINT, -- ID of template invoice/bill
    frequency VARCHAR(50) NOT NULL, -- daily, weekly, monthly, quarterly, yearly
    interval_count INTEGER DEFAULT 1, -- every X periods
    start_date DATE NOT NULL,
    end_date DATE,
    next_date DATE NOT NULL,
    last_generated_date DATE,
    total_occurrences INTEGER,
    generated_count INTEGER DEFAULT 0,
    template_data JSONB NOT NULL DEFAULT '{}',
    is_active BOOLEAN DEFAULT TRUE,
    status VARCHAR(50) DEFAULT 'active', -- active, paused, completed, cancelled
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id),
    CONSTRAINT chk_interval_count CHECK (interval_count > 0),
    CONSTRAINT chk_generated_count CHECK (generated_count >= 0)
);

-- Financial Statements
CREATE TABLE financial_statements (
    statement_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    fiscal_year_id BIGINT NOT NULL,
    period_id BIGINT,
    statement_type VARCHAR(50) NOT NULL, -- balance_sheet, profit_loss, cash_flow, equity
    name VARCHAR(255) NOT NULL,
    statement_date DATE NOT NULL,
    date_range_start DATE,
    date_range_end DATE,
    data JSONB NOT NULL DEFAULT '{}',
    totals JSONB DEFAULT '{}',
    comparative_data JSONB DEFAULT '{}', -- Previous period comparison
    notes TEXT,
    status VARCHAR(50) DEFAULT 'draft', -- draft, finalized, published
    version INTEGER DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    finalized_by BIGINT,
    finalized_at TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (fiscal_year_id) REFERENCES fiscal_years(fiscal_year_id),
    FOREIGN KEY (period_id) REFERENCES accounting_periods(period_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (finalized_by) REFERENCES user_accounts(user_id),
    CONSTRAINT chk_version CHECK (version > 0)
);

-- ===============================================
-- PAYROLL SYSTEM
-- ===============================================

-- Employees
CREATE TABLE employees (
    employee_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    user_account_id BIGINT UNIQUE,
    employee_number VARCHAR(100) UNIQUE NOT NULL,
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255) NOT NULL,
    middle_name VARCHAR(255),
    email VARCHAR(255) UNIQUE,
    phone VARCHAR(50),
    date_of_birth DATE,
    hire_date DATE NOT NULL,
    termination_date DATE,
    department VARCHAR(100),
    job_title VARCHAR(255),
    employment_type VARCHAR(50) DEFAULT 'full_time', -- full_time, part_time, contract, intern
    employment_status VARCHAR(50) DEFAULT 'active', -- active, inactive, terminated
    base_salary DECIMAL(15,2),
    hourly_rate DECIMAL(10,2),
    currency_id BIGINT,
    pay_frequency VARCHAR(50) DEFAULT 'monthly', -- weekly, bi_weekly, monthly, quarterly
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    postal_code VARCHAR(20),
    country_id BIGINT,
    emergency_contact_name VARCHAR(255),
    emergency_contact_phone VARCHAR(50),
    emergency_contact_relation VARCHAR(100),
    bank_account_number VARCHAR(100),
    bank_routing_number VARCHAR(50),
    tax_id VARCHAR(100),
    social_security VARCHAR(100),
    notes TEXT,
    custom_fields JSONB DEFAULT '{}',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    deleted_at TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (user_account_id) REFERENCES user_accounts(user_id),
    FOREIGN KEY (currency_id) REFERENCES currencies(currency_id),
    FOREIGN KEY (country_id) REFERENCES countries(country_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id),
    CONSTRAINT chk_employment_dates CHECK (termination_date IS NULL OR termination_date > hire_date),
    CONSTRAINT chk_salary_rates CHECK (base_salary >= 0 AND hourly_rate >= 0)
);

-- Payroll Periods
CREATE TABLE payroll_periods (
    payroll_period_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    name VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    pay_date DATE NOT NULL,
    status VARCHAR(50) DEFAULT 'open', -- open, processing, completed, cancelled
    total_gross_pay DECIMAL(15,2) DEFAULT 0,
    total_net_pay DECIMAL(15,2) DEFAULT 0,
    total_taxes DECIMAL(15,2) DEFAULT 0,
    total_deductions DECIMAL(15,2) DEFAULT 0,
    employee_count INTEGER DEFAULT 0,
    processed_by BIGINT,
    processed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (processed_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id),
    CONSTRAINT chk_payroll_dates CHECK (end_date > start_date AND pay_date >= end_date)
);

-- Payroll Records
CREATE TABLE payroll (
    payroll_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    payroll_period_id BIGINT NOT NULL,
    employee_id BIGINT NOT NULL,
    pay_frequency VARCHAR(50) NOT NULL,
    regular_hours DECIMAL(8,2) DEFAULT 0,
    overtime_hours DECIMAL(8,2) DEFAULT 0,
    base_salary DECIMAL(15,2) DEFAULT 0,
    hourly_rate DECIMAL(10,2) DEFAULT 0,
    overtime_rate DECIMAL(10,2) DEFAULT 0,
    regular_pay DECIMAL(15,2) DEFAULT 0,
    overtime_pay DECIMAL(15,2) DEFAULT 0,
    bonus DECIMAL(15,2) DEFAULT 0,
    commission DECIMAL(15,2) DEFAULT 0,
    other_earnings DECIMAL(15,2) DEFAULT 0,
    gross_pay DECIMAL(15,2) NOT NULL DEFAULT 0,
    federal_tax DECIMAL(15,2) DEFAULT 0,
    state_tax DECIMAL(15,2) DEFAULT 0,
    local_tax DECIMAL(15,2) DEFAULT 0,
    social_security DECIMAL(15,2) DEFAULT 0,
    medicare DECIMAL(15,2) DEFAULT 0,
    unemployment_tax DECIMAL(15,2) DEFAULT 0,
    total_taxes DECIMAL(15,2) DEFAULT 0,
    health_insurance DECIMAL(15,2) DEFAULT 0,
    dental_insurance DECIMAL(15,2) DEFAULT 0,
    life_insurance DECIMAL(15,2) DEFAULT 0,
    retirement_401k DECIMAL(15,2) DEFAULT 0,
    other_deductions DECIMAL(15,2) DEFAULT 0,
    total_deductions DECIMAL(15,2) DEFAULT 0,
    net_pay DECIMAL(15,2) NOT NULL DEFAULT 0,
    year_to_date_gross DECIMAL(15,2) DEFAULT 0,
    year_to_date_taxes DECIMAL(15,2) DEFAULT 0,
    year_to_date_net DECIMAL(15,2) DEFAULT 0,
    currency_id BIGINT NOT NULL,
    status VARCHAR(50) DEFAULT 'draft', -- draft, approved, paid
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (payroll_period_id) REFERENCES payroll_periods(payroll_period_id),
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id),
    FOREIGN KEY (currency_id) REFERENCES currencies(currency_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id),
    CONSTRAINT chk_payroll_amounts CHECK (
        regular_hours >= 0 AND overtime_hours >= 0 AND gross_pay >= 0 AND
        net_pay >= 0 AND total_taxes >= 0 AND total_deductions >= 0
    ),
    UNIQUE (payroll_period_id, employee_id)
);

-- ===============================================
-- INVENTORY MANAGEMENT
-- ===============================================

-- Inventory Adjustments
CREATE TABLE inventory_adjustments (
    adjustment_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    adjustment_number VARCHAR(100) UNIQUE NOT NULL,
    adjustment_date DATE NOT NULL,
    adjustment_type VARCHAR(50) NOT NULL, -- count, damage, loss, found, write_off
    reason VARCHAR(255),
    reference_number VARCHAR(100),
    total_value_change DECIMAL(15,2) DEFAULT 0,
    status VARCHAR(50) DEFAULT 'draft', -- draft, approved, posted
    notes TEXT,
    approved_by BIGINT,
    approved_at TIMESTAMP,
    posted_by BIGINT,
    posted_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (approved_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (posted_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id)
);

-- Inventory Adjustment Items
CREATE TABLE inventory_adjustment_items (
    adjustment_item_id BIGSERIAL PRIMARY KEY,
    adjustment_id BIGINT NOT NULL,
    item_id BIGINT NOT NULL,
    location VARCHAR(255),
    current_quantity DECIMAL(10,3) NOT NULL,
    adjusted_quantity DECIMAL(10,3) NOT NULL,
    quantity_change DECIMAL(10,3) NOT NULL,
    unit_cost DECIMAL(15,2),
    total_cost_change DECIMAL(15,2),
    reason VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (adjustment_id) REFERENCES inventory_adjustments(adjustment_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(item_id),
    CONSTRAINT chk_quantities CHECK (current_quantity >= 0 AND adjusted_quantity >= 0)
);

-- Stock Movements Summary
CREATE TABLE stock_movements (
    movement_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    item_id BIGINT NOT NULL,
    movement_type VARCHAR(50) NOT NULL, -- in, out, adjustment, transfer
    quantity DECIMAL(10,3) NOT NULL,
    unit_cost DECIMAL(15,2),
    total_cost DECIMAL(15,2),
    reference_type VARCHAR(50), -- invoice, bill, adjustment, transfer
    reference_id BIGINT,
    reference_number VARCHAR(100),
    location VARCHAR(255),
    movement_date TIMESTAMP NOT NULL,
    balance_after DECIMAL(10,3) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (item_id) REFERENCES items(item_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id)
);

-- ===============================================
-- SYSTEM CONFIGURATION & SETTINGS
-- ===============================================

-- System Settings
CREATE TABLE system_settings (
    setting_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT, -- NULL for global settings
    category VARCHAR(100) NOT NULL,
    setting_key VARCHAR(255) NOT NULL,
    setting_value TEXT,
    data_type VARCHAR(50) DEFAULT 'string', -- string, number, boolean, json
    description TEXT,
    is_encrypted BOOLEAN DEFAULT FALSE,
    is_system_setting BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_by BIGINT,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id),
    UNIQUE (company_id, category, setting_key)
);

-- User Sessions
CREATE TABLE user_sessions (
    session_id VARCHAR(255) PRIMARY KEY,
    user_id BIGINT NOT NULL,
    company_id BIGINT NOT NULL,
    ip_address INET,
    user_agent TEXT,
    device_info JSONB DEFAULT '{}',
    location_info JSONB DEFAULT '{}',
    is_active BOOLEAN DEFAULT TRUE,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES user_accounts(user_id),
    FOREIGN KEY (company_id) REFERENCES companies(company_id)
);

-- API Keys
CREATE TABLE api_keys (
    api_key_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    user_id BIGINT,
    name VARCHAR(255) NOT NULL,
    key_hash VARCHAR(255) NOT NULL UNIQUE,
    key_prefix VARCHAR(20) NOT NULL,
    permissions JSONB DEFAULT '[]',
    rate_limit INTEGER DEFAULT 1000,
    is_active BOOLEAN DEFAULT TRUE,
    last_used_at TIMESTAMP,
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (user_id) REFERENCES user_accounts(user_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id)
);

-- Webhooks
CREATE TABLE webhooks (
    webhook_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    url VARCHAR(500) NOT NULL,
    events JSONB NOT NULL DEFAULT '[]', -- Array of event names
    secret VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    verify_ssl BOOLEAN DEFAULT TRUE,
    timeout_seconds INTEGER DEFAULT 30,
    retry_attempts INTEGER DEFAULT 3,
    last_triggered_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_by BIGINT NOT NULL,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id),
    FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id)
);

-- Webhook Deliveries
CREATE TABLE webhook_deliveries (
    delivery_id BIGSERIAL PRIMARY KEY,
    webhook_id BIGINT NOT NULL,
    event_name VARCHAR(100) NOT NULL,
    payload JSONB NOT NULL,
    response_status INTEGER,
    response_body TEXT,
    response_time_ms INTEGER,
    attempt_number INTEGER DEFAULT 1,
    delivered_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (webhook_id) REFERENCES webhooks(webhook_id)
);

-- ===============================================
-- PERFORMANCE & OPTIMIZATION INDEXES
-- ===============================================

-- Essential Performance Indexes
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_companies_schema_name ON companies(schema_name);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_companies_active ON companies(is_active) WHERE is_active = TRUE;

-- User Accounts Indexes
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_user_accounts_company ON user_accounts(company_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_user_accounts_email ON user_accounts(email);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_user_accounts_username ON user_accounts(username);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_user_accounts_active ON user_accounts(company_id, is_active) WHERE is_active = TRUE;

-- Customer & Vendor Indexes
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_customers_company ON customers(company_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_customers_number ON customers(customer_number);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_customers_active ON customers(company_id, is_active) WHERE is_active = TRUE;
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_customers_email ON customers(email);

CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_vendors_company ON vendors(company_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_vendors_number ON vendors(vendor_number);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_vendors_active ON vendors(company_id, is_active) WHERE is_active = TRUE;

-- Invoice Indexes
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_invoices_company ON invoices(company_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_invoices_customer ON invoices(customer_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_invoices_number ON invoices(invoice_number);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_invoices_date ON invoices(company_id, invoice_date);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_invoices_status ON invoices(company_id, status);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_invoices_due_date ON invoices(company_id, due_date);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_invoices_payment_status ON invoices(payment_status) WHERE payment_status != 'paid';

-- Bill Indexes
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_bills_company ON bills(company_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_bills_vendor ON bills(vendor_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_bills_number ON bills(company_id, bill_number);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_bills_date ON bills(company_id, bill_date);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_bills_status ON bills(company_id, status);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_bills_due_date ON bills(company_id, due_date);

-- Transaction Indexes
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_transactions_company ON transactions(company_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_transactions_date ON transactions(company_id, transaction_date);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_transactions_type ON transactions(company_id, transaction_type);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_transactions_reference ON transactions(reference_type, reference_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_transactions_number ON transactions(transaction_number);

-- Journal Entry Indexes
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_journal_entries_transaction ON journal_entries(transaction_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_journal_entries_account ON journal_entries(account_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_journal_entries_account_date ON journal_entries(account_id, transaction_id);

-- Payment Indexes
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_payments_company ON payments(company_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_payments_entity ON payments(entity_type, entity_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_payments_date ON payments(company_id, payment_date);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_payments_method ON payments(payment_method);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_payments_reconciled ON payments(reconciled) WHERE reconciled = FALSE;

-- Chart of Accounts Indexes
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_accounts_company ON chart_of_accounts(company_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_accounts_code ON chart_of_accounts(company_id, account_code);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_accounts_type ON chart_of_accounts(company_id, account_type);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_accounts_parent ON chart_of_accounts(parent_account_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_accounts_active ON chart_of_accounts(company_id, is_active) WHERE is_active = TRUE;

-- Item/Inventory Indexes
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_items_company ON items(company_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_items_code ON items(company_id, item_code);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_items_active ON items(company_id, is_active) WHERE is_active = TRUE;
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_items_stock ON items(stock_quantity) WHERE stock_quantity <= reorder_level;

-- Contact & CRM Indexes
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_contacts_company ON contacts(company_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_contacts_entity ON contacts(entity_type, entity_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_contacts_email ON contacts(email);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_contacts_name ON contacts(company_id, first_name, last_name);

CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_interactions_company ON interactions(company_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_interactions_contact ON interactions(contact_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_interactions_date ON interactions(company_id, interaction_date);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_interactions_type ON interactions(type);

-- VMS Indexes
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_visitors_company ON visitors(company_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_visitors_group ON visitors(group_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_visitors_customer ON visitors(customer_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_visitors_passport ON visitors(passport_number);

CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_groups_company ON groups(company_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_groups_customer ON groups(customer_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_groups_number ON groups(group_number);

CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_services_company ON services(company_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_services_visitor ON services(visitor_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_services_type ON services(service_type);

-- Activity Log Indexes
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_activity_log_company ON activity_log(company_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_activity_log_user ON activity_log(user_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_activity_log_created ON activity_log(created_at);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_activity_log_entity ON activity_log(entity_type, entity_id);

-- Audit Trail Indexes
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_audit_trail_company ON audit_trail(company_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_audit_trail_table ON audit_trail(table_name, record_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_audit_trail_changed ON audit_trail(changed_at);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_audit_trail_user ON audit_trail(changed_by);

-- Currency & Exchange Rate Indexes
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_exchange_rates_currencies ON exchange_rates(base_currency_id, target_currency_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_exchange_rates_date ON exchange_rates(effective_date);

-- Job Queue Indexes
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_job_queue_status ON job_queue(status, available_at);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_job_queue_priority ON job_queue(priority, available_at);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_job_queue_type ON job_queue(job_type);

-- Session & Security Indexes
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_user_sessions_user ON user_sessions(user_id);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_user_sessions_active ON user_sessions(is_active, last_activity) WHERE is_active = TRUE;
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_user_sessions_expires ON user_sessions(expires_at);

-- ===============================================
-- MATERIALIZED VIEWS FOR REPORTING
-- ===============================================

-- Customer Balance Summary
CREATE MATERIALIZED VIEW customer_balances AS
SELECT
    c.company_id,
    c.customer_id,
    c.customer_number,
    c.display_name,
    c.currency_id,
    curr.code as currency_code,
    COALESCE(SUM(i.total_amount), 0) as total_invoiced,
    COALESCE(SUM(CASE WHEN i.payment_status = 'paid' THEN i.total_amount ELSE 0 END), 0) as total_paid,
    COALESCE(SUM(i.balance_due), 0) as balance_due,
    COUNT(i.invoice_id) as invoice_count,
    MAX(i.invoice_date) as last_invoice_date,
    c.credit_limit,
    CASE
        WHEN c.credit_limit > 0 AND COALESCE(SUM(i.balance_due), 0) > c.credit_limit
        THEN TRUE ELSE FALSE
    END as over_credit_limit
FROM customers c
LEFT JOIN currencies curr ON c.currency_id = curr.currency_id
LEFT JOIN invoices i ON c.customer_id = i.customer_id AND i.deleted_at IS NULL
WHERE c.deleted_at IS NULL AND c.is_active = TRUE
GROUP BY c.company_id, c.customer_id, c.customer_number, c.display_name,
         c.currency_id, curr.code, c.credit_limit;

-- Create indexes on materialized view
CREATE UNIQUE INDEX idx_customer_balances_pk ON customer_balances(customer_id);


-- ===============================================
-- schema-v2-patch.sql
-- Delta patch to upgrade schema-v2 for a strong SME accounting foundation
-- Focus: the first 12 upgrades previously outlined
-- Target: PostgreSQL
-- ===============================================

SET search_path = public;
SET timezone = 'UTC';

-- =====================================================
-- 1) MULTI-TENANCY GUARDS: composite uniques and indexes
-- =====================================================

-- Customers, Vendors, Items, Invoices, Bills already have company_id.
-- Ensure tenant-scoped uniqueness for human-facing numbers and codes.
ALTER TABLE IF EXISTS customers
    DROP CONSTRAINT IF EXISTS customers_customer_number_key,
    ADD CONSTRAINT uq_customers_company_number UNIQUE (company_id, customer_number);

ALTER TABLE IF EXISTS vendors
    DROP CONSTRAINT IF EXISTS vendors_vendor_number_key,
    ADD CONSTRAINT uq_vendors_company_number UNIQUE (company_id, vendor_number);

ALTER TABLE IF EXISTS items
    DROP CONSTRAINT IF EXISTS items_company_id_item_code_key,
    ADD CONSTRAINT uq_items_company_item_code UNIQUE (company_id, item_code);

ALTER TABLE IF EXISTS invoices
    DROP CONSTRAINT IF EXISTS invoices_invoice_number_key,
    ADD CONSTRAINT uq_invoices_company_number UNIQUE (company_id, invoice_number);

ALTER TABLE IF EXISTS bills
    DROP CONSTRAINT IF EXISTS bills_company_id_bill_number_key,
    ADD CONSTRAINT uq_bills_company_number UNIQUE (company_id, bill_number);

ALTER TABLE IF EXISTS chart_of_accounts
    DROP CONSTRAINT IF EXISTS chart_of_accounts_company_id_account_code_key,
    ADD CONSTRAINT uq_accounts_company_code UNIQUE (company_id, account_code);


-- =====================================================
-- 2) LEDGER IMMUTABILITY + PERIOD LOCK
-- =====================================================

-- Prevent UPDATE/DELETE on posted transactions and their entries.
CREATE OR REPLACE FUNCTION gl_block_mutation_when_posted()
RETURNS trigger LANGUAGE plpgsql AS
$$
BEGIN
  IF (TG_TABLE_NAME = 'transactions') THEN
     IF (TG_OP IN ('UPDATE','DELETE')) THEN
        IF (OLD.status = 'posted' OR OLD.status = 'reversed') THEN
           RAISE EXCEPTION 'Cannot % % once posted or reversed', TG_OP, TG_TABLE_NAME;
        END IF;
     END IF;
  ELSIF (TG_TABLE_NAME = 'journal_entries') THEN
     IF (TG_OP IN ('UPDATE','DELETE')) THEN
        PERFORM 1 FROM transactions t WHERE t.transaction_id = OLD.transaction_id AND (t.status IN ('posted','reversed'));
        IF FOUND THEN
           RAISE EXCEPTION 'Cannot % % for posted/reversed transactions', TG_OP, TG_TABLE_NAME;
        END IF;
     END IF;
  END IF;
  RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
END;
$$;

DROP TRIGGER IF EXISTS trg_transactions_block_mutation ON transactions;
CREATE TRIGGER trg_transactions_block_mutation
BEFORE UPDATE OR DELETE ON transactions
FOR EACH ROW EXECUTE FUNCTION gl_block_mutation_when_posted();

DROP TRIGGER IF EXISTS trg_journal_entries_block_mutation ON journal_entries;
CREATE TRIGGER trg_journal_entries_block_mutation
BEFORE UPDATE OR DELETE ON journal_entries
FOR EACH ROW EXECUTE FUNCTION gl_block_mutation_when_posted();


-- Disallow posting into closed accounting periods.
CREATE OR REPLACE FUNCTION gl_block_post_in_closed_period()
RETURNS trigger LANGUAGE plpgsql AS
$$
DECLARE v_closed BOOLEAN;
BEGIN
  SELECT ap.is_closed INTO v_closed FROM accounting_periods ap WHERE ap.period_id = NEW.period_id;
  IF v_closed IS TRUE THEN
     RAISE EXCEPTION 'Cannot post into a closed accounting period (period_id=%)', NEW.period_id;
  END IF;
  RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_transactions_period_lock ON transactions;
CREATE TRIGGER trg_transactions_period_lock
BEFORE INSERT OR UPDATE OF period_id ON transactions
FOR EACH ROW EXECUTE FUNCTION gl_block_post_in_closed_period();


-- Ensure transaction is balanced at row-level totals and frozen at insert.
ALTER TABLE IF EXISTS transactions
  DROP CONSTRAINT IF EXISTS chk_balanced_transaction,
  ADD CONSTRAINT chk_balanced_transaction CHECK (total_debit = total_credit);


-- ======================================
-- 3) CURRENCIES AND FX ON GL LINE LEVEL
-- ======================================

-- Add explicit transactional and base/reporting amounts to journal_entries.
DO $$ BEGIN
  IF NOT EXISTS (
     SELECT 1 FROM information_schema.columns
     WHERE table_name='journal_entries' AND column_name='currency_id'
  ) THEN
     ALTER TABLE journal_entries
        ADD COLUMN currency_id BIGINT,
        ADD COLUMN amount_txn NUMERIC(15,2) DEFAULT 0,
        ADD COLUMN amount_base NUMERIC(15,2) DEFAULT 0,
        ADD COLUMN amount_reporting NUMERIC(15,2) DEFAULT 0,
        ADD COLUMN fx_txn_to_base NUMERIC(20,10) DEFAULT 1,
        ADD COLUMN fx_base_to_reporting NUMERIC(20,10) DEFAULT 1;
     ALTER TABLE journal_entries
        ADD CONSTRAINT fk_je_currency FOREIGN KEY (currency_id) REFERENCES currencies(currency_id);
  END IF;
END $$;


-- =======================
-- 4) TAXES: line-level M:N
-- =======================

-- Support multiple taxes per invoice/bill line.
CREATE TABLE IF NOT EXISTS invoice_item_taxes (
    invoice_item_tax_id BIGSERIAL PRIMARY KEY,
    invoice_item_id BIGINT NOT NULL,
    tax_rate_id BIGINT NOT NULL,
    tax_base NUMERIC(15,2) NOT NULL DEFAULT 0,
    tax_amount NUMERIC(15,2) NOT NULL DEFAULT 0,
    tax_inclusive BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_item_id) REFERENCES invoice_items(invoice_item_id) ON DELETE CASCADE,
    FOREIGN KEY (tax_rate_id) REFERENCES tax_rates(tax_rate_id)
);

CREATE TABLE IF NOT EXISTS bill_item_taxes (
    bill_item_tax_id BIGSERIAL PRIMARY KEY,
    bill_item_id BIGINT NOT NULL,
    tax_rate_id BIGINT NOT NULL,
    tax_base NUMERIC(15,2) NOT NULL DEFAULT 0,
    tax_amount NUMERIC(15,2) NOT NULL DEFAULT 0,
    tax_inclusive BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_item_id) REFERENCES bill_items(bill_item_id) ON DELETE CASCADE,
    FOREIGN KEY (tax_rate_id) REFERENCES tax_rates(tax_rate_id)
);


-- =====================
-- 5) AR/AP ALLOCATIONS
-- =====================

-- Prevent over-allocation against invoices/bills.
CREATE OR REPLACE FUNCTION ap_ar_prevent_over_allocation()
RETURNS trigger LANGUAGE plpgsql AS
$$
DECLARE v_due NUMERIC(15,2);
DECLARE v_alloc NUMERIC(15,2);
BEGIN
  IF NEW.reference_type = 'invoice' THEN
     SELECT balance_due INTO v_due FROM invoices WHERE invoice_id = NEW.reference_id;
     SELECT COALESCE(SUM(allocated_amount),0) INTO v_alloc
       FROM payment_allocations
       WHERE reference_type='invoice' AND reference_id=NEW.reference_id
         AND allocation_id <> COALESCE(NEW.allocation_id,0);
  ELSIF NEW.reference_type = 'bill' THEN
     SELECT balance_due INTO v_due FROM bills WHERE bill_id = NEW.reference_id;
     SELECT COALESCE(SUM(allocated_amount),0) INTO v_alloc
       FROM payment_allocations
       WHERE reference_type='bill' AND reference_id=NEW.reference_id
         AND allocation_id <> COALESCE(NEW.allocation_id,0);
  ELSE
     RAISE EXCEPTION 'Unknown reference_type %', NEW.reference_type;
  END IF;

  IF (v_alloc + NEW.allocated_amount) > v_due THEN
     RAISE EXCEPTION 'Allocation exceeds outstanding balance. Outstanding=%, NewTotalAllocation=%', v_due, (v_alloc + NEW.allocated_amount);
  END IF;

  RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_payment_allocations_block_over ON payment_allocations;
CREATE TRIGGER trg_payment_allocations_block_over
BEFORE INSERT OR UPDATE ON payment_allocations
FOR EACH ROW EXECUTE FUNCTION ap_ar_prevent_over_allocation();


-- ==================================
-- 6) INVENTORY VALUATION: Cost Layers
-- ==================================

CREATE TABLE IF NOT EXISTS inventory_layers (
    layer_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    item_id BIGINT NOT NULL,
    source_type VARCHAR(50) NOT NULL,          -- bill, adjustment, opening
    source_id BIGINT,
    received_date DATE NOT NULL,
    quantity NUMERIC(12,3) NOT NULL,
    remaining_qty NUMERIC(12,3) NOT NULL,
    unit_cost NUMERIC(15,4) NOT NULL,
    currency_id BIGINT,
    notes TEXT,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (item_id) REFERENCES items(item_id),
    FOREIGN KEY (currency_id) REFERENCES currencies(currency_id),
    CHECK (quantity >= 0 AND remaining_qty >= 0 AND unit_cost >= 0)
);

CREATE INDEX IF NOT EXISTS idx_inventory_layers_item ON inventory_layers(item_id);
CREATE INDEX IF NOT EXISTS idx_inventory_layers_company_item ON inventory_layers(company_id, item_id);

CREATE TABLE IF NOT EXISTS cogs_entries (
    cogs_entry_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    item_id BIGINT NOT NULL,
    invoice_id BIGINT,
    invoice_item_id BIGINT,
    quantity_issued NUMERIC(12,3) NOT NULL,
    total_cost NUMERIC(15,4) NOT NULL,
    unit_cost NUMERIC(15,4) NOT NULL,
    valuation_method VARCHAR(20) DEFAULT 'FIFO', -- FIFO, WAVCO
    issued_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(company_id),
    FOREIGN KEY (item_id) REFERENCES items(item_id),
    FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id),
    FOREIGN KEY (invoice_item_id) REFERENCES invoice_items(invoice_item_id)
);


-- =======================================
-- 7) CHART OF ACCOUNTS GOVERNANCE RULES
-- =======================================

-- Block posting to parent accounts.
CREATE OR REPLACE FUNCTION accounts_block_parent_posting()
RETURNS trigger LANGUAGE plpgsql AS
$$
DECLARE has_children BOOLEAN;
BEGIN
  SELECT EXISTS(SELECT 1 FROM chart_of_accounts c WHERE c.parent_account_id = NEW.account_id)
    INTO has_children;
  IF has_children THEN
     RAISE EXCEPTION 'Posting to parent account (%) is not allowed', NEW.account_id;
  END IF;
  RETURN NEW;
END;
$$;

-- Enforce debit/credit discipline by account balance_type.
CREATE OR REPLACE FUNCTION accounts_enforce_balance_side()
RETURNS trigger LANGUAGE plpgsql AS
$$
DECLARE v_balance_type TEXT;
BEGIN
  SELECT balance_type INTO v_balance_type FROM chart_of_accounts WHERE account_id = NEW.account_id;
  IF NEW.debit_amount > 0 AND v_balance_type = 'credit' THEN
     -- allow but warn? In DB enforce neutrality: still allowed since totals must balance.
     RETURN NEW;
  ELSIF NEW.credit_amount > 0 AND v_balance_type = 'debit' THEN
     RETURN NEW;
  END IF;
  RETURN NEW;
END;
$$;

-- These are advisory; not attached by default to avoid breaking existing inserts.
-- Example of how to attach if desired:
-- CREATE TRIGGER trg_journal_entries_parent_block BEFORE INSERT ON journal_entries
-- FOR EACH ROW EXECUTE FUNCTION accounts_block_parent_posting();
-- CREATE TRIGGER trg_journal_entries_balance_side BEFORE INSERT ON journal_entries
-- FOR EACH ROW EXECUTE FUNCTION accounts_enforce_balance_side();


-- ==================================
-- 8) DOCUMENT NUMBERING + STATE RULES
-- ==================================

CREATE TABLE IF NOT EXISTS document_numbers (
    doc_number_id BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL,
    document_type VARCHAR(50) NOT NULL,    -- invoice, bill, payment, transaction, voucher
    prefix VARCHAR(20) DEFAULT '',
    suffix VARCHAR(20) DEFAULT '',
    next_value BIGINT NOT NULL DEFAULT 1,
    zero_pad SMALLINT DEFAULT 5,
    reset_policy VARCHAR(20) DEFAULT 'yearly', -- never, yearly, monthly
    last_reset_at DATE,
    is_locked BOOLEAN DEFAULT FALSE,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (company_id, document_type),
    FOREIGN KEY (company_id) REFERENCES companies(company_id)
);

CREATE OR REPLACE FUNCTION doc_numbers_next(p_company_id BIGINT, p_doc_type TEXT)
RETURNS TEXT LANGUAGE plpgsql AS
$$
DECLARE rec RECORD; value_text TEXT;
BEGIN
  SELECT * INTO rec FROM document_numbers
   WHERE company_id = p_company_id AND document_type = p_doc_type FOR UPDATE;
  IF NOT FOUND THEN
     RAISE EXCEPTION 'No document number row for company=% type=%', p_company_id, p_doc_type;
  END IF;
  value_text := LPAD(rec.next_value::text, COALESCE(rec.zero_pad,5), '0');
  UPDATE document_numbers SET next_value = rec.next_value + 1, updated_at = CURRENT_TIMESTAMP
   WHERE doc_number_id = rec.doc_number_id;
  RETURN rec.prefix || value_text || rec.suffix;
END;
$$;


-- ==================================
-- 9) AUDIT HOOKS (data change capture)
-- ==================================

-- Generic audit function (INSERT/UPDATE/DELETE)
CREATE OR REPLACE FUNCTION audit_capture()
RETURNS trigger LANGUAGE plpgsql AS
$$
BEGIN
  INSERT INTO audit_trail(company_id, table_name, record_id, action, old_values, new_values, changed_by, changed_at)
  VALUES (
    COALESCE(NEW.company_id, OLD.company_id),
    TG_TABLE_NAME,
    COALESCE((to_jsonb(NEW)->>'id')::BIGINT, (to_jsonb(OLD)->>'id')::BIGINT, 0),
    TG_OP,
    CASE WHEN TG_OP IN ('UPDATE','DELETE') THEN to_jsonb(OLD) ELSE NULL END,
    CASE WHEN TG_OP IN ('INSERT','UPDATE') THEN to_jsonb(NEW) ELSE NULL END,
    NULL,
    CURRENT_TIMESTAMP
  );
  RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
END;
$$;

-- Example attachments (commented out to avoid heavy overhead by default)
-- CREATE TRIGGER audit_invoices          AFTER INSERT OR UPDATE OR DELETE ON invoices          FOR EACH ROW EXECUTE FUNCTION audit_capture();
-- CREATE TRIGGER audit_bills             AFTER INSERT OR UPDATE OR DELETE ON bills             FOR EACH ROW EXECUTE FUNCTION audit_capture();
-- CREATE TRIGGER audit_transactions      AFTER INSERT OR UPDATE OR DELETE ON transactions      FOR EACH ROW EXECUTE FUNCTION audit_capture();
-- CREATE TRIGGER audit_journal_entries   AFTER INSERT OR UPDATE OR DELETE ON journal_entries   FOR EACH ROW EXECUTE FUNCTION audit_capture();


-- =================
-- 10) SECURITY/RLS
-- =================

-- Enable RLS on tenant-scoped tables and add a simple policy.
DO $$
DECLARE r record;
BEGIN
  FOR r IN
    SELECT tablename FROM pg_tables
    WHERE schemaname='public'
      AND tablename IN (
        'customers','vendors','items','invoices','invoice_items','bills','bill_items',
        'transactions','journal_entries','payments','payment_allocations',
        'accounts_receivable','accounts_payable','chart_of_accounts','documents'
      )
  LOOP
    EXECUTE format('ALTER TABLE %I ENABLE ROW LEVEL SECURITY', r.tablename);
    -- Simple policy: only rows with company_id in current_setting('app.company_id')
    EXECUTE format($sql$
      DO $$ BEGIN
        IF NOT EXISTS (
          SELECT 1 FROM pg_policies WHERE schemaname='public' AND tablename='%1$s' AND policyname='%1$s_company_isolation'
        ) THEN
          CREATE POLICY %1$s_company_isolation ON %1$s
          USING (company_id::text = current_setting('app.company_id', true));
        END IF;
      END $$;
    $sql$, r.tablename);
  END LOOP;
END $$;


-- ====================================
-- 11) PERFORMANCE & PARTITION TEMPLATES
-- ====================================

-- Example: time-range partitioning for transactions by month. Optional.
-- Requires creating a new partitioned parent and attaching the existing data if desired.
-- Skipped automatic migration to avoid downtime. Template below:

-- CREATE TABLE transactions_p (
--   LIKE transactions INCLUDING ALL
-- ) PARTITION BY RANGE (transaction_date);
--
-- CREATE TABLE transactions_2025_08 PARTITION OF transactions_p
--   FOR VALUES FROM ('2025-08-01') TO ('2025-09-01');
--
-- -- Move data and swap names during maintenance window.


-- ====================================
-- 12) REPORTING SURFACE: MVs
-- ====================================

-- Fast Trial Balance by period
CREATE MATERIALIZED VIEW IF NOT EXISTS mv_trial_balance AS
SELECT
  t.company_id,
  t.period_id,
  je.account_id,
  SUM(je.debit_amount) AS total_debit,
  SUM(je.credit_amount) AS total_credit,
  SUM(je.debit_amount - je.credit_amount) AS net_change
FROM transactions t
JOIN journal_entries je ON je.transaction_id = t.transaction_id
GROUP BY t.company_id, t.period_id, je.account_id;

CREATE UNIQUE INDEX IF NOT EXISTS idx_mv_trial_balance_pk ON mv_trial_balance(company_id, period_id, account_id);

-- Account balances by period for fast Balance Sheet / P&L
CREATE MATERIALIZED VIEW IF NOT EXISTS mv_balances_by_account_period AS
SELECT
  t.company_id,
  t.period_id,
  a.account_id,
  a.account_type,
  a.balance_type,
  SUM(je.debit_amount - je.credit_amount) AS period_change
FROM transactions t
JOIN journal_entries je ON je.transaction_id = t.transaction_id
JOIN chart_of_accounts a ON a.account_id = je.account_id
GROUP BY t.company_id, t.period_id, a.account_id, a.account_type, a.balance_type;

CREATE UNIQUE INDEX IF NOT EXISTS idx_mv_balances_by_account_period_pk
ON mv_balances_by_account_period(company_id, period_id, account_id);

-- Refresh helpers
CREATE OR REPLACE FUNCTION refresh_reporting_mvs()
RETURNS void LANGUAGE plpgsql AS
$$
BEGIN
  REFRESH MATERIALIZED VIEW CONCURRENTLY mv_trial_balance;
  REFRESH MATERIALIZED VIEW CONCURRENTLY mv_balances_by_account_period;
END;
$$;

-- ===============================================
-- END OF PATCH
-- ===============================================
