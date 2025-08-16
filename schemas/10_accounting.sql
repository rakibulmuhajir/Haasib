-- 10_accounting.sql — Core Accounting schema (PostgreSQL)
-- Depends on core schema. Provides periods, chart of accounts, GL, and posting guards.
BEGIN;

CREATE SCHEMA IF NOT EXISTS acct;
SET search_path = acct, core, public;

-- =========================
-- Fiscal years
-- =========================
CREATE TABLE IF NOT EXISTS fiscal_years (
  fiscal_year_id BIGSERIAL PRIMARY KEY,
  company_id     BIGINT NOT NULL REFERENCES core.companies(company_id),
  name           VARCHAR(100) NOT NULL,
  start_date     DATE NOT NULL,
  end_date       DATE NOT NULL CHECK (end_date > start_date),
  is_current     BOOLEAN NOT NULL DEFAULT FALSE,
  is_closed      BOOLEAN NOT NULL DEFAULT FALSE,
  status         VARCHAR(50) NOT NULL DEFAULT 'open',
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by     BIGINT REFERENCES core.user_accounts(user_id),
  updated_by     BIGINT REFERENCES core.user_accounts(user_id)
);

-- =========================
-- Accounting periods
-- =========================
CREATE TABLE IF NOT EXISTS accounting_periods (
  period_id    BIGSERIAL PRIMARY KEY,
  company_id   BIGINT NOT NULL REFERENCES core.companies(company_id),
  fiscal_year_id BIGINT NOT NULL REFERENCES acct.fiscal_years(fiscal_year_id),
  name         VARCHAR(100) NOT NULL,
  start_date   DATE NOT NULL,
  end_date     DATE NOT NULL CHECK (end_date > start_date),
  period_type  VARCHAR(20) NOT NULL DEFAULT 'monthly',
  is_closed    BOOLEAN NOT NULL DEFAULT FALSE,
  closed_by    BIGINT REFERENCES core.user_accounts(user_id),
  closed_at    TIMESTAMP,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by   BIGINT REFERENCES core.user_accounts(user_id),
  updated_by   BIGINT REFERENCES core.user_accounts(user_id)
);

-- =========================
-- Chart of accounts
-- =========================
CREATE TABLE IF NOT EXISTS chart_of_accounts (
  account_id   BIGSERIAL PRIMARY KEY,
  company_id   BIGINT NOT NULL REFERENCES core.companies(company_id),
  parent_account_id BIGINT REFERENCES acct.chart_of_accounts(account_id),
  account_code VARCHAR(50) NOT NULL,
  account_name VARCHAR(255) NOT NULL,
  account_type VARCHAR(50) NOT NULL,  -- asset, liability, equity, revenue, expense
  account_subtype VARCHAR(50),
  balance_type VARCHAR(10) NOT NULL DEFAULT 'debit',
  is_system_account BOOLEAN NOT NULL DEFAULT FALSE,
  is_active    BOOLEAN NOT NULL DEFAULT TRUE,
  opening_balance DECIMAL(15,2) NOT NULL DEFAULT 0 CHECK (opening_balance >= 0),
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by   BIGINT REFERENCES core.user_accounts(user_id),
  updated_by   BIGINT REFERENCES core.user_accounts(user_id),
  deleted_at   TIMESTAMP,
  UNIQUE (company_id, account_code)
);

-- =========================
-- General ledger transactions
-- =========================
CREATE TABLE IF NOT EXISTS transactions (
  transaction_id BIGSERIAL PRIMARY KEY,
  company_id     BIGINT NOT NULL REFERENCES core.companies(company_id),
  transaction_number VARCHAR(100) NOT NULL,
  transaction_type   VARCHAR(50) NOT NULL, -- journal_entry, invoice, bill, payment, receipt
  reference_type VARCHAR(50),
  reference_id   BIGINT,
  transaction_date DATE NOT NULL,
  description    TEXT,
  currency_id    BIGINT NOT NULL REFERENCES core.currencies(currency_id),
  exchange_rate  DECIMAL(20,10) NOT NULL DEFAULT 1,
  total_debit    DECIMAL(15,2) NOT NULL DEFAULT 0 CHECK (total_debit >= 0),
  total_credit   DECIMAL(15,2) NOT NULL DEFAULT 0 CHECK (total_credit >= 0),
  status         VARCHAR(50) NOT NULL DEFAULT 'posted',
  reversal_transaction_id BIGINT REFERENCES acct.transactions(transaction_id),
  period_id      BIGINT REFERENCES acct.accounting_periods(period_id),
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by     BIGINT REFERENCES core.user_accounts(user_id),
  updated_by     BIGINT REFERENCES core.user_accounts(user_id),
  deleted_at     TIMESTAMP,
  UNIQUE (company_id, transaction_number),
  CHECK (total_debit = total_credit)
);

