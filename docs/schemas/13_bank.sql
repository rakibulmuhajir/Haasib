-- 13_bank.sql — Banking module (PostgreSQL)
-- Depends on: core. Optionally links to acct, acct_ar, acct_ap.
BEGIN;

CREATE SCHEMA IF NOT EXISTS bank;
SET search_path = bank, core, public;

-- =========================
-- Reference: banks (optional catalogue)
-- =========================
CREATE TABLE IF NOT EXISTS banks (
  bank_id     BIGSERIAL PRIMARY KEY,
  name        VARCHAR(255) NOT NULL,
  swift_code  VARCHAR(11),
  country_id  BIGINT REFERENCES core.countries(country_id),
  logo_url    VARCHAR(500),
  website     VARCHAR(255),
  is_active   BOOLEAN NOT NULL DEFAULT TRUE,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- =========================
-- Company bank accounts
-- =========================
CREATE TABLE IF NOT EXISTS company_bank_accounts (
  bank_account_id BIGSERIAL PRIMARY KEY,
  company_id      BIGINT NOT NULL REFERENCES core.companies(company_id),
  bank_id         BIGINT REFERENCES bank.banks(bank_id),
  account_name    VARCHAR(255) NOT NULL,
  account_number  VARCHAR(100) NOT NULL,
  account_type    VARCHAR(50) NOT NULL DEFAULT 'checking', -- checking, savings, credit_card
  currency_id     BIGINT NOT NULL REFERENCES core.currencies(currency_id),
  iban            VARCHAR(34),
  swift_code      VARCHAR(11),
  branch          VARCHAR(255),
  opening_balance DECIMAL(15,2) NOT NULL DEFAULT 0,
  current_balance DECIMAL(15,2) NOT NULL DEFAULT 0,
  is_primary      BOOLEAN NOT NULL DEFAULT FALSE,
  is_active       BOOLEAN NOT NULL DEFAULT TRUE,
  reconciliation_date DATE,
  last_statement_date DATE,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by      BIGINT REFERENCES core.user_accounts(user_id),
  updated_by      BIGINT REFERENCES core.user_accounts(user_id),
  UNIQUE (company_id, account_number)
);

CREATE INDEX IF NOT EXISTS idx_bank_accts_company ON company_bank_accounts(company_id);

-- =========================
-- Bank reconciliations
-- =========================
CREATE TABLE IF NOT EXISTS bank_reconciliations (
  reconciliation_id BIGSERIAL PRIMARY KEY,
  company_id        BIGINT NOT NULL REFERENCES core.companies(company_id),
  bank_account_id   BIGINT NOT NULL REFERENCES bank.company_bank_accounts(bank_account_id),
  statement_date    DATE NOT NULL,
  statement_balance DECIMAL(15,2) NOT NULL,
  book_balance      DECIMAL(15,2) NOT NULL,
  adjusted_balance  DECIMAL(15,2) NOT NULL,
  status            VARCHAR(50) NOT NULL DEFAULT 'in_progress', -- in_progress, completed
  reconciled_by     BIGINT REFERENCES core.user_accounts(user_id),
  reconciled_date   TIMESTAMP,
  notes             TEXT,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_bank_recs_account ON bank_reconciliations(bank_account_id, statement_date);

-- =========================
-- Bank transactions
-- =========================
CREATE TABLE IF NOT EXISTS bank_transactions (
  bank_transaction_id BIGSERIAL PRIMARY KEY,
  company_id          BIGINT NOT NULL REFERENCES core.companies(company_id),
  bank_account_id     BIGINT NOT NULL REFERENCES bank.company_bank_accounts(bank_account_id),
  reconciliation_id   BIGINT REFERENCES bank.bank_reconciliations(reconciliation_id),
  transaction_date    DATE NOT NULL,
  description         TEXT NOT NULL,
  reference_number    VARCHAR(100),
  amount              DECIMAL(15,2) NOT NULL,            -- sign indicates direction
  transaction_type    VARCHAR(50) NOT NULL,               -- debit, credit
  balance             DECIMAL(15,2),
  is_reconciled       BOOLEAN NOT NULL DEFAULT FALSE,
  matched_ar_payment_id BIGINT,  -- acct_ar.payments
  matched_ap_payment_id BIGINT,  -- acct_ap.payments
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CHECK (transaction_type IN ('debit','credit')),
  CHECK (NOT (matched_ar_payment_id IS NOT NULL AND matched_ap_payment_id IS NOT NULL))
);

CREATE INDEX IF NOT EXISTS idx_bank_tx_account_date ON bank_transactions(bank_account_id, transaction_date);
CREATE INDEX IF NOT EXISTS idx_bank_tx_company ON bank_transactions(company_id);

-- =========================
-- Conditional cross‑module FKs
-- =========================
DO $$
BEGIN
  -- Link bank account to a GL account when acct.chart_of_accounts exists
  IF EXISTS (
    SELECT 1 FROM information_schema.tables WHERE table_schema='acct' AND table_name='chart_of_accounts'
  ) THEN
    IF NOT EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema='bank' AND table_name='company_bank_accounts' AND column_name='gl_account_id'
    ) THEN
      ALTER TABLE bank.company_bank_accounts ADD COLUMN gl_account_id BIGINT;
    END IF;
    IF NOT EXISTS (
      SELECT 1 FROM pg_constraint WHERE conname='fk_bank_acct_gl_account'
    ) THEN
      ALTER TABLE bank.company_bank_accounts
        ADD CONSTRAINT fk_bank_acct_gl_account FOREIGN KEY (gl_account_id)
        REFERENCES acct.chart_of_accounts(account_id);
    END IF;
  END IF;

  -- Match to AR payments when module present
  IF EXISTS (
    SELECT 1 FROM information_schema.tables WHERE table_schema='acct_ar' AND table_name='payments'
  ) THEN
    IF NOT EXISTS (
      SELECT 1 FROM pg_constraint WHERE conname='fk_bank_tx_ar_payment'
    ) THEN
      ALTER TABLE bank.bank_transactions
        ADD CONSTRAINT fk_bank_tx_ar_payment FOREIGN KEY (matched_ar_payment_id)
        REFERENCES acct_ar.payments(payment_id) ON DELETE SET NULL;
    END IF;
  END IF;

  -- Match to AP payments when module present
  IF EXISTS (
    SELECT 1 FROM information_schema.tables WHERE table_schema='acct_ap' AND table_name='payments'
  ) THEN
    IF NOT EXISTS (
      SELECT 1 FROM pg_constraint WHERE conname='fk_bank_tx_ap_payment'
    ) THEN
      ALTER TABLE bank.bank_transactions
        ADD CONSTRAINT fk_bank_tx_ap_payment FOREIGN KEY (matched_ap_payment_id)
        REFERENCES acct_ap.payments(payment_id) ON DELETE SET NULL;
    END IF;
  END IF;
END$$;

COMMIT;
