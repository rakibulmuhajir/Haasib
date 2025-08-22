-- 30_reporting.sql — Reporting & Financial Statements (PostgreSQL)
-- Depends on: core, acct. Safe, idempotent migrations.
BEGIN;

CREATE SCHEMA IF NOT EXISTS rpt;
SET search_path = rpt, acct, core, public;

-- =========================
-- Report templates (metadata-driven)
-- =========================
CREATE TABLE IF NOT EXISTS report_templates (
  template_id   BIGSERIAL PRIMARY KEY,
  company_id    BIGINT NOT NULL REFERENCES core.companies(company_id),
  name          VARCHAR(255) NOT NULL,
  description   TEXT,
  report_type   VARCHAR(100) NOT NULL,          -- profit_loss, balance_sheet, cash_flow, trial_balance, custom
  category      VARCHAR(100),                   -- financial, operational, analytical
  configuration JSONB NOT NULL DEFAULT '{}'::jsonb,
  sql_query     TEXT,
  parameters    JSONB NOT NULL DEFAULT '[]'::jsonb,
  is_system_template BOOLEAN NOT NULL DEFAULT FALSE,
  is_public     BOOLEAN NOT NULL DEFAULT FALSE,
  sort_order    INTEGER NOT NULL DEFAULT 0,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by    BIGINT REFERENCES core.user_accounts(user_id),
  updated_by    BIGINT REFERENCES core.user_accounts(user_id)
);

CREATE INDEX IF NOT EXISTS idx_rpt_templates_company ON report_templates(company_id);

-- =========================
-- Generated reports (files or cached results)
-- =========================
CREATE TABLE IF NOT EXISTS reports (
  report_id     BIGSERIAL PRIMARY KEY,
  company_id    BIGINT NOT NULL REFERENCES core.companies(company_id),
  template_id   BIGINT REFERENCES rpt.report_templates(template_id),
  name          VARCHAR(255) NOT NULL,
  report_type   VARCHAR(100) NOT NULL,
  parameters    JSONB NOT NULL DEFAULT '{}'::jsonb,
  filters       JSONB NOT NULL DEFAULT '{}'::jsonb,
  date_range_start DATE,
  date_range_end   DATE,
  status        VARCHAR(50) NOT NULL DEFAULT 'generated', -- generating, generated, failed
  file_path     VARCHAR(500),
  file_size     BIGINT,
  mime_type     VARCHAR(100),
  generated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at    TIMESTAMP,
  created_by    BIGINT REFERENCES core.user_accounts(user_id)
);

CREATE INDEX IF NOT EXISTS idx_reports_company ON reports(company_id);
CREATE INDEX IF NOT EXISTS idx_reports_template ON reports(template_id);

-- =========================
-- Financial statements storage (auditable snapshots)
-- =========================
CREATE TABLE IF NOT EXISTS financial_statements (
  statement_id  BIGSERIAL PRIMARY KEY,
  company_id    BIGINT NOT NULL REFERENCES core.companies(company_id),
  fiscal_year_id BIGINT NOT NULL REFERENCES acct.fiscal_years(fiscal_year_id),
  period_id     BIGINT REFERENCES acct.accounting_periods(period_id),
  statement_type VARCHAR(50) NOT NULL,          -- balance_sheet, profit_loss, cash_flow, equity
  name          VARCHAR(255) NOT NULL,
  statement_date DATE NOT NULL,
  date_range_start DATE,
  date_range_end   DATE,
  data          JSONB NOT NULL DEFAULT '{}'::jsonb,  -- rows/columns structure
  totals        JSONB NOT NULL DEFAULT '{}'::jsonb,
  comparative_data JSONB NOT NULL DEFAULT '{}'::jsonb,
  notes         TEXT,
  status        VARCHAR(50) NOT NULL DEFAULT 'draft', -- draft, finalized, published
  version       INTEGER NOT NULL DEFAULT 1,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by    BIGINT REFERENCES core.user_accounts(user_id),
  updated_by    BIGINT REFERENCES core.user_accounts(user_id),
  finalized_by  BIGINT REFERENCES core.user_accounts(user_id),
  finalized_at  TIMESTAMP,
  CONSTRAINT chk_version_gt0 CHECK (version > 0)
);

CREATE INDEX IF NOT EXISTS idx_fs_company_type_date ON financial_statements(company_id, statement_type, statement_date);

COMMIT;
