# Deployment & Operations Guide — SME Modular Accounting (PostgreSQL)

Target: PostgreSQL 14+
Audience: Backend developers and DevOps

---

## 1) Prerequisites

* PostgreSQL 14 or higher
* Superuser or a role with `CREATE` on the target database
* `psql` in PATH
* UTF‑8 DB, `timezone=UTC`
* Network access to DB from CI or local shell

Optional hardening (recommended):

* Create a dedicated role `app_owner` that owns schemas and objects
* Separate runtime role `app_user` with `USAGE` on schemas and `SELECT/INSERT/UPDATE/DELETE` on tables

```sql
-- Example roles
CREATE ROLE app_owner LOGIN PASSWORD '***';
CREATE ROLE app_user  LOGIN PASSWORD '***';
GRANT app_owner TO app_user; -- optional during bootstrap
```

---

## 2) Files and Load Order

```
00_core.sql
10_accounting.sql
11_ar.sql
12_ap.sql
13_bank.sql
14_tax.sql       # new
15_posting.sql   # new
20_inventory.sql
30_reporting.sql
40_crm.sql
50_payroll.sql
60_vms.sql
90_system.sql
```

Reasoning: `core` → `acct` → subledgers → bank; tax before posting; posting relies on AR/AP; others are optional.

---

## 3) Full Deployment

### 3.1 With helper script

```bash
PGHOST=localhost PGPORT=5432 PGDATABASE=mydb \
PGUSER=app_owner PGPASSWORD=*** ./deploy.sh
```

### 3.2 Manual

```bash
psql -v ON_ERROR_STOP=1 -f 00_core.sql
psql -v ON_ERROR_STOP=1 -f 10_accounting.sql
psql -v ON_ERROR_STOP=1 -f 11_ar.sql
psql -v ON_ERROR_STOP=1 -f 12_ap.sql
psql -v ON_ERROR_STOP=1 -f 13_bank.sql
psql -v ON_ERROR_STOP=1 -f 14_tax.sql
psql -v ON_ERROR_STOP=1 -f 15_posting.sql
psql -v ON_ERROR_STOP=1 -f 20_inventory.sql
psql -v ON_ERROR_STOP=1 -f 30_reporting.sql
psql -v ON_ERROR_STOP=1 -f 40_crm.sql
psql -v ON_ERROR_STOP=1 -f 50_payroll.sql
psql -v ON_ERROR_STOP=1 -f 60_vms.sql
psql -v ON_ERROR_STOP=1 -f 90_system.sql
```

Selective install: apply any prefix‑closed subset that respects dependencies. Example: core+acct+AR only → run `00,10,11`.

---

## 4) Post‑Install Verification

Run quick health checks.

```sql
-- Schemas exist
SELECT nspname FROM pg_namespace WHERE nspname IN ('core','acct','acct_ar','acct_ap','bank','tax','acct_post');

-- Core refs populated?
SELECT COUNT(*) currencies, COUNT(*) countries FROM core.currencies, core.countries;

-- Triggers installed
SELECT tgname, tgrelid::regclass FROM pg_trigger WHERE NOT tgisinternal AND tgname IN ('journal_entries_aiud','transactions_biu_period','ar_autopost','ap_autopost');
```

---

## 5) Tenant Onboarding

```sql
-- 5.1 Company
INSERT INTO core.companies (name, primary_currency_id, fiscal_year_start_month, schema_name)
VALUES ('Acme Travel', 1, 1, 'acme');  -- adjust currency id

-- 5.2 First user
INSERT INTO core.user_accounts (company_id, username, email, password_hash, first_name, last_name)
VALUES (1, 'admin', 'admin@acme.test', 'bcrypt$...','Sys','Admin');

-- 5.3 Fiscal year and periods
INSERT INTO acct.fiscal_years (company_id, name, start_date, end_date, is_current)
VALUES (1, 'FY2025', '2025-01-01', '2025-12-31', TRUE);

-- Generate periods (monthly example)
INSERT INTO acct.accounting_periods (company_id, fiscal_year_id, name, start_date, end_date)
SELECT 1, fiscal_year_id,
       to_char(d, 'Mon YYYY'),
       date_trunc('month', d)::date,
       (date_trunc('month', d) + INTERVAL '1 month - 1 day')::date
FROM acct.fiscal_years, generate_series('2025-01-01'::date, '2025-12-01'::date, '1 month') d;
```

---

## 6) Enable Tax per Company (Optional)

Populate country and jurisdiction, then toggle.

