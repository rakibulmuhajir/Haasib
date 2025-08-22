-- 11_ar.sql — Accounts Receivable module (PostgreSQL)
-- Depends on: core, acct. Optionally references crm, bank.
-- This file is idempotent. It will add cross‑schema FKs only if targets exist.
BEGIN;

CREATE SCHEMA IF NOT EXISTS acct_ar;
SET search_path = acct_ar, acct, core, public;

-- =========================
-- Invoices (tenant‑scoped numbering)
-- =========================
CREATE TABLE IF NOT EXISTS invoices (
  invoice_id      BIGSERIAL PRIMARY KEY,
  company_id      BIGINT NOT NULL REFERENCES core.companies(company_id),
  customer_id     BIGINT, -- FK to crm.customers added later if crm exists
  invoice_number  VARCHAR(100) NOT NULL,
  reference_number VARCHAR(100),
  invoice_date    DATE NOT NULL,
  due_date        DATE NOT NULL CHECK (due_date >= invoice_date),
  currency_id     BIGINT NOT NULL REFERENCES core.currencies(currency_id),
  exchange_rate   DECIMAL(20,10) NOT NULL DEFAULT 1,
  subtotal        DECIMAL(15,2) NOT NULL DEFAULT 0,
  tax_amount      DECIMAL(15,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  shipping_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  total_amount    DECIMAL(15,2) NOT NULL DEFAULT 0,
  paid_amount     DECIMAL(15,2) NOT NULL DEFAULT 0,
  balance_due     DECIMAL(15,2) NOT NULL DEFAULT 0,
  status          VARCHAR(50) NOT NULL DEFAULT 'draft',     -- draft, sent, posted, cancelled
  payment_status  VARCHAR(50) NOT NULL DEFAULT 'unpaid',    -- unpaid, partial, paid, overpaid
  notes           TEXT,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by      BIGINT REFERENCES core.user_accounts(user_id),
  updated_by      BIGINT REFERENCES core.user_accounts(user_id),
  deleted_at      TIMESTAMP,
  UNIQUE (company_id, invoice_number)
);

CREATE INDEX IF NOT EXISTS idx_invoices_company ON invoices(company_id);
CREATE INDEX IF NOT EXISTS idx_invoices_dates ON invoices(company_id, invoice_date);
CREATE INDEX IF NOT EXISTS idx_invoices_status ON invoices(company_id, status);

-- =========================
-- Invoice line items
-- =========================
CREATE TABLE IF NOT EXISTS invoice_items (
  invoice_item_id BIGSERIAL PRIMARY KEY,
  invoice_id      BIGINT NOT NULL REFERENCES acct_ar.invoices(invoice_id) ON DELETE CASCADE,
  item_id         BIGINT, -- optional: reference inv.items when module installed
  description     VARCHAR(255) NOT NULL,
  quantity        DECIMAL(10,3) NOT NULL CHECK (quantity > 0),
  unit_price      DECIMAL(15,2) NOT NULL CHECK (unit_price >= 0),
  discount_percentage DECIMAL(5,2) NOT NULL DEFAULT 0 CHECK (discount_percentage BETWEEN 0 AND 100),
  discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0 CHECK (discount_amount >= 0),
  line_total      DECIMAL(15,2) NOT NULL CHECK (line_total >= 0),
  sort_order      INTEGER NOT NULL DEFAULT 0,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_invoice_items_invoice ON invoice_items(invoice_id);

-- =========================
-- Multi‑tax per line
-- =========================
CREATE TABLE IF NOT EXISTS invoice_item_taxes (
  invoice_item_id BIGINT NOT NULL REFERENCES acct_ar.invoice_items(invoice_item_id) ON DELETE CASCADE,
  tax_rate_id     BIGINT NOT NULL REFERENCES core.currencies(currency_id) DEFERRABLE INITIALLY IMMEDIATE,
  -- NOTE: tax_rate_id will be re‑FKed to tax table when that module is installed.
  tax_amount      DECIMAL(15,2) NOT NULL DEFAULT 0 CHECK (tax_amount >= 0),
  PRIMARY KEY (invoice_item_id, tax_rate_id)
);

-- =========================
-- Customer payments (AR)
-- =========================
CREATE TABLE IF NOT EXISTS payments (
  payment_id      BIGSERIAL PRIMARY KEY,
  company_id      BIGINT NOT NULL REFERENCES core.companies(company_id),
  payment_number  VARCHAR(100) NOT NULL,
  payment_type    VARCHAR(50) NOT NULL DEFAULT 'customer_payment',
  entity_type     VARCHAR(50) NOT NULL DEFAULT 'customer',
  entity_id       BIGINT, -- crm.customers; FK added later if crm exists
  bank_account_id BIGINT, -- bank.company_bank_accounts; FK added later if bank exists
  payment_method  VARCHAR(50) NOT NULL,   -- cash, bank_transfer, card
  payment_date    DATE NOT NULL,
  amount          DECIMAL(15,2) NOT NULL CHECK (amount > 0),
  currency_id     BIGINT NOT NULL REFERENCES core.currencies(currency_id),
  exchange_rate   DECIMAL(20,10) NOT NULL DEFAULT 1,
  reference_number VARCHAR(100),
  check_number    VARCHAR(50),
  bank_txn_id     VARCHAR(100),
  status          VARCHAR(50) NOT NULL DEFAULT 'completed',
  reconciled      BOOLEAN NOT NULL DEFAULT FALSE,
  reconciled_date TIMESTAMP,
  reconciled_by   BIGINT REFERENCES core.user_accounts(user_id),
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by      BIGINT REFERENCES core.user_accounts(user_id),
  updated_by      BIGINT REFERENCES core.user_accounts(user_id),
  deleted_at      TIMESTAMP,
  UNIQUE (company_id, payment_number)
);

CREATE INDEX IF NOT EXISTS idx_payments_company ON payments(company_id);
CREATE INDEX IF NOT EXISTS idx_payments_date ON payments(company_id, payment_date);

-- =========================
-- Validated allocations: only invoices for AR
-- =========================
CREATE TABLE IF NOT EXISTS payment_allocations (
  allocation_id   BIGSERIAL PRIMARY KEY,
  payment_id      BIGINT NOT NULL REFERENCES acct_ar.payments(payment_id) ON DELETE CASCADE,
  invoice_id      BIGINT NOT NULL REFERENCES acct_ar.invoices(invoice_id) ON DELETE CASCADE,
  allocated_amount DECIMAL(15,2) NOT NULL CHECK (allocated_amount > 0),
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_alloc_payment ON payment_allocations(payment_id);
CREATE INDEX IF NOT EXISTS idx_alloc_invoice ON payment_allocations(invoice_id);

-- Enforce entity_type consistency for AR
CREATE OR REPLACE FUNCTION acct_ar.trg_validate_ar_allocation() RETURNS TRIGGER AS $$
DECLARE v_entity_type TEXT; BEGIN
  SELECT entity_type INTO v_entity_type FROM acct_ar.payments WHERE payment_id = NEW.payment_id;
  IF v_entity_type <> 'customer' THEN
    RAISE EXCEPTION 'Only customer payments can allocate to invoices';
  END IF;
  RETURN NEW;
END; $$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS payment_allocations_bi ON acct_ar.payment_allocations;
CREATE TRIGGER payment_allocations_bi
BEFORE INSERT ON acct_ar.payment_allocations
FOR EACH ROW EXECUTE FUNCTION acct_ar.trg_validate_ar_allocation();

-- =========================
-- Optional AR summary table (materialized by app or triggers)
-- =========================
CREATE TABLE IF NOT EXISTS accounts_receivable (
  ar_id         BIGSERIAL PRIMARY KEY,
  company_id    BIGINT NOT NULL REFERENCES core.companies(company_id),
  customer_id   BIGINT, -- FK to crm.customers when available
  invoice_id    BIGINT NOT NULL UNIQUE REFERENCES acct_ar.invoices(invoice_id) ON DELETE CASCADE,
  amount_due    DECIMAL(15,2) NOT NULL CHECK (amount_due >= 0),
  original_amount DECIMAL(15,2) NOT NULL CHECK (original_amount > 0),
  currency_id   BIGINT NOT NULL REFERENCES core.currencies(currency_id),
  due_date      DATE NOT NULL,
  days_overdue  INTEGER NOT NULL DEFAULT 0
);

-- =========================
-- Conditional cross‑module foreign keys
-- =========================
DO $$
BEGIN
  -- crm.customers linkage
  IF EXISTS (
    SELECT 1 FROM information_schema.schemata WHERE schema_name = 'crm'
  ) AND EXISTS (
    SELECT 1 FROM information_schema.tables WHERE table_schema='crm' AND table_name='customers'
  ) THEN
    IF NOT EXISTS (
      SELECT 1 FROM pg_constraint WHERE conname = 'fk_ar_invoices_customer'
    ) THEN
      ALTER TABLE acct_ar.invoices
        ADD CONSTRAINT fk_ar_invoices_customer FOREIGN KEY (customer_id) REFERENCES crm.customers(customer_id);
    END IF;
    IF NOT EXISTS (
      SELECT 1 FROM pg_constraint WHERE conname = 'fk_ar_payments_customer'
    ) THEN
      ALTER TABLE acct_ar.payments
        ADD CONSTRAINT fk_ar_payments_customer FOREIGN KEY (entity_id) REFERENCES crm.customers(customer_id);
    END IF;
    IF NOT EXISTS (
      SELECT 1 FROM pg_constraint WHERE conname = 'fk_ar_summary_customer'
    ) THEN
      ALTER TABLE acct_ar.accounts_receivable
        ADD CONSTRAINT fk_ar_summary_customer FOREIGN KEY (customer_id) REFERENCES crm.customers(customer_id);
    END IF;
  END IF;

  -- bank.company_bank_accounts linkage
  IF EXISTS (
    SELECT 1 FROM information_schema.schemata WHERE schema_name = 'bank'
  ) AND EXISTS (
    SELECT 1 FROM information_schema.tables WHERE table_schema='bank' AND table_name='company_bank_accounts'
  ) THEN
    IF NOT EXISTS (
      SELECT 1 FROM pg_constraint WHERE conname = 'fk_ar_payments_bank_account'
    ) THEN
      ALTER TABLE acct_ar.payments
        ADD CONSTRAINT fk_ar_payments_bank_account FOREIGN KEY (bank_account_id) REFERENCES bank.company_bank_accounts(bank_account_id);
    END IF;
  END IF;

  -- inv.items linkage for invoice_items.item_id
  IF EXISTS (
    SELECT 1 FROM information_schema.schemata WHERE schema_name = 'inv'
  ) AND EXISTS (
    SELECT 1 FROM information_schema.tables WHERE table_schema='inv' AND table_name='items'
  ) THEN
    IF NOT EXISTS (
      SELECT 1 FROM pg_constraint WHERE conname = 'fk_ar_invoice_items_item'
    ) THEN
      ALTER TABLE acct_ar.invoice_items
        ADD CONSTRAINT fk_ar_invoice_items_item FOREIGN KEY (item_id) REFERENCES inv.items(item_id);
    END IF;
  END IF;

  -- tax_rates table if present (e.g., core.tax_rates or tax.tax_rates). Prefer core.tax_rates if exists
  IF EXISTS (
    SELECT 1 FROM information_schema.tables WHERE table_schema='core' AND table_name='tax_rates'
  ) THEN
    -- drop placeholder ref and add proper FK
    -- Note: our placeholder used core.currencies just to satisfy NOT NULL FK requirement.
    -- We now swap to core.tax_rates.
    IF EXISTS (
      SELECT 1 FROM pg_constraint WHERE conname = 'invoice_item_taxes_invoice_item_id_tax_rate_id_pkey'
    ) THEN
      -- primary key is fine; ensure foreign key
      IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_ar_item_taxes_tax_rate'
      ) THEN
        ALTER TABLE acct_ar.invoice_item_taxes
          DROP CONSTRAINT IF EXISTS invoice_item_taxes_tax_rate_id_fkey,
          ADD CONSTRAINT fk_ar_item_taxes_tax_rate FOREIGN KEY (tax_rate_id) REFERENCES core.tax_rates(tax_rate_id);
      END IF;
    END IF;
  END IF;
END$$;

COMMIT;
