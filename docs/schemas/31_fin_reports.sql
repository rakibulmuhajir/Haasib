-- 31_fin_reports.sql — Financial reporting views and functions (PostgreSQL)
-- Depends on: core, acct, 30_reporting (rpt schema)
BEGIN;

SET search_path = rpt, acct, core, public;

-- =============================================
-- Helper: get functional balance sign (debit/credit)
-- =============================================
CREATE OR REPLACE FUNCTION rpt.account_sign(p_balance_type TEXT) RETURNS INT AS $$
BEGIN
  RETURN CASE LOWER(p_balance_type)
           WHEN 'debit' THEN 1
           WHEN 'credit' THEN -1
           ELSE 1
         END;
END; $$ LANGUAGE plpgsql IMMUTABLE;

-- =============================================
-- Trial Balance (function) — parameterized by company and date range
-- Returns one row per account with opening, period, and closing
-- =============================================
CREATE OR REPLACE FUNCTION rpt.trial_balance(
  p_company_id BIGINT,
  p_start_date DATE,
  p_end_date   DATE
) RETURNS TABLE (
  account_id BIGINT,
  account_code VARCHAR,
  account_name VARCHAR,
  balance_type VARCHAR,
  opening NUMERIC(18,2),
  period_debit NUMERIC(18,2),
  period_credit NUMERIC(18,2),
  closing NUMERIC(18,2)
) AS $$
BEGIN
  RETURN QUERY
  WITH base AS (
    SELECT a.account_id, a.account_code, a.account_name, a.balance_type,
           COALESCE(SUM(CASE WHEN t.transaction_date < p_start_date THEN je.debit_amount - je.credit_amount END),0) AS open_delta,
           COALESCE(SUM(CASE WHEN t.transaction_date >= p_start_date AND t.transaction_date <= p_end_date THEN je.debit_amount END),0) AS p_debit,
           COALESCE(SUM(CASE WHEN t.transaction_date >= p_start_date AND t.transaction_date <= p_end_date THEN je.credit_amount END),0) AS p_credit
    FROM acct.chart_of_accounts a
    LEFT JOIN acct.journal_entries je ON je.account_id = a.account_id
    LEFT JOIN acct.transactions t ON t.transaction_id = je.transaction_id AND t.company_id = a.company_id
    WHERE a.company_id = p_company_id
      AND (t.deleted_at IS NULL OR t.deleted_at IS NULL)
    GROUP BY a.account_id, a.account_code, a.account_name, a.balance_type
  )
  SELECT account_id, account_code, account_name, balance_type,
         -- opening in natural sign of the account
         CASE WHEN balance_type='debit' THEN open_delta ELSE -open_delta END AS opening,
         p_debit AS period_debit,
         p_credit AS period_credit,
         -- closing = opening + (period_debit - period_credit) for debit accounts; inverse for credit
         CASE WHEN balance_type='debit'
              THEN (CASE WHEN balance_type='debit' THEN open_delta ELSE -open_delta END) + (p_debit - p_credit)
              ELSE (CASE WHEN balance_type='debit' THEN open_delta ELSE -open_delta END) - (p_debit - p_credit)
         END AS closing
  FROM base
  ORDER BY account_code;
END; $$ LANGUAGE plpgsql STABLE;

-- =============================================
-- Profit & Loss (function) — by company and date range
-- Aggregates revenue and expense accounts
-- =============================================
CREATE OR REPLACE FUNCTION rpt.profit_and_loss(
  p_company_id BIGINT,
  p_start_date DATE,
  p_end_date   DATE
) RETURNS TABLE (
  section TEXT,         -- 'revenue' or 'expense'
  account_id BIGINT,
  account_code VARCHAR,
  account_name VARCHAR,
  amount NUMERIC(18,2)
) AS $$
BEGIN
  RETURN QUERY
  SELECT CASE WHEN LOWER(a.account_type) IN ('revenue','income') THEN 'revenue' ELSE 'expense' END AS section,
         a.account_id, a.account_code, a.account_name,
         SUM(je.credit_amount - je.debit_amount) AS amount
  FROM acct.chart_of_accounts a
  JOIN acct.journal_entries je ON je.account_id = a.account_id
  JOIN acct.transactions t ON t.transaction_id = je.transaction_id
  WHERE a.company_id = p_company_id
    AND LOWER(a.account_type) IN ('revenue','income','expense')
    AND t.transaction_date BETWEEN p_start_date AND p_end_date
  GROUP BY section, a.account_id, a.account_code, a.account_name
  ORDER BY section, account_code;