-- =========================
-- Journal entries
-- =========================
CREATE TABLE IF NOT EXISTS journal_entries (
  entry_id      BIGSERIAL PRIMARY KEY,
  transaction_id BIGINT NOT NULL REFERENCES acct.transactions(transaction_id) ON DELETE CASCADE,
  account_id    BIGINT NOT NULL REFERENCES acct.chart_of_accounts(account_id),
  debit_amount  DECIMAL(15,2) NOT NULL DEFAULT 0 CHECK (debit_amount >= 0),
  credit_amount DECIMAL(15,2) NOT NULL DEFAULT 0 CHECK (credit_amount >= 0),
  description   TEXT,
  reference_type VARCHAR(50),
  reference_id  BIGINT,
  sort_order    INTEGER NOT NULL DEFAULT 0,
  functional_currency_id BIGINT REFERENCES core.currencies(currency_id),
  fx_rate       DECIMAL(20,10),
  functional_debit DECIMAL(15,2) DEFAULT 0 CHECK (functional_debit >= 0),
  functional_credit DECIMAL(15,2) DEFAULT 0 CHECK (functional_credit >= 0),
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CHECK ((debit_amount > 0 AND credit_amount = 0) OR (credit_amount > 0 AND debit_amount = 0))
);

-- =========================
-- Trigger: recompute totals
-- =========================
CREATE OR REPLACE FUNCTION acct.recompute_transaction_totals(p_tx BIGINT) RETURNS VOID AS $$
BEGIN
  UPDATE acct.transactions t
  SET total_debit = COALESCE(s.sum_debit,0),
      total_credit = COALESCE(s.sum_credit,0)
  FROM (
    SELECT transaction_id,
           SUM(debit_amount) AS sum_debit,
           SUM(credit_amount) AS sum_credit
    FROM acct.journal_entries
    WHERE transaction_id = p_tx
    GROUP BY transaction_id
  ) s
  WHERE t.transaction_id = p_tx;
END; $$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION acct.trg_after_journal_change() RETURNS TRIGGER AS $$
BEGIN
  PERFORM acct.recompute_transaction_totals(COALESCE(NEW.transaction_id, OLD.transaction_id));
  RETURN NULL;
END; $$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS journal_entries_aiud ON acct.journal_entries;
CREATE TRIGGER journal_entries_aiud
AFTER INSERT OR UPDATE OR DELETE ON acct.journal_entries
FOR EACH ROW EXECUTE FUNCTION acct.trg_after_journal_change();

-- =========================
-- Trigger: block postings into closed periods
-- =========================
CREATE OR REPLACE FUNCTION acct.trg_check_period_open() RETURNS TRIGGER AS $$
DECLARE v_closed BOOL; BEGIN
  IF NEW.period_id IS NOT NULL THEN
    SELECT is_closed INTO v_closed FROM acct.accounting_periods WHERE period_id = NEW.period_id;
    IF v_closed THEN RAISE EXCEPTION 'Accounting period % is closed', NEW.period_id; END IF;
  END IF;
  RETURN NEW;
END; $$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS transactions_biu_period ON acct.transactions;
CREATE TRIGGER transactions_biu_period
BEFORE INSERT OR UPDATE ON acct.transactions
FOR EACH ROW EXECUTE FUNCTION acct.trg_check_period_open();

-- =========================
-- Indexes
-- =========================
CREATE INDEX IF NOT EXISTS idx_tx_company_date ON acct.transactions(company_id, transaction_date);
CREATE INDEX IF NOT EXISTS idx_je_tx ON acct.journal_entries(transaction_id);
CREATE INDEX IF NOT EXISTS idx_coa_company ON acct.chart_of_accounts(company_id);

COMMIT;
