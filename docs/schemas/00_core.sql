-- 00_core.sql — Core reference tables used across modules (PostgreSQL)
-- NOTE: Application migrations are the source of truth. This SQL mirrors
-- the current app models/migrations to avoid confusion for future devs.
-- Companies live in schema 'auth' (see app migrations). This file covers
-- public reference tables only.

BEGIN;
SET search_path = public;

-- =========================
CREATE TABLE IF NOT EXISTS currencies (
  id               UUID PRIMARY KEY,
  code             VARCHAR(3) NOT NULL UNIQUE,
  numeric_code     VARCHAR(3),
  name             VARCHAR(255) NOT NULL,
  symbol           VARCHAR(8),
  minor_unit       SMALLINT NOT NULL DEFAULT 2,
  cash_minor_unit  SMALLINT,
  rounding         DECIMAL(6,3) NOT NULL DEFAULT 0,
  fund             BOOLEAN NOT NULL DEFAULT FALSE,
  is_active        BOOLEAN NOT NULL DEFAULT TRUE,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at       TIMESTAMP NULL
);

-- =========================
CREATE TABLE IF NOT EXISTS countries (
  id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  code          CHAR(2) NOT NULL UNIQUE,                 -- ISO 3166-1 alpha-2
  alpha3        CHAR(3),                                 -- ISO 3166-1 alpha-3
  name          VARCHAR(255) NOT NULL,
  native_name   VARCHAR(255),
  region        VARCHAR(100),
  subregion     VARCHAR(100),
  emoji         VARCHAR(8),
  capital       VARCHAR(100),
  calling_code  VARCHAR(8),
  eea_member    BOOLEAN NOT NULL DEFAULT FALSE,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_countries_alpha3 ON countries(alpha3);

-- =========================
-- Companies are defined in `auth.companies` via application migrations.

-- =========================
-- Users are defined in public.user_accounts via application migrations.

-- =========================
CREATE TABLE IF NOT EXISTS exchange_rates (
  id                 UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  base_currency_id   UUID NOT NULL REFERENCES currencies(id),
  target_currency_id UUID NOT NULL REFERENCES currencies(id),
  rate               DECIMAL(20,10) NOT NULL CHECK (rate > 0),
  effective_date     DATE NOT NULL,
  source             VARCHAR(50) NOT NULL DEFAULT 'manual',
  is_active          BOOLEAN NOT NULL DEFAULT TRUE,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_rate UNIQUE (base_currency_id, target_currency_id, effective_date),
  CONSTRAINT chk_diff_ccy CHECK (base_currency_id <> target_currency_id)
);

-- =========================
-- Helpful indexes (non-concurrent)
-- =========================
CREATE INDEX IF NOT EXISTS idx_rates_pair_date ON exchange_rates(base_currency_id, target_currency_id, effective_date);

COMMIT;
