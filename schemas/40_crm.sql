-- 40_crm.sql — CRM module (PostgreSQL)
-- Depends on: core. Optionally links to acct_ar, acct_ap.
BEGIN;

CREATE SCHEMA IF NOT EXISTS crm;
SET search_path = crm, core, public;

-- =========================
-- Customers
-- =========================
CREATE TABLE IF NOT EXISTS customers (
  customer_id   BIGSERIAL PRIMARY KEY,
  company_id    BIGINT NOT NULL REFERENCES core.companies(company_id),
  name          VARCHAR(255) NOT NULL,
  email         VARCHAR(255),
  phone         VARCHAR(50),
  tax_number    VARCHAR(100),
  billing_address TEXT,
  shipping_address TEXT,
  currency_id   BIGINT REFERENCES core.currencies(currency_id),
  is_active     BOOLEAN NOT NULL DEFAULT TRUE,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by    BIGINT REFERENCES core.user_accounts(user_id),
  updated_by    BIGINT REFERENCES core.user_accounts(user_id),
  UNIQUE (company_id, name)
);

-- =========================
-- Vendors
-- =========================
CREATE TABLE IF NOT EXISTS vendors (
  vendor_id     BIGSERIAL PRIMARY KEY,
  company_id    BIGINT NOT NULL REFERENCES core.companies(company_id),
  name          VARCHAR(255) NOT NULL,
  email         VARCHAR(255),
  phone         VARCHAR(50),
  tax_number    VARCHAR(100),
  address       TEXT,
  currency_id   BIGINT REFERENCES core.currencies(currency_id),
  is_active     BOOLEAN NOT NULL DEFAULT TRUE,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by    BIGINT REFERENCES core.user_accounts(user_id),
  updated_by    BIGINT REFERENCES core.user_accounts(user_id),
  UNIQUE (company_id, name)
);

-- =========================
-- Contacts (linked to customers or vendors)
-- =========================
CREATE TABLE IF NOT EXISTS contacts (
  contact_id    BIGSERIAL PRIMARY KEY,
  company_id    BIGINT NOT NULL REFERENCES core.companies(company_id),
  first_name    VARCHAR(100) NOT NULL,
  last_name     VARCHAR(100) NOT NULL,
  email         VARCHAR(255),
  phone         VARCHAR(50),
  customer_id   BIGINT REFERENCES crm.customers(customer_id) ON DELETE CASCADE,
  vendor_id     BIGINT REFERENCES crm.vendors(vendor_id) ON DELETE CASCADE,
  position      VARCHAR(100),
  notes         TEXT,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- =========================
-- Interactions (CRM activity log)
-- =========================
CREATE TABLE IF NOT EXISTS interactions (
  interaction_id BIGSERIAL PRIMARY KEY,
  company_id     BIGINT NOT NULL REFERENCES core.companies(company_id),
  customer_id    BIGINT REFERENCES crm.customers(customer_id),
  vendor_id      BIGINT REFERENCES crm.vendors(vendor_id),
  contact_id     BIGINT REFERENCES crm.contacts(contact_id),
  interaction_type VARCHAR(50) NOT NULL, -- call, meeting, email, note
  subject        VARCHAR(255),
  details        TEXT,
  interaction_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by     BIGINT REFERENCES core.user_accounts(user_id),
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- =========================
-- Conditional links to AR/AP modules
-- =========================
DO $$
BEGIN
  -- Link AR invoices to customers
  IF EXISTS (
    SELECT 1 FROM information_schema.tables WHERE table_schema='acct_ar' AND table_name='invoices'
  ) THEN
    IF NOT EXISTS (
      SELECT 1 FROM pg_constraint WHERE conname='fk_ar_invoices_customer'
    ) THEN
      ALTER TABLE acct_ar.invoices
        ADD CONSTRAINT fk_ar_invoices_customer FOREIGN KEY (customer_id)
        REFERENCES crm.customers(customer_id);
    END IF;
  END IF;

  -- Link AP bills to vendors
  IF EXISTS (
    SELECT 1 FROM information_schema.tables WHERE table_schema='acct_ap' AND table_name='bills'
  ) THEN
    IF NOT EXISTS (
      SELECT 1 FROM pg_constraint WHERE conname='fk_ap_bills_vendor'
    ) THEN
      ALTER TABLE acct_ap.bills
        ADD CONSTRAINT fk_ap_bills_vendor FOREIGN KEY (vendor_id)
        REFERENCES crm.vendors(vendor_id);
    END IF;
  END IF;
END$$;

COMMIT;
