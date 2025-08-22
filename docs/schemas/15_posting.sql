-- 15_posting.sql — Automatic posting templates and functions (PostgreSQL)
-- Depends on: core, acct, acct_ar, acct_ap; optionally tax.
BEGIN;

CREATE SCHEMA IF NOT EXISTS acct_post;
SET search_path = acct_post, acct, acct_ar, acct_ap, tax, core, public;

-- =========================
-- Template headers and lines
-- =========================
CREATE TABLE IF NOT EXISTS posting_templates (
  template_id  BIGSERIAL PRIMARY KEY,
  company_id   BIGINT NOT NULL REFERENCES core.companies(company_id),
  doc_type     VARCHAR(30) NOT NULL,        -- 'AR_INVOICE', 'AP_BILL'
  name         VARCHAR(255) NOT NULL,
  is_active    BOOLEAN NOT NULL DEFAULT TRUE,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (company_id, doc_type, name)
);

-- Each line specifies an account and whether it's debit or credit
CREATE TABLE IF NOT EXISTS posting_template_lines (
  line_id      BIGSERIAL PRIMARY KEY,
  template_id  BIGINT NOT NULL REFERENCES acct_post.posting_templates(template_id) ON DELETE CASCADE,
  role         VARCHAR(50) NOT NULL,        -- AR, Revenue, TaxPayable, Discount, Shipping, AP, Expense
  account_id   BIGINT NOT NULL REFERENCES acct.chart_of_accounts(account_id),
  precedence   SMALLINT NOT NULL DEFAULT 1
);

-- =========================
-- Helper: compute tax total for an AR invoice
-- =========================
CREATE OR REPLACE FUNCTION acct_post.tax_total_for_invoice(p_invoice_id BIGINT) RETURNS NUMERIC AS $$
DECLARE v_total NUMERIC; BEGIN
  SELECT COALESCE(SUM(t.tax_amount),0)
    INTO v_total
  FROM acct_ar.invoice_items i
  JOIN acct_ar.invoice_item_taxes t ON t.invoice_item_id = i.invoice_item_id
  WHERE i.invoice_id = p_invoice_id;
  RETURN v_total;
END; $$ LANGUAGE plpgsql;

-- Helper: compute tax total for an AP bill
CREATE OR REPLACE FUNCTION acct_post.tax_total_for_bill(p_bill_id BIGINT) RETURNS NUMERIC AS $$
DECLARE v_total NUMERIC; BEGIN
  SELECT COALESCE(SUM(t.tax_amount),0)
    INTO v_total
  FROM acct_ap.bill_items i
  JOIN acct_ap.bill_item_taxes t ON t.bill_item_id = i.bill_item_id
  WHERE i.bill_id = p_bill_id;
  RETURN v_total;
END; $$ LANGUAGE plpgsql;

-- =========================
-- Post AR invoice to GL
-- =========================
CREATE OR REPLACE FUNCTION acct_post.post_ar_invoice(p_invoice_id BIGINT, p_template_id BIGINT) RETURNS BIGINT AS $$
DECLARE
  v_tx_id BIGINT;
  v_company BIGINT; v_curr BIGINT; v_date DATE; v_number TEXT; v_total NUMERIC; v_sub NUMERIC; v_disc NUMERIC; v_ship NUMERIC; v_tax NUMERIC; v_ar_acc BIGINT; v_rev_acc BIGINT; v_tax_acc BIGINT; v_disc_acc BIGINT; v_ship_acc BIGINT; v_period BIGINT;
  v_tax_enabled BOOLEAN;
