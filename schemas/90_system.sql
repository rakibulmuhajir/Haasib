-- 90_system.sql — System utilities, configuration, audit, background jobs (PostgreSQL)
-- Depends on: core. Safe to load last.
BEGIN;

CREATE SCHEMA IF NOT EXISTS sys;
SET search_path = sys, core, public;

-- =========================
-- System settings (key-value per company)
-- =========================
CREATE TABLE IF NOT EXISTS settings (
  setting_id    BIGSERIAL PRIMARY KEY,
  company_id    BIGINT NOT NULL REFERENCES core.companies(company_id),
  key           VARCHAR(255) NOT NULL,
  value         TEXT,
  value_type    VARCHAR(50) NOT NULL DEFAULT 'string', -- string, int, decimal, bool, json
  is_encrypted  BOOLEAN NOT NULL DEFAULT FALSE,
  is_active     BOOLEAN NOT NULL DEFAULT TRUE,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by    BIGINT REFERENCES core.user_accounts(user_id),
  updated_by    BIGINT REFERENCES core.user_accounts(user_id),
  UNIQUE (company_id, key)
);

-- =========================
-- API keys
-- =========================
CREATE TABLE IF NOT EXISTS api_keys (
  api_key_id    BIGSERIAL PRIMARY KEY,
  company_id    BIGINT REFERENCES core.companies(company_id),
  user_id       BIGINT REFERENCES core.user_accounts(user_id),
  key           VARCHAR(255) NOT NULL UNIQUE,
  description   TEXT,
  permissions   JSONB NOT NULL DEFAULT '[]'::jsonb,
  expires_at    TIMESTAMP,
  is_active     BOOLEAN NOT NULL DEFAULT TRUE,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at    TIMESTAMP
);

-- =========================
-- Webhooks
-- =========================
CREATE TABLE IF NOT EXISTS webhooks (
  webhook_id    BIGSERIAL PRIMARY KEY,
  company_id    BIGINT REFERENCES core.companies(company_id),
  name          VARCHAR(255) NOT NULL,
  url           VARCHAR(500) NOT NULL,
  method        VARCHAR(10) NOT NULL DEFAULT 'POST',
  headers       JSONB NOT NULL DEFAULT '{}'::jsonb,
  events        JSONB NOT NULL DEFAULT '[]'::jsonb,
  secret        VARCHAR(255),
  is_active     BOOLEAN NOT NULL DEFAULT TRUE,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- =========================
-- Audit log
-- =========================
CREATE TABLE IF NOT EXISTS audit_log (
  audit_id      BIGSERIAL PRIMARY KEY,
  company_id    BIGINT REFERENCES core.companies(company_id),
  user_id       BIGINT REFERENCES core.user_accounts(user_id),
  action        VARCHAR(100) NOT NULL,
  table_name    VARCHAR(255),
  record_id     BIGINT,
  details       JSONB NOT NULL DEFAULT '{}'::jsonb,
  ip_address    INET,
  user_agent    TEXT,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- =========================
-- Background jobs (queue)
-- =========================
CREATE TABLE IF NOT EXISTS jobs (
  job_id        BIGSERIAL PRIMARY KEY,
  company_id    BIGINT REFERENCES core.companies(company_id),
  job_type      VARCHAR(100) NOT NULL,
  payload       JSONB NOT NULL DEFAULT '{}'::jsonb,
  status        VARCHAR(50) NOT NULL DEFAULT 'pending', -- pending, running, completed, failed
  retries       INTEGER NOT NULL DEFAULT 0,
  max_retries   INTEGER NOT NULL DEFAULT 3,
  scheduled_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  started_at    TIMESTAMP,
  finished_at   TIMESTAMP,
  error_message TEXT,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- =========================
-- Notifications (system-wide)
-- =========================
CREATE TABLE IF NOT EXISTS notifications (
  notification_id BIGSERIAL PRIMARY KEY,
  company_id    BIGINT REFERENCES core.companies(company_id),
  user_id       BIGINT REFERENCES core.user_accounts(user_id),
  type          VARCHAR(100) NOT NULL,
  message       TEXT NOT NULL,
  payload       JSONB NOT NULL DEFAULT '{}'::jsonb,
  is_read       BOOLEAN NOT NULL DEFAULT FALSE,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- =========================
-- Error log (system-level)
-- =========================
CREATE TABLE IF NOT EXISTS error_log (
  error_id      BIGSERIAL PRIMARY KEY,
  company_id    BIGINT REFERENCES core.companies(company_id),
  user_id       BIGINT REFERENCES core.user_accounts(user_id),
  error_code    VARCHAR(100),
  message       TEXT NOT NULL,
  details       JSONB NOT NULL DEFAULT '{}'::jsonb,
  stack_trace   TEXT,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- =========================
-- Suggested indexes
-- =========================
CREATE INDEX IF NOT EXISTS idx_settings_company ON settings(company_id);
CREATE INDEX IF NOT EXISTS idx_api_keys_company ON api_keys(company_id);
CREATE INDEX IF NOT EXISTS idx_webhooks_company ON webhooks(company_id);
CREATE INDEX IF NOT EXISTS idx_audit_company ON audit_log(company_id);
CREATE INDEX IF NOT EXISTS idx_jobs_company_status ON jobs(company_id, status);
CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_error_company ON error_log(company_id);

COMMIT;
