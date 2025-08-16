-- 14_tax.sql — Tax module (PostgreSQL)
-- Depends on: core. Integrates with: acct_ar, acct_ap. Country-aware, company-toggle.
BEGIN;

CREATE SCHEMA IF NOT EXISTS tax;
SET search_path = tax, core, public;

-- =========================
-- Jurisdictions (usually countries; extensible to state/province)
-- =========================
CREATE TABLE IF NOT EXISTS jurisdictions (
  jurisdiction_id BIGSERIAL PRIMARY KEY,
  country_id      BIGINT NOT NULL REFERENCES core.countries(country_id),
  code            VARCHAR(50) NOT NULL,      -- e.g., 'PK', 'US-CA'
  name            VARCHAR(255) NOT NULL,
  level           VARCHAR(50) NOT NULL DEFAULT 'country', -- country, state, city
  is_active       BOOLEAN NOT NULL DEFAULT TRUE,
  UNIQUE (country_id, code)
);

-- =========================
-- Company tax settings (feature toggle per tenant)
-- =========================
CREATE TABLE IF NOT EXISTS company_tax_settings (
  company_id       BIGINT PRIMARY KEY REFERENCES core.companies(company_id) ON DELETE CASCADE,
  enabled          BOOLEAN NOT NULL DEFAULT FALSE,
  default_jurisdiction_id BIGINT REFERENCES tax.jurisdictions(jurisdiction_id),
  price_includes_tax BOOLEAN NOT NULL DEFAULT FALSE,
  rounding_mode    VARCHAR(20) NOT NULL DEFAULT 'half_up', -- half_up, half_down, bankers
  rounding_precision SMALLINT NOT NULL DEFAULT 2 CHECK (rounding_precision BETWEEN 0 AND 6),
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- =========================
-- Tax rates and groups
-- =========================
CREATE TABLE IF NOT EXISTS tax_rates (
  tax_rate_id   BIGSERIAL PRIMARY KEY,
  company_id    BIGINT NOT NULL REFERENCES core.companies(company_id),
  jurisdiction_id BIGINT NOT NULL REFERENCES tax.jurisdictions(jurisdiction_id),
  code          VARCHAR(50) NOT NULL,         -- e.g., 'VAT-STD', 'GST'
  name          VARCHAR(255) NOT NULL,
  rate          DECIMAL(8,4) NOT NULL CHECK (rate >= 0 AND rate <= 100),
  tax_type      VARCHAR(50) NOT NULL DEFAULT 'sales', -- sales, purchase, withholding
  is_compound   BOOLEAN NOT NULL DEFAULT FALSE,
  effective_from DATE NOT NULL DEFAULT CURRENT_DATE,
  effective_to   DATE,
  is_active     BOOLEAN NOT NULL DEFAULT TRUE,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (company_id, code, effective_from)
);

CREATE TABLE IF NOT EXISTS tax_groups (
  tax_group_id  BIGSERIAL PRIMARY KEY,
  company_id    BIGINT NOT NULL REFERENCES core.companies(company_id),
  code          VARCHAR(50) NOT NULL,
  name          VARCHAR(255) NOT NULL,
  jurisdiction_id BIGINT NOT NULL REFERENCES tax.jurisdictions(jurisdiction_id),
  is_active     BOOLEAN NOT NULL DEFAULT TRUE,
  UNIQUE (company_id, code)
);

CREATE TABLE IF NOT EXISTS tax_group_components (
  tax_group_id  BIGINT NOT NULL REFERENCES tax.tax_groups(tax_group_id) ON DELETE CASCADE,
  tax_rate_id   BIGINT NOT NULL REFERENCES tax.tax_rates(tax_rate_id) ON DELETE RESTRICT,
  priority      SMALLINT NOT NULL DEFAULT 1,
  PRIMARY KEY (tax_group_id, tax_rate_id)
);

-- =========================
-- Registration & exemptions
-- =========================
CREATE TABLE IF NOT EXISTS company_tax_registrations (
  registration_id BIGSERIAL PRIMARY KEY,
  company_id    BIGINT NOT NULL REFERENCES core.companies(company_id),
  jurisdiction_id BIGINT NOT NULL REFERENCES tax.jurisdictions(jurisdiction_id),
  registration_number VARCHAR(100) NOT NULL,
  effective_from DATE NOT NULL,
  effective_to   DATE,
  is_active     BOOLEAN NOT NULL DEFAULT TRUE,
  UNIQUE (company_id, jurisdiction_id, registration_number)
);

CREATE TABLE IF NOT EXISTS tax_exemptions (
  exemption_id  BIGSERIAL PRIMARY KEY,
  company_id    BIGINT NOT NULL REFERENCES core.companies(company_id),
  code          VARCHAR(50) NOT NULL,
  name          VARCHAR(255) NOT NULL,
  description   TEXT,
  is_active     BOOLEAN NOT NULL DEFAULT TRUE,
  UNIQUE (company_id, code)
);

-- =========================
-- Conditional integration with AR/AP line taxes
-- =========================
DO $$
BEGIN
  -- Relink acct_ar.invoice_item_taxes.tax_rate_id → tax.tax_rates
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema='acct_ar' AND table_name='invoice_item_taxes') THEN
    -- Drop any existing foreign key on tax_rate_id and add ours
    IF EXISTS (
      SELECT 1 FROM pg_constraint c
      JOIN pg_class t ON t.oid = c.conrelid
      JOIN pg_namespace n ON n.oid = t.relnamespace
      WHERE n.nspname='acct_ar' AND t.relname='invoice_item_taxes' AND c.conname LIKE '%tax_rate_id%'
    ) THEN
      ALTER TABLE acct_ar.invoice_item_taxes DROP CONSTRAINT IF EXISTS invoice_item_taxes_tax_rate_id_fkey;
      ALTER TABLE acct_ar.invoice_item_taxes DROP CONSTRAINT IF EXISTS fk_ar_item_taxes_tax_rate;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='fk_ar_item_taxes_tax_rate_tax') THEN
      ALTER TABLE acct_ar.invoice_item_taxes
        ADD CONSTRAINT fk_ar_item_taxes_tax_rate_tax FOREIGN KEY (tax_rate_id)
        REFERENCES tax.tax_rates(tax_rate_id);
    END IF;
  END IF;

  -- Relink acct_ap.bill_item_taxes.tax_rate_id → tax.tax_rates
  IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema='acct_ap' AND table_name='bill_item_taxes') THEN
    IF EXISTS (
      SELECT 1 FROM pg_constraint c
      JOIN pg_class t ON t.oid = c.conrelid
      JOIN pg_namespace n ON n.oid = t.relnamespace
      WHERE n.nspname='acct_ap' AND t.relname='bill_item_taxes' AND c.conname LIKE '%tax_rate_id%'
    ) THEN
      ALTER TABLE acct_ap.bill_item_taxes DROP CONSTRAINT IF EXISTS bill_item_taxes_tax_rate_id_fkey;
      ALTER TABLE acct_ap.bill_item_taxes DROP CONSTRAINT IF EXISTS fk_ap_item_taxes_tax_rate;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname='fk_ap_item_taxes_tax_rate_tax') THEN
      ALTER TABLE acct_ap.bill_item_taxes
        ADD CONSTRAINT fk_ap_item_taxes_tax_rate_tax FOREIGN KEY (tax_rate_id)
        REFERENCES tax.tax_rates(tax_rate_id);
    END IF;
  END IF;
END $$;

COMMIT;
