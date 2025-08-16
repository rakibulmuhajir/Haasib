-- 00_core.sql — Core shared schema (PostgreSQL)
-- Modules depend on this. Idempotent. No concurrent indexes inside transaction.
BEGIN;

CREATE SCHEMA IF NOT EXISTS core;
SET search_path = core, public;

-- =========================
-- Reference: currencies
-- =========================
CREATE TABLE IF NOT EXISTS currencies (
  currency_id   BIGSERIAL PRIMARY KEY,
  code          VARCHAR(10) NOT NULL UNIQUE,       -- e.g., USD, EUR
  name          VARCHAR(100) NOT NULL,
  symbol        VARCHAR(10) NOT NULL,
  decimal_places SMALLINT NOT NULL DEFAULT 2 CHECK (decimal_places BETWEEN 0 AND 6),
  is_active     BOOLEAN NOT NULL DEFAULT TRUE,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- =========================
-- Reference: countries
-- =========================
CREATE TABLE IF NOT EXISTS countries (
  country_id  BIGSERIAL PRIMARY KEY,
  code        VARCHAR(3) NOT NULL UNIQUE,          -- ISO 3166-1 alpha-3
  name        VARCHAR(255) NOT NULL,
  currency_id BIGINT REFERENCES currencies(currency_id),
  phone_prefix VARCHAR(10),
  is_active   BOOLEAN NOT NULL DEFAULT TRUE,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- =========================
-- Tenants: companies
-- =========================
CREATE TABLE IF NOT EXISTS companies (
  company_id   BIGSERIAL PRIMARY KEY,
  name         VARCHAR(255) NOT NULL,
  legal_name   VARCHAR(255),
  registration_number VARCHAR(100),
  tax_number   VARCHAR(100),
  country_id   BIGINT REFERENCES countries(country_id),
  primary_currency_id BIGINT NOT NULL REFERENCES currencies(currency_id),
  fiscal_year_start_month SMALLINT NOT NULL DEFAULT 1 CHECK (fiscal_year_start_month BETWEEN 1 AND 12),
  schema_name  VARCHAR(63) NOT NULL UNIQUE,
  industry     VARCHAR(100),
  website      VARCHAR(255),
  phone        VARCHAR(50),
  email        VARCHAR(255),
  address      TEXT,
  city         VARCHAR(100),
  state        VARCHAR(100),
  postal_code  VARCHAR(20),
  is_active    BOOLEAN NOT NULL DEFAULT TRUE,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by   BIGINT,
  updated_by   BIGINT,
  deleted_at   TIMESTAMP
);

-- =========================
-- Users (per-tenant)
-- =========================
CREATE TABLE IF NOT EXISTS user_accounts (
  user_id        BIGSERIAL PRIMARY KEY,
  company_id     BIGINT NOT NULL REFERENCES companies(company_id),
  username       VARCHAR(255) NOT NULL,
  email          VARCHAR(255) NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  first_name     VARCHAR(255) NOT NULL,
  last_name      VARCHAR(255) NOT NULL,
  role           VARCHAR(50)  NOT NULL DEFAULT 'user',
  permissions    JSONB NOT NULL DEFAULT '{}'::jsonb,
  timezone       VARCHAR(50) NOT NULL DEFAULT 'UTC',
  language       VARCHAR(10) NOT NULL DEFAULT 'en',
  is_active      BOOLEAN NOT NULL DEFAULT TRUE,
  is_verified    BOOLEAN NOT NULL DEFAULT FALSE,
  last_login     TIMESTAMP,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by     BIGINT,
  updated_by     BIGINT,
  deleted_at     TIMESTAMP,
  CONSTRAINT uq_user_unique_per_company UNIQUE (company_id, username),
  CONSTRAINT uq_email_global UNIQUE (email)
);

-- Add creator/updater FKs after both tables exist
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_constraint WHERE conname = 'fk_companies_created_by'
  ) THEN
    ALTER TABLE companies
      ADD CONSTRAINT fk_companies_created_by FOREIGN KEY (created_by) REFERENCES user_accounts(user_id);
  END IF;
  IF NOT EXISTS (
    SELECT 1 FROM pg_constraint WHERE conname = 'fk_companies_updated_by'
  ) THEN
    ALTER TABLE companies
      ADD CONSTRAINT fk_companies_updated_by FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id);
  END IF;
  IF NOT EXISTS (
    SELECT 1 FROM pg_constraint WHERE conname = 'fk_users_created_by'
  ) THEN
    ALTER TABLE user_accounts
      ADD CONSTRAINT fk_users_created_by FOREIGN KEY (created_by) REFERENCES user_accounts(user_id);
  END IF;
  IF NOT EXISTS (
    SELECT 1 FROM pg_constraint WHERE conname = 'fk_users_updated_by'
  ) THEN
    ALTER TABLE user_accounts
      ADD CONSTRAINT fk_users_updated_by FOREIGN KEY (updated_by) REFERENCES user_accounts(user_id);
  END IF;
END$$;

-- =========================
-- Exchange rates (shared)
-- =========================
CREATE TABLE IF NOT EXISTS exchange_rates (
  exchange_rate_id  BIGSERIAL PRIMARY KEY,
  base_currency_id  BIGINT NOT NULL REFERENCES currencies(currency_id),
  target_currency_id BIGINT NOT NULL REFERENCES currencies(currency_id),
  rate              DECIMAL(20,10) NOT NULL CHECK (rate > 0),
  effective_date    DATE NOT NULL,
  source            VARCHAR(50) NOT NULL DEFAULT 'manual',
  is_active         BOOLEAN NOT NULL DEFAULT TRUE,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_rate UNIQUE (base_currency_id, target_currency_id, effective_date),
  CONSTRAINT chk_diff_ccy CHECK (base_currency_id <> target_currency_id)
);

-- =========================
-- Helpful indexes (non-concurrent)
-- =========================
CREATE INDEX IF NOT EXISTS idx_user_company ON user_accounts(company_id);
CREATE INDEX IF NOT EXISTS idx_company_country ON companies(country_id);
CREATE INDEX IF NOT EXISTS idx_country_currency ON countries(currency_id);
CREATE INDEX IF NOT EXISTS idx_rates_pair_date ON exchange_rates(base_currency_id, target_currency_id, effective_date);

COMMIT;