```sql
-- 6.1 Jurisdiction for company country (example Pakistan)
INSERT INTO tax.jurisdictions (country_id, code, name) VALUES ((SELECT country_id FROM core.countries WHERE code='PAK'), 'PK', 'Pakistan')
ON CONFLICT DO NOTHING;

-- 6.2 Company tax settings
INSERT INTO tax.company_tax_settings (company_id, enabled, default_jurisdiction_id, price_includes_tax, rounding_mode)
VALUES (1, TRUE, (SELECT jurisdiction_id FROM tax.jurisdictions WHERE code='PK'), FALSE, 'half_up')
ON CONFLICT (company_id) DO UPDATE SET enabled=EXCLUDED.enabled, default_jurisdiction_id=EXCLUDED.default_jurisdiction_id;

-- 6.3 Define rates and groups
INSERT INTO tax.tax_rates (company_id, jurisdiction_id, code, name, rate)
VALUES (1, (SELECT jurisdiction_id FROM tax.jurisdictions WHERE code='PK'), 'GST-STD', 'GST Standard', 18.0);

INSERT INTO tax.tax_groups (company_id, code, name, jurisdiction_id)
VALUES (1, 'STD', 'Standard Taxes', (SELECT jurisdiction_id FROM tax.jurisdictions WHERE code='PK'));

INSERT INTO tax.tax_group_components (tax_group_id, tax_rate_id)
VALUES (
  (SELECT tax_group_id FROM tax.tax_groups WHERE company_id=1 AND code='STD'),
  (SELECT tax_rate_id  FROM tax.tax_rates  WHERE company_id=1 AND code='GST-STD')
);
```

Assign taxes at line level through AR/AP tables:

* AR: `acct_ar.invoice_item_taxes (invoice_item_id, tax_rate_id, tax_amount)`
* AP: `acct_ap.bill_item_taxes   (bill_item_id,   tax_rate_id, tax_amount)`

> Tax is included in postings only when `tax.company_tax_settings.enabled = TRUE` for the company.

---

## 7) Configure Double‑Entry Posting

Create a posting template per company and doc type.

```sql
-- 7.1 AR template
INSERT INTO acct_post.posting_templates (company_id, doc_type, name)
VALUES (1, 'AR_INVOICE', 'Default AR');

-- Map roles → accounts (replace account ids with your COA)
INSERT INTO acct_post.posting_template_lines (template_id, role, account_id)
SELECT template_id, role, account_id FROM (
  SELECT (SELECT template_id FROM acct_post.posting_templates WHERE company_id=1 AND doc_type='AR_INVOICE') AS template_id,
         *
  FROM (VALUES
    ('AR',        1100),  -- Accounts Receivable
    ('Revenue',   4000),
    ('TaxPayable',2100),
    ('Discount',  4050),
    ('Shipping',  4060)
  ) AS m(role, account_id)
) s;

-- 7.2 AP template
INSERT INTO acct_post.posting_templates (company_id, doc_type, name)
VALUES (1, 'AP_BILL', 'Default AP');

INSERT INTO acct_post.posting_template_lines (template_id, role, account_id)
SELECT template_id, role, account_id FROM (
  SELECT (SELECT template_id FROM acct_post.posting_templates WHERE company_id=1 AND doc_type='AP_BILL') AS template_id,
         *
  FROM (VALUES
    ('AP',            2101), -- Accounts Payable
    ('Expense',       5000),
    ('TaxReceivable', 1300),
    ('Discount',      5090)
  ) AS m(role, account_id)
) s;
```

---

## 8) Working With AR/AP and Posting

### 8.1 Create a customer and invoice (minimal)

```sql
INSERT INTO crm.customers (company_id, name) VALUES (1, 'Contoso Ltd') ON CONFLICT DO NOTHING;

INSERT INTO acct_ar.invoices (company_id, customer_id, invoice_number, invoice_date, due_date, currency_id, subtotal, discount_amount, shipping_amount, total_amount)
VALUES (1, (SELECT customer_id FROM crm.customers WHERE company_id=1 AND name='Contoso Ltd'), 'INV-1001', CURRENT_DATE, CURRENT_DATE + 30,
        (SELECT primary_currency_id FROM core.companies WHERE company_id=1), 1000, 0, 0, 1180); -- total includes expected tax

INSERT INTO acct_ar.invoice_items (invoice_id, description, quantity, unit_price, discount_percentage, discount_amount, line_total)
VALUES ((SELECT invoice_id FROM acct_ar.invoices WHERE company_id=1 AND invoice_number='INV-1001'), 'Consulting', 1, 1000, 0, 0, 1000);

-- Add tax per line (if tax enabled)
INSERT INTO acct_ar.invoice_item_taxes (invoice_item_id, tax_rate_id, tax_amount)
VALUES (
  (SELECT invoice_item_id FROM acct_ar.invoice_items i JOIN acct_ar.invoices v ON v.invoice_id=i.invoice_id WHERE v.invoice_number='INV-1001'),
  (SELECT tax_rate_id FROM tax.tax_rates WHERE company_id=1 AND code='GST-STD'),
  180
);
```

