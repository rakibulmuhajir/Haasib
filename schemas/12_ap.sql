-- 12_ap.sql — Accounts Payable module (PostgreSQL)
-- Depends on: core, acct. Optionally references crm, bank.
BEGIN;

CREATE SCHEMA IF NOT EXISTS acct_ap;
SET search_path = acct_ap, acct, core, public;

-- =========================
-- Bills (tenant‑scoped numbering)
-- =========================
CREATE TABLE IF NOT EXISTS bills (
  bill_id        BIGSERIAL PRIMARY KEY,
  company_id     BIGINT NOT NULL REFERENCES core.companies(company_id),
  vendor_id      BIGINT, -- FK to crm.vendors added later
  bill_number    VARCHAR(100) NOT NULL,
  reference_number VARCHAR(100),
  bill_date      DATE NOT NULL,
  due_date       DATE NOT NULL CHECK (due_date >= bill_date),
  currency_id    BIGINT NOT NULL REFERENCES core.currencies(currency_id),
  exchange_rate  DECIMAL(20,10) NOT NULL DEFAULT 1,
  subtotal       DECIMAL(15,2) NOT NULL DEFAULT 0,
  tax_amount     DECIMAL(15,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  shipping_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  total_amount   DECIMAL(15,2) NOT NULL DEFAULT 0,
  paid_amount    DECIMAL(15,2) NOT NULL DEFAULT 0,
  balance_due    DECIMAL(15,2) NOT NULL DEFAULT 0,
  status         VARCHAR(50) NOT NULL DEFAULT 'draft',   -- draft, approved, posted, cancelled
  payment_status VARCHAR(50) NOT NULL DEFAULT 'unpaid',  -- unpaid, partial, paid, overpaid
  notes          TEXT,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by     BIGINT REFERENCES core.user_accounts(user_id),
  updated_by     BIGINT REFERENCES core.user_accounts(user_id),
  deleted_at     TIMESTAMP,
  UNIQUE (company_id, bill_number)
);

CREATE INDEX IF NOT EXISTS idx_bills_company ON bills(company_id);
CREATE INDEX IF NOT EXISTS idx_bills_dates ON bills(company_id, bill_date);
CREATE INDEX IF NOT EXISTS idx_bills_status ON bills(company_id, status);

-- =========================
-- Bill line items
-- =========================
CREATE TABLE IF NOT EXISTS bill_items (
  bill_item_id   BIGSERIAL PRIMARY KEY,
  bill_id        BIGINT NOT NULL REFERENCES acct_ap.bills(bill_id) ON DELETE CASCADE,
  item_id        BIGINT, -- optional: reference inv.items when module installed
  description    VARCHAR(255) NOT NULL,
  quantity       DECIMAL(10,3) NOT NULL CHECK (quantity > 0),
  unit_price     DECIMAL(15,2) NOT NULL CHECK (unit_price >= 0),
  discount_percentage DECIMAL(5,2) NOT NULL DEFAULT 0 CHECK (discount_percentage BETWEEN 0 AND 100),
  discount_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  line_total     DECIMAL(15,2) NOT NULL CHECK (line_total >= 0),
  sort_order     INTEGER NOT NULL DEFAULT 0,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_bill_items_bill ON bill_items(bill_id);

-- =========================
-- Multi‑tax per bill line
-- =========================
CREATE TABLE IF NOT EXISTS bill_item_taxes (
  bill_item_id BIGINT NOT NULL REFERENCES acct_ap.bill_items(bill_item_id) ON DELETE CASCADE,
  tax_rate_id  BIGINT NOT NULL REFERENCES core.currencies(currency_id) DEFERRABLE INITIALLY IMMEDIATE,
  -- placeholder; replaced by tax_rates when available
  tax_amount   DECIMAL(15,2) NOT NULL DEFAULT 0 CHECK (tax_amount >= 0),
  PRIMARY KEY (bill_item_id, tax_rate_id)
);

-- =========================
-- Vendor payments (AP)
-- =========================
CREATE TABLE IF NOT EXISTS payments (
  payment_id     BIGSERIAL PRIMARY KEY,
  company_id     BIGINT NOT NULL REFERENCES core.companies(company_id),
  payment_number VARCHAR(100) NOT NULL,
  payment_type   VARCHAR(50) NOT NULL DEFAULT 'vendor_payment',
  entity_type    VARCHAR(50) NOT NULL DEFAULT 'vendor',
  entity_id      BIGINT, -- crm.vendors; FK later
  bank_account_id BIGINT, -- bank.company_bank_accounts; FK later
  payment_method VARCHAR(50) NOT NULL,
  payment_date   DATE NOT NULL,
  amount         DECIMAL(15,2) NOT NULL CHECK (amount > 0),
  currency_id    BIGINT NOT NULL REFERENCES core.currencies(currency_id),
  exchange_rate  DECIMAL(20,10) NOT NULL DEFAULT 1,
  reference_number VARCHAR(100),
  check_number   VARCHAR(50),
  bank_txn_id    VARCHAR(100),
  status         VARCHAR(50) NOT NULL DEFAULT 'completed',
  reconciled     BOOLEAN NOT NULL DEFAULT FALSE,
  reconciled_date TIMESTAMP,
  reconciled_by  BIGINT REFERENCES core.user_accounts(user_id),
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by     BIGINT REFERENCES core.user_accounts(user_id),
  updated_by     BIGINT REFERENCES core.user_accounts(user_id),
  deleted_at     TIMESTAMP,
  UNIQUE (company_id, payment_number)
);

CREATE INDEX IF NOT EXISTS idx_ap_payments_company ON payments(company_id);
CREATE INDEX IF NOT EXISTS idx_ap_payments_date ON payments(company_id, payment_date);

-- =========================
-- Validated allocations: only bills for AP
-- =========================
CREATE TABLE IF NOT EXISTS payment_allocations (
  allocation_id   BIGSERIAL PRIMARY KEY,
  payment_id      BIGINT NOT NULL REFERENCES acct_ap.payments(payment_id) ON DELETE CASCADE,
  bill_id         BIGINT NOT NULL REFERENCES acct_ap.bills(bill_id) ON DELETE CASCADE,
  allocated_amount DECIMAL(15,2) NOT NULL CHECK (allocated_amount > 0),
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_ap_alloc_payment ON payment_allocations(payment_id);
CREATE INDEX IF NOT EXISTS idx_ap_alloc_bill ON payment_allocations(bill_id);

-- Enforce entity_type consistency for AP
CREATE OR REPLACE FUNCTION acct_ap.trg_validate_ap_allocation() RETURNS TRIGGER AS $$
DECLARE v_entity_type TEXT; BEGIN
  SELECT entity_type INTO v_entity_type FROM acct_ap.payments WHERE payment_id = NEW.payment_id;
  IF v_entity_type <> 'vendor' THEN
    RAISE EXCEPTION 'Only vendor payments can allocate to bills';
  END IF;
  RETURN NEW;
END; $$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS payment_allocations_bi ON acct_ap.payment_allocations;
CREATE TRIGGER payment_allocations_bi
BEFORE INSERT ON acct_ap.payment_allocations
FOR EACH ROW EXECUTE FUNCTION acct_ap.trg_validate_ap_allocation();

-- =========================
-- Optional AP summary
-- =========================
CREATE TABLE IF NOT EXISTS accounts_payable (
  ap_id         BIGSERIAL PRIMARY KEY,
  company_id    BIGINT NOT NULL REFERENCES core.companies(company_id),
  vendor_id     BIGINT, -- crm.vendors when available
  bill_id       BIGINT NOT NULL UNIQUE REFERENCES acct_ap.bills(bill_id) ON DELETE CASCADE,
  amount_due    DECIMAL(15,2) NOT NULL CHECK (amount_due >= 0),
  original_amount DECIMAL(15,2) NOT NULL CHECK (original_amount > 0),
  currency_id   BIGINT NOT NULL REFERENCES core.currencies(currency_id),
  due_date      DATE NOT NULL,
  days_overdue  INTEGER NOT NULL DEFAULT 0
);

-- =========================
-- Conditional cross‑module FKs
-- =========================
DO $$
BEGIN
  -- crm.vendors
  IF EXISTS (
    SELECT 1 FROM information_schema.schemata WHERE schema_name = 'crm'
  ) AND EXISTS (
    SELECT 1 FROM information_schema.tables WHERE table_schema='crm' AND table_name='vendors'
  ) THEN
    IF NOT EXISTS (
      SELECT 1 FROM pg_constraint WHERE conname = 'fk_ap_bills_vendor'
    ) THEN
      ALTER TABLE acct_ap.bills
        ADD CONSTRAINT fk_ap_bills_vendor FOREIGN KEY (vendor_id) REFERENCES crm.vendors(vendor_id);
    END IF;
    IF NOT EXISTS (
      SELECT 1 FROM pg_constraint WHERE conname = 'fk_ap_payments_vendor'
    ) THEN
      ALTER TABLE acct_ap.payments
        ADD CONSTRAINT fk_ap_payments_vendor FOREIGN KEY (entity_id) REFERENCES crm.vendors(vendor_id);
    END IF;
    IF NOT EXISTS (
      SELECT 1 FROM pg_constraint WHERE conname = 'fk_ap_summary_vendor'
    ) THEN
      ALTER TABLE acct_ap.accounts_payable
        ADD CONSTRAINT fk_ap_summary_vendor FOREIGN KEY (vendor_id) REFERENCES crm.vendors(vendor_id);
    END IF;
  END IF;

  -- bank.company_bank_accounts
  IF EXISTS (
    SELECT 1 FROM information_schema.schemata WHERE schema_name = 'bank'
  ) AND EXISTS (
    SELECT 1 FROM information_schema.tables WHERE table_schema='bank' AND table_name='company_bank_accounts'
  ) THEN
    IF NOT EXISTS (
      SELECT 1 FROM pg_constraint WHERE conname = 'fk_ap_payments_bank_account'
    ) THEN
      ALTER TABLE acct_ap.payments
        ADD CONSTRAINT fk_ap_payments_bank_account FOREIGN KEY (bank_account_id) REFERENCES bank.company_bank_accounts(bank_account_id);
    END IF;
  END IF;

  -- inv.items linkage for bill_items.item_id
  IF EXISTS (
    SELECT 1 FROM information_schema.schemata WHERE schema_name = 'inv'
  ) AND EXISTS (
    SELECT 1 FROM information_schema.tables WHERE table_schema='inv' AND table_name='items'
  ) THEN
    IF NOT EXISTS (
      SELECT 1 FROM pg_constraint WHERE conname = 'fk_ap_bill_items_item'
    ) THEN
      ALTER TABLE acct_ap.bill_items
        ADD CONSTRAINT fk_ap_bill_items_item FOREIGN KEY (item_id) REFERENCES inv.items(item_id);
    END IF;
  END IF;

  -- tax_rates table if present
  IF EXISTS (
    SELECT 1 FROM information_schema.tables WHERE table_schema='core' AND table_name='tax_rates'
  ) THEN
    IF NOT EXISTS (
      SELECT 1 FROM pg_constraint WHERE conname = 'fk_ap_item_taxes_tax_rate'
    ) THEN
      ALTER TABLE acct_ap.bill_item_taxes
        DROP CONSTRAINT IF EXISTS bill_item_taxes_tax_rate_id_fkey,
        ADD CONSTRAINT fk_ap_item_taxes_tax_rate FOREIGN KEY (tax_rate_id) REFERENCES core.tax_rates(tax_rate_id);
    END IF;
  END IF;
END$$;

COMMIT;