BEGIN
  SELECT i.company_id, i.currency_id, i.invoice_date, i.invoice_number, i.total_amount, i.subtotal, i.discount_amount, i.shipping_amount
    INTO v_company, v_curr, v_date, v_number, v_total, v_sub, v_disc, v_ship
  FROM acct_ar.invoices i WHERE i.invoice_id = p_invoice_id;

  IF v_company IS NULL THEN RAISE EXCEPTION 'Invoice % not found', p_invoice_id; END IF;

  -- tax toggle
  SELECT COALESCE(s.enabled, FALSE) INTO v_tax_enabled
  FROM tax.company_tax_settings s WHERE s.company_id = v_company;

  v_tax := CASE WHEN v_tax_enabled THEN acct_post.tax_total_for_invoice(p_invoice_id) ELSE 0 END;

  -- accounts from template
  SELECT MAX(CASE WHEN role='AR' THEN account_id END),
         MAX(CASE WHEN role='Revenue' THEN account_id END),
         MAX(CASE WHEN role='TaxPayable' THEN account_id END),
         MAX(CASE WHEN role='Discount' THEN account_id END),
         MAX(CASE WHEN role='Shipping' THEN account_id END)
    INTO v_ar_acc, v_rev_acc, v_tax_acc, v_disc_acc, v_ship_acc
  FROM acct_post.posting_template_lines WHERE template_id = p_template_id;

  IF v_ar_acc IS NULL OR v_rev_acc IS NULL THEN
    RAISE EXCEPTION 'Posting template % incomplete', p_template_id;
  END IF;

  -- pick period if available
  SELECT period_id INTO v_period FROM acct.accounting_periods
   WHERE company_id=v_company AND v_date BETWEEN start_date AND end_date AND is_closed=FALSE
   ORDER BY start_date LIMIT 1;

  INSERT INTO acct.transactions(company_id, transaction_number, transaction_type, reference_type, reference_id, transaction_date, description, currency_id, period_id)
  VALUES (v_company, CONCAT('AR-', v_number), 'invoice', 'acct_ar.invoices', p_invoice_id, v_date, 'Auto-post AR invoice', v_curr, v_period)
  RETURNING transaction_id INTO v_tx_id;

  -- Debit AR
  INSERT INTO acct.journal_entries(transaction_id, account_id, debit_amount, description)
  VALUES (v_tx_id, v_ar_acc, v_total, 'AR control');

  -- Credit Revenue for subtotal minus discount and shipping (shipping handled separately if account provided)
  INSERT INTO acct.journal_entries(transaction_id, account_id, credit_amount, description)
  VALUES (v_tx_id, v_rev_acc, GREATEST(v_sub - COALESCE(v_disc,0),0), 'Revenue');

  -- Discount (debit) if configured
  IF v_disc_acc IS NOT NULL AND COALESCE(v_disc,0) > 0 THEN
    INSERT INTO acct.journal_entries(transaction_id, account_id, debit_amount, description)
    VALUES (v_tx_id, v_disc_acc, v_disc, 'Discounts given');
  END IF;

  -- Shipping (credit) if configured
  IF v_ship_acc IS NOT NULL AND COALESCE(v_ship,0) > 0 THEN
    INSERT INTO acct.journal_entries(transaction_id, account_id, credit_amount, description)
    VALUES (v_tx_id, v_ship_acc, v_ship, 'Shipping income');
  END IF;

  -- Tax payable (credit) when enabled
  IF v_tax_enabled AND COALESCE(v_tax,0) > 0 THEN
    IF v_tax_acc IS NULL THEN RAISE EXCEPTION 'Tax enabled but TaxPayable account not set in template %', p_template_id; END IF;
    INSERT INTO acct.journal_entries(transaction_id, account_id, credit_amount, description)
    VALUES (v_tx_id, v_tax_acc, v_tax, 'Tax payable');
  END IF;

  RETURN v_tx_id;
END; $$ LANGUAGE plpgsql;

-- =========================
-- Post AP bill to GL
-- =========================
CREATE OR REPLACE FUNCTION acct_post.post_ap_bill(p_bill_id BIGINT, p_template_id BIGINT) RETURNS BIGINT AS $$
DECLARE
  v_tx_id BIGINT;
  v_company BIGINT; v_curr BIGINT; v_date DATE; v_number TEXT; v_total NUMERIC; v_sub NUMERIC; v_disc NUMERIC; v_ship NUMERIC; v_tax NUMERIC; v_ap_acc BIGINT; v_exp_acc BIGINT; v_tax_recv BIGINT; v_disc_acc BIGINT; v_period BIGINT;
  v_tax_enabled BOOLEAN;