### 8.2 Post the invoice

Manual:

```sql
SELECT acct_post.post_ar_invoice((SELECT invoice_id FROM acct_ar.invoices WHERE invoice_number='INV-1001'),
                                 (SELECT template_id FROM acct_post.posting_templates WHERE company_id=1 AND doc_type='AR_INVOICE'));
```

Auto (via trigger):

```sql
UPDATE acct_ar.invoices SET status='posted' WHERE invoice_number='INV-1001';
```

### 8.3 Verify GL

```sql
-- Find the GL transaction
SELECT t.*
FROM acct.transactions t
WHERE t.reference_type='acct_ar.invoices'
  AND t.reference_id=(SELECT invoice_id FROM acct_ar.invoices WHERE invoice_number='INV-1001');

-- Check journal lines balance
SELECT entry_id, account_id, debit_amount, credit_amount
FROM acct.journal_entries
WHERE transaction_id = (
  SELECT transaction_id FROM acct.transactions WHERE reference_type='acct_ar.invoices' AND reference_id=(SELECT invoice_id FROM acct_ar.invoices WHERE invoice_number='INV-1001')
);
```

### 8.4 Create a vendor bill and post

```sql
INSERT INTO crm.vendors (company_id, name) VALUES (1, 'Fabrikam Supplies') ON CONFLICT DO NOTHING;

INSERT INTO acct_ap.bills (company_id, vendor_id, bill_number, bill_date, due_date, currency_id, subtotal, discount_amount, total_amount)
VALUES (1, (SELECT vendor_id FROM crm.vendors WHERE company_id=1 AND name='Fabrikam Supplies'), 'B-2001', CURRENT_DATE, CURRENT_DATE + 30,
        (SELECT primary_currency_id FROM core.companies WHERE company_id=1), 500, 0, 590);

INSERT INTO acct_ap.bill_items (bill_id, description, quantity, unit_price, discount_percentage, discount_amount, line_total)
VALUES ((SELECT bill_id FROM acct_ap.bills WHERE bill_number='B-2001'), 'Office Supplies', 1, 500, 0, 0, 500);

INSERT INTO acct_ap.bill_item_taxes (bill_item_id, tax_rate_id, tax_amount)
VALUES (
  (SELECT bill_item_id FROM acct_ap.bill_items i JOIN acct_ap.bills b ON b.bill_id=i.bill_id WHERE b.bill_number='B-2001'),
  (SELECT tax_rate_id FROM tax.tax_rates WHERE company_id=1 AND code='GST-STD'),
  90
);

-- Post
SELECT acct_post.post_ap_bill((SELECT bill_id FROM acct_ap.bills WHERE bill_number='B-2001'),
                              (SELECT template_id FROM acct_post.posting_templates WHERE company_id=1 AND doc_type='AP_BILL'));
```

---

## 9) Reconciliation of AR/AP Payments (outline)

* Record payments in `acct_ar.payments` and `acct_ap.payments`.
* Allocate via `payment_allocations` to invoices or bills.
* Import bank lines into `bank.bank_transactions` and match to payments.

---

## 10) Maintenance and Safety

* Idempotent DDL: all files use `IF NOT EXISTS` and guarded `ALTER`s.
* Heavy indexes: create later with `CREATE INDEX CONCURRENTLY` in a separate migration, not inside `BEGIN`.
* Closing periods: set `is_closed = TRUE` on `acct.accounting_periods`. Trigger blocks postings.
* Backups: use `pg_dump -Fc` and WAL archiving for PITR.

Rollback strategy:

* DDL is additive. To revert data changes from a failed post, delete GL rows by `reference_type`+`reference_id` in a transaction.

```sql
BEGIN;
DELETE FROM acct.journal_entries WHERE transaction_id IN (
  SELECT transaction_id FROM acct.transactions WHERE reference_type='acct_ar.invoices' AND reference_id=:invoice_id);
DELETE FROM acct.transactions WHERE reference_type='acct_ar.invoices' AND reference_id=:invoice_id;
COMMIT;
```

---

## 11) Permissions Model (suggested)

```sql
GRANT USAGE ON SCHEMA core, acct, acct_ar, acct_ap, tax, acct_post TO app_user;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA core, acct, acct_ar, acct_ap, tax, acct_post TO app_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA core, acct, acct_ar, acct_ap, tax, acct_post GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO app_user;
```

---

## 12) Common Errors