END; $$ LANGUAGE plpgsql STABLE;

-- =============================================
-- Balance Sheet (function) — as of date
-- Returns assets, liabilities, equity with account totals
-- =============================================
CREATE OR REPLACE FUNCTION rpt.balance_sheet(
  p_company_id BIGINT,
  p_as_of DATE
) RETURNS TABLE (
  section TEXT,         -- 'asset','liability','equity'
  account_id BIGINT,
  account_code VARCHAR,
  account_name VARCHAR,
  amount NUMERIC(18,2)
) AS $$
BEGIN
  RETURN QUERY
  WITH sums AS (
    SELECT a.account_id, a.account_code, a.account_name, LOWER(a.account_type) AS atype, a.balance_type,
           COALESCE(SUM(CASE WHEN t.transaction_date <= p_as_of THEN je.debit_amount - je.credit_amount END),0) AS delta
    FROM acct.chart_of_accounts a
    LEFT JOIN acct.journal_entries je ON je.account_id = a.account_id
    LEFT JOIN acct.transactions t ON t.transaction_id = je.transaction_id AND t.company_id = a.company_id
    WHERE a.company_id = p_company_id
    GROUP BY a.account_id, a.account_code, a.account_name, a.account_type, a.balance_type
  )
  SELECT CASE
           WHEN atype IN ('asset') THEN 'asset'
           WHEN atype IN ('liability') THEN 'liability'
           ELSE 'equity'
         END AS section,
         account_id, account_code, account_name,
         CASE WHEN balance_type='debit' THEN delta ELSE -delta END AS amount
  FROM sums
  WHERE atype IN ('asset','liability','equity')
  ORDER BY section, account_code;
END; $$ LANGUAGE plpgsql STABLE;

-- =============================================
-- Convenience views using current fiscal year of the company
-- =============================================
CREATE OR REPLACE VIEW rpt.v_trial_balance_current AS
SELECT fy.company_id,
       (rpt.trial_balance(fy.company_id, fy.start_date, fy.end_date)).*
FROM acct.fiscal_years fy
WHERE fy.is_current = TRUE;

-- P&L current FY view
CREATE OR REPLACE VIEW rpt.v_profit_and_loss_current AS
SELECT fy.company_id,
       (rpt.profit_and_loss(fy.company_id, fy.start_date, fy.end_date)).*
FROM acct.fiscal_years fy
WHERE fy.is_current = TRUE;

-- Balance sheet as of today per company
CREATE OR REPLACE VIEW rpt.v_balance_sheet_today AS
SELECT c.company_id,
       (rpt.balance_sheet(c.company_id, CURRENT_DATE)).*
FROM core.companies c;

-- =============================================
-- Optional: snapshot helpers writing to rpt.financial_statements
-- =============================================
CREATE OR REPLACE FUNCTION rpt.snapshot_trial_balance(
  p_company_id BIGINT,
  p_period_id  BIGINT
) RETURNS BIGINT AS $$
DECLARE v_fy BIGINT; v_name TEXT; v_json JSONB; v_stmt_id BIGINT; v_start DATE; v_end DATE; BEGIN
  SELECT fiscal_year_id, start_date, end_date INTO v_fy, v_start, v_end FROM acct.accounting_periods WHERE period_id=p_period_id;
  IF v_fy IS NULL THEN RAISE EXCEPTION 'Invalid period %', p_period_id; END IF;

  v_name := 'Trial Balance ' || to_char(v_start,'YYYY-MM-DD') || ' .. ' || to_char(v_end,'YYYY-MM-DD');
  SELECT jsonb_agg(to_jsonb(tb)) INTO v_json
  FROM rpt.trial_balance(p_company_id, v_start, v_end) AS tb;

  INSERT INTO rpt.financial_statements(company_id, fiscal_year_id, period_id, statement_type, name, statement_date, date_range_start, date_range_end, data, totals, status)
  VALUES (p_company_id, v_fy, p_period_id, 'trial_balance', v_name, v_end, v_start, v_end, v_json, '{}'::jsonb, 'finalized')
  RETURNING statement_id INTO v_stmt_id;
  RETURN v_stmt_id;
END; $$ LANGUAGE plpgsql VOLATILE;

COMMIT;
