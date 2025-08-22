-- 50_payroll.sql — Payroll module (PostgreSQL)
-- Depends on: core. Optionally integrates with acct.
BEGIN;

CREATE SCHEMA IF NOT EXISTS pay;
SET search_path = pay, core, public;

-- =========================
-- Employees
-- =========================
CREATE TABLE IF NOT EXISTS employees (
  employee_id   BIGSERIAL PRIMARY KEY,
  company_id    BIGINT NOT NULL REFERENCES core.companies(company_id),
  first_name    VARCHAR(100) NOT NULL,
  last_name     VARCHAR(100) NOT NULL,
  email         VARCHAR(255),
  phone         VARCHAR(50),
  hire_date     DATE NOT NULL,
  termination_date DATE,
  position      VARCHAR(100),
  department    VARCHAR(100),
  salary        DECIMAL(15,2) NOT NULL,
  currency_id   BIGINT REFERENCES core.currencies(currency_id),
  pay_frequency VARCHAR(50) NOT NULL DEFAULT 'monthly', -- monthly, biweekly, weekly
  is_active     BOOLEAN NOT NULL DEFAULT TRUE,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by    BIGINT REFERENCES core.user_accounts(user_id),
  updated_by    BIGINT REFERENCES core.user_accounts(user_id)
);

-- =========================
-- Payroll periods
-- =========================
CREATE TABLE IF NOT EXISTS payroll_periods (
  period_id     BIGSERIAL PRIMARY KEY,
  company_id    BIGINT NOT NULL REFERENCES core.companies(company_id),
  period_start  DATE NOT NULL,
  period_end    DATE NOT NULL,
  payment_date  DATE NOT NULL,
  status        VARCHAR(50) NOT NULL DEFAULT 'open', -- open, closed, posted
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (company_id, period_start, period_end)
);

-- =========================
-- Payroll runs
-- =========================
CREATE TABLE IF NOT EXISTS payroll_runs (
  run_id        BIGSERIAL PRIMARY KEY,
  company_id    BIGINT NOT NULL REFERENCES core.companies(company_id),
  period_id     BIGINT NOT NULL REFERENCES pay.payroll_periods(period_id) ON DELETE CASCADE,
  run_date      DATE NOT NULL DEFAULT CURRENT_DATE,
  status        VARCHAR(50) NOT NULL DEFAULT 'draft', -- draft, approved, posted
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  approved_by   BIGINT REFERENCES core.user_accounts(user_id),
  approved_at   TIMESTAMP
);

-- =========================
-- Payroll details
-- =========================
CREATE TABLE IF NOT EXISTS payroll_details (
  detail_id     BIGSERIAL PRIMARY KEY,
  run_id        BIGINT NOT NULL REFERENCES pay.payroll_runs(run_id) ON DELETE CASCADE,
  employee_id   BIGINT NOT NULL REFERENCES pay.employees(employee_id),
  gross_pay     DECIMAL(15,2) NOT NULL,
  deductions    DECIMAL(15,2) NOT NULL DEFAULT 0,
  net_pay       DECIMAL(15,2) NOT NULL,
  currency_id   BIGINT REFERENCES core.currencies(currency_id),
  notes         TEXT
);

-- =========================
-- Payroll deductions
-- =========================
CREATE TABLE IF NOT EXISTS payroll_deductions (
  deduction_id  BIGSERIAL PRIMARY KEY,
  detail_id     BIGINT NOT NULL REFERENCES pay.payroll_details(detail_id) ON DELETE CASCADE,
  deduction_type VARCHAR(100) NOT NULL, -- tax, insurance, pension, loan, custom
  amount        DECIMAL(15,2) NOT NULL,
  description   TEXT
);

-- =========================
-- Conditional link to GL
-- =========================
DO $$
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.tables WHERE table_schema='acct' AND table_name='chart_of_accounts'
  ) THEN
    IF NOT EXISTS (
      SELECT 1 FROM information_schema.tables WHERE table_schema='pay' AND table_name='payroll_gl_mappings'
    ) THEN
      CREATE TABLE pay.payroll_gl_mappings (
        mapping_id   BIGSERIAL PRIMARY KEY,
        company_id   BIGINT NOT NULL REFERENCES core.companies(company_id),
        expense_account_id BIGINT NOT NULL REFERENCES acct.chart_of_accounts(account_id),
        liability_account_id BIGINT NOT NULL REFERENCES acct.chart_of_accounts(account_id),
        description  TEXT,
        created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
      );
    END IF;
  END IF;
END$$;

COMMIT;