* **FK missing to tax**: ensure `14_tax.sql` ran before using invoice/bill line taxes.
* **Auto‑post not firing**: check triggers `ar_autopost` and `ap_autopost` exist and status changed from non‑posted to `posted`.
* **Unbalanced transaction**: template missing a role or tax enabled without `TaxPayable`/`TaxReceivable` account.
* **Closed period error**: posting into a closed `acct.accounting_periods` row.

---

## 13) CI/CD Tips

* Run `psql -v ON_ERROR_STOP=1` in pipelines.
* After migration, run a health query that returns zero rows if OK:

```sql
SELECT t.transaction_id FROM acct.transactions t
LEFT JOIN (
  SELECT transaction_id, SUM(debit_amount) d, SUM(credit_amount) c
  FROM acct.journal_entries GROUP BY 1
) s USING (transaction_id)
WHERE COALESCE(d,0) <> COALESCE(total_debit,0)
   OR COALESCE(c,0) <> COALESCE(total_credit,0);
```

---

## 14) Performance Tuning (scale guidance for DBAs)

### 14.1 General settings (per cluster)

* Enable `pg_stat_statements` and track normalised query patterns.
* Size `shared_buffers` \~25% RAM, `effective_cache_size` \~50–75% RAM.
* Start with `work_mem` 4–32MB per sort/hash, raise in controlled workloads.
* Set `maintenance_work_mem` 512MB–2GB for VACUUM/CREATE INDEX on large tables.

### 14.2 VACUUM/ANALYZE

Create aggressive autovacuum for heavy‑write tables (`acct.journal_entries`, `acct.transactions`, `acct_ar.payment_allocations`, `acct_ap.payment_allocations`, `bank.bank_transactions`).

```sql
ALTER TABLE acct.journal_entries SET (
  autovacuum_vacuum_scale_factor = 0.05,
  autovacuum_analyze_scale_factor = 0.05,
  autovacuum_vacuum_cost_limit = 400,
  autovacuum_vacuum_cost_delay = 2
);
ALTER TABLE acct.transactions SET (
  autovacuum_vacuum_scale_factor = 0.05,
  autovacuum_analyze_scale_factor = 0.05
);
```

Manual cadence for analytics/reporting tables after bulk loads:

```sql
VACUUM (ANALYZE) rpt.financial_statements;
```

### 14.3 Index maintenance

* Prefer **covering indexes** for common filters: `(company_id, date)` or `(company_id, status)`.
* Rebuild bloated indexes with `REINDEX CONCURRENTLY` off‑peak.
* Add partial indexes for active subsets, e.g. `WHERE status IN ('posted','completed')`.

Examples:

```sql
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_tx_company_date ON acct.transactions(company_id, transaction_date);
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_inv_status_open ON acct_ar.invoices(company_id, status) WHERE status IN ('sent','posted');
```

### 14.4 Partitioning

Use **declarative range partitioning** by month for very large facts.

```sql
-- Parent
ALTER TABLE acct.transactions PARTITION BY RANGE (transaction_date);
-- Example partitions
CREATE TABLE acct.transactions_2025_01 PARTITION OF acct.transactions
  FOR VALUES FROM ('2025-01-01') TO ('2025-02-01');
CREATE TABLE acct.transactions_2025_02 PARTITION OF acct.transactions
  FOR VALUES FROM ('2025-02-01') TO ('2025-03-01');
-- Default catch‑all
CREATE TABLE acct.transactions_default PARTITION OF acct.transactions DEFAULT;
```

Mirror partitioning on `acct.journal_entries` by referencing the same date via `transactions.transaction_date` at insert time (handled in app or via trigger). Create local indexes per partition.

### 14.5 Query plans and hints

* Always parameterise tenant: `WHERE company_id = $1`.
* Avoid `SELECT *` in hot paths. Project needed columns only.
* Use `EXPLAIN (ANALYZE, BUFFERS)` on slow queries; add indexes accordingly.

### 14.6 Connection and workload

* Use a pooler (e.g. pgbouncer) with transaction pooling for web workloads.
* Keep transactions short; commit early to reduce row version churn.

### 14.7 Monitoring

* Track: autovacuum activity, dead tuples, index bloat, long‑running queries.
* Sample bloat check (approximate):

```sql
SELECT schemaname, relname, pg_size_pretty(pg_total_relation_size(relid)) AS total_size
FROM pg_stat_user_tables
ORDER BY pg_total_relation_size(relid) DESC
LIMIT 20;
```

* Expose key metrics via `pg_stat_statements` and your APM.

---

This guide provides the exact steps to deploy, enable tax, configure posting, run routines, and scale PostgreSQL safely. Apply with your company IDs, account IDs, and jurisdiction codes as needed.