BEGIN
  SELECT b.company_id, b.currency_id, b.bill_date, b.bill_number, b.total_amount, b.subtotal, b.discount_amount, b.shipping_amount
    INTO v_company, v_curr, v_date, v_number, v_total, v_sub, v_disc, v_ship
  FROM acct_ap.bills b WHERE b.bill_id = p_bill_id;

  IF v_company IS NULL THEN RAISE EXCEPTION 'Bill % not found', p_bill_id; END IF;

  SELECT COALESCE(s.enabled, FALSE) INTO v_tax_enabled FROM tax.company_tax_settings s WHERE s.company_id = v_company;
  v_tax := CASE WHEN v_tax_enabled THEN acct_post.tax_total_for_bill(p_bill_id) ELSE 0 END;

  -- accounts from template
  SELECT MAX(CASE WHEN role='AP' THEN account_id END),
         MAX(CASE WHEN role='Expense' THEN account_id END),
         MAX(CASE WHEN role='TaxReceivable' THEN account_id END),
         MAX(CASE WHEN role='Discount' THEN account_id END)
    INTO v_ap_acc, v_exp_acc, v_tax_recv, v_disc_acc
  FROM acct_post.posting_template_lines WHERE template_id = p_template_id;

  IF v_ap_acc IS NULL OR v_exp_acc IS NULL THEN
    RAISE EXCEPTION 'Posting template % incomplete', p_template_id;
  END IF;

  SELECT period_id INTO v_period FROM acct.accounting_periods
   WHERE company_id=v_company AND v_date BETWEEN start_date AND end_date AND is_closed=FALSE
   ORDER BY start_date LIMIT 1;

  INSERT INTO acct.transactions(company_id, transaction_number, transaction_type, reference_type, reference_id, transaction_date, description, currency_id, period_id)
  VALUES (v_company, CONCAT('AP-', v_number), 'bill', 'acct_ap.bills', p_bill_id, v_date, 'Auto-post AP bill', v_curr, v_period)
  RETURNING transaction_id INTO v_tx_id;

  -- Credit AP
  INSERT INTO acct.journal_entries(transaction_id, account_id, credit_amount, description)
  VALUES (v_tx_id, v_ap_acc, v_total, 'AP control');

  -- Debit Expense for subtotal minus discount
  INSERT INTO acct.journal_entries(transaction_id, account_id, debit_amount, description)
  VALUES (v_tx_id, v_exp_acc, GREATEST(v_sub - COALESCE(v_disc,0),0), 'Expense');

  -- Tax receivable (debit) when enabled
  IF v_tax_enabled AND COALESCE(v_tax,0) > 0 THEN
    IF v_tax_recv IS NULL THEN RAISE EXCEPTION 'Tax enabled but TaxReceivable account not set in template %', p_template_id; END IF;
    INSERT INTO acct.journal_entries(transaction_id, account_id, debit_amount, description)
    VALUES (v_tx_id, v_tax_recv, v_tax, 'Input tax receivable');
  END IF;

  -- Discount (credit) if configured
  IF v_disc_acc IS NOT NULL AND COALESCE(v_disc,0) > 0 THEN
    INSERT INTO acct.journal_entries(transaction_id, account_id, credit_amount, description)
    VALUES (v_tx_id, v_disc_acc, v_disc, 'Purchase discounts');
  END IF;

  RETURN v_tx_id;
END; $$ LANGUAGE plpgsql;

-- =========================
-- Optional: auto-post on status change to 'posted'
-- =========================
CREATE OR REPLACE FUNCTION acct_post.trg_autopost_ar() RETURNS TRIGGER AS $$
DECLARE v_tx BIGINT; v_tpl BIGINT; BEGIN
  IF NEW.status='posted' AND (OLD.status IS DISTINCT FROM 'posted') THEN
    SELECT template_id INTO v_tpl FROM acct_post.posting_templates
      WHERE company_id=NEW.company_id AND doc_type='AR_INVOICE' AND is_active
      ORDER BY template_id LIMIT 1;
    IF v_tpl IS NULL THEN RAISE EXCEPTION 'No active AR posting template for company %', NEW.company_id; END IF;
    PERFORM acct_post.post_ar_invoice(NEW.invoice_id, v_tpl);
  END IF;
  RETURN NEW;
END; $$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS ar_autopost ON acct_ar.invoices;
CREATE TRIGGER ar_autopost
AFTER UPDATE ON acct_ar.invoices
FOR EACH ROW EXECUTE FUNCTION acct_post.trg_autopost_ar();

CREATE OR REPLACE FUNCTION acct_post.trg_autopost_ap() RETURNS TRIGGER AS $$
DECLARE v_tx BIGINT; v_tpl BIGINT; BEGIN
  IF NEW.status='posted' AND (OLD.status IS DISTINCT FROM 'posted') THEN
    SELECT template_id INTO v_tpl FROM acct_post.posting_templates
      WHERE company_id=NEW.company_id AND doc_type='AP_BILL' AND is_active
      ORDER BY template_id LIMIT 1;
    IF v_tpl IS NULL THEN RAISE EXCEPTION 'No active AP posting template for company %', NEW.company_id; END IF;
    PERFORM acct_post.post_ap_bill(NEW.bill_id, v_tpl);
  END IF;
  RETURN NEW;
END; $$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS ap_autopost ON acct_ap.bills;
CREATE TRIGGER ap_autopost
AFTER UPDATE ON acct_ap.bills
FOR EACH ROW EXECUTE FUNCTION acct_post.trg_autopost_ap();

COMMIT;
