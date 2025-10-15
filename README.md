# App Deployment Guide

## 1. SME Modular Accounting — Deployment, Operations, and Expansion Guide (PostgreSQL)

## 0. Scope

Covers deployment, table purposes, usage patterns, operations, and expansion for all modules:
`00_core`, `10_acct`, `11_ar`, `12_ap`, `13_bank`, `14_tax`, `15_posting`, `20_inv`, `21_inventory_costing`, `30_reporting`, `31_fin_reports`, `40_crm`, `50_payroll`, `51_payroll_extended`, `60_vms`, `90_sys`, `91_webhook_deliveries`, `92_failed_jobs`, `93_schema_version`.

---

## 1. Architecture and Tenancy

* **Module-based schemas.** Each domain lives in its own schema for isolation and optionality.
* **Logical multi-tenancy.** Every business table carries `company_id` with tenant-scoped unique keys like `(company_id, number)`.
* **Optional cross-module FKs.** Added conditionally via `DO $$` blocks so you can deploy subsets without errors.

**Do not** create a separate PostgreSQL schema per company unless you need hard isolation. It complicates migrations, pooling, and cross-tenant reporting. Prefer one set of module schemas with `company_id` filters.

---

## 2. Versions, Roles, and Cluster Prep

* PostgreSQL 14+.
* Enable `pg_stat_statements` for monitoring.
* Create roles:

```sql
CREATE ROLE app_owner LOGIN PASSWORD '***';
CREATE ROLE app_user  LOGIN PASSWORD '***';
GRANT app_owner TO app_user; -- temporary during bootstrap only
```

* After bootstrap, revoke ownership from `app_user`. Grant runtime privileges only.

---

## 3. Files and Load Order

```
00_core.sql
10_accounting.sql
11_ar.sql
12_ap.sql
13_bank.sql
14_tax.sql
15_posting.sql
20_inventory.sql
21_inventory_costing.sql
30_reporting.sql
31_fin_reports.sql
40_crm.sql
50_payroll.sql
51_payroll_extended.sql
60_vms.sql
90_system.sql
91_webhook_deliveries.sql
92_failed_jobs.sql
93_schema_version.sql
```

Deploy with `deploy.sh` or manually using `psql -v ON_ERROR_STOP=1 -f <file>` in the above order.

---

## 4. Post-Install Verification

```sql
-- Schemas present
SELECT nspname FROM pg_namespace
WHERE nspname IN ('core','acct','acct_ar','acct_ap','bank','tax','acct_post','inv','rpt','crm','pay','vms','sys');

-- Critical triggers present
SELECT tgname, tgrelid::regclass
FROM pg_trigger
WHERE NOT tgisinternal
  AND tgname IN ('ar_autopost','ap_autopost','payslip_lines_rollup_aiud','stock_movements_aiud_cost_wa');
```

---

## 5. Tenant Onboarding

1. Create company and first admin.

```sql
INSERT INTO core.companies(name, primary_currency_id, fiscal_year_start_month, schema_name)
VALUES ('Acme Travel', 1, 1, 'acme');

INSERT INTO core.user_accounts(company_id, username, email, password_hash, first_name, last_name)
VALUES (1,'admin','admin@acme.test','bcrypt$...','Sys','Admin');
```

2. Fiscal year and periods.

```sql
INSERT INTO acct.fiscal_years(company_id,name,start_date,end_date,is_current)
VALUES (1,'FY2025','2025-01-01','2025-12-31',true);

INSERT INTO acct.accounting_periods(company_id,fiscal_year_id,name,start_date,end_date)
SELECT 1, fiscal_year_id, to_char(d,'Mon YYYY'),
       date_trunc('month',d)::date,
       (date_trunc('month',d) + interval '1 month - 1 day')::date
FROM acct.fiscal_years fy
CROSS JOIN generate_series('2025-01-01','2025-12-01','1 month') d
WHERE fy.company_id=1 AND fy.name='FY2025';
```

3. Chart of accounts seed (import your COA).

---

## 6. Module Deep-Dive: Tables and How To Use Them

### 6.1 `core` — Tenancy and shared refs

* `core.companies` — one row per tenant. Holds primary currency and fiscal settings.
* `core.user_accounts` — application users. Join on `company_id` for tenancy.
* `core.countries`, `core.currencies`, `core.exchange_rates` — dimension data for docs and reporting.

**Use:** always include `company_id` in inserts and filters.

---

### 6.2 `acct` — General Ledger

* `acct.chart_of_accounts` — account tree. Fields: `account_code`, `account_type` (asset, liability, equity, revenue, expense), `balance_type` (debit/credit).
* `acct.fiscal_years`, `acct.accounting_periods` — calendar control. `is_closed` blocks postings.
* `acct.transactions` — header per GL document. Stores type, dates, totals.
* `acct.journal_entries` — lines per account with `debit_amount` or `credit_amount`.

**Use:**

* Manual journal:

```sql
INSERT INTO acct.transactions(company_id,transaction_number,transaction_type,transaction_date,description,currency_id)
VALUES (1,'GJ-0001','journal',CURRENT_DATE,'Year open',(SELECT primary_currency_id FROM core.companies WHERE company_id=1))
RETURNING transaction_id;

INSERT INTO acct.journal_entries(transaction_id,account_id,debit_amount,description) VALUES (:tx,1000,100,'Open');
INSERT INTO acct.journal_entries(transaction_id,account_id,credit_amount,description) VALUES (:tx,3000,100,'Open');
```

---

### 6.3 `acct_ar` — Accounts Receivable

* `invoices`, `invoice_items`, `invoice_item_taxes` — sales docs and line taxes.
* `payments` — cash received.
* `payment_allocations` — links payment to invoices; never exceed invoice total.

**Use:** create invoice → optional taxes → post to GL (via `15_posting`) → receive payment → allocate.

---

### 6.4 `acct_ap` — Accounts Payable

* `bills`, `bill_items`, `bill_item_taxes` — vendor bills and taxes.
* `payments`, `payment_allocations` — outflows and allocation to bills.

**Use:** enter bill → post to GL → pay vendor → allocate.

---

### 6.5 `bank` — Banking

* `bank_accounts` — per company bank ledgers. Optional FK to GL cash account.
* `bank_transactions` — imported statement lines or recorded bank activity.
* `reconciliations` — reconciliation sessions.

**Use:** import lines, match to AR/AP payments, reconcile.

---

### 6.6 `tax` — Jurisdictions and rates

* `jurisdictions` — country or sub-region codes.
* `company_tax_settings` — feature toggle, price-includes-tax, rounding.
* `tax_rates` — rate and validity windows.
* `tax_groups`, `tax_group_components` — composite taxes.
* `company_tax_registrations`, `tax_exemptions` — compliance metadata.

**Use:** enable for a company and attach `tax_rate_id` per AR/AP line. Posting includes tax only when enabled.

---

### 6.7 `acct_post` — Auto-posting to GL

* `posting_templates` — per company, per doc type (`AR_INVOICE`, `AP_BILL`).
* `posting_template_lines` — map roles to GL accounts: AR/AP control, Revenue, Expense, Tax, Discount, Shipping.
* Functions:
  `acct_post.post_ar_invoice(invoice_id, template_id)`
  `acct_post.post_ap_bill(bill_id, template_id)`
* Triggers: `ar_autopost` and `ap_autopost` post on `status='posted'`.

**Use:**

```sql
-- template
INSERT INTO acct_post.posting_templates(company_id,doc_type,name) VALUES (1,'AR_INVOICE','Default');
INSERT INTO acct_post.posting_template_lines(template_id,role,account_id)
VALUES ((SELECT template_id FROM acct_post.posting_templates WHERE company_id=1 AND doc_type='AR_INVOICE'),
        'AR',1100),('Revenue',4000),('TaxPayable',2100),('Discount',4050);

-- manual post
SELECT acct_post.post_ar_invoice(:invoice_id,
  (SELECT template_id FROM acct_post.posting_templates WHERE company_id=1 AND doc_type='AR_INVOICE'));
```

Common errors: tax enabled but `TaxPayable` missing; closed period.

---

### 6.8 `inv` — Inventory

* `item_categories`, `items` — SKUs and attributes.
* `warehouses` — locations.
* `stock_levels` — snapshot quantity per `(warehouse,item)`.
* `stock_movements` — in, out, transfer, adjustment. Optional links to AR/AP.

**Use:** write a movement row for each change. Keep unit cost for inbound movements.

---

### 6.9 `21_inventory_costing` — Costing and COGS

* `cost_policies` — WA or FIFO per company.
* `item_costs` — moving average and values per `(company,item,warehouse)`.
* `cost_layers`, `layer_consumptions` — FIFO scaffolding.
* `cogs_entries` — COGS per stock issue.
* Trigger `inv.trg_costing_wa` — maintains averages and creates COGS on outbound.

**Use:** set policy to `WA` to activate trigger. For FIFO, build layers in app jobs.

---

### 6.10 `rpt` — Reporting metadata and snapshots

* `report_templates` — custom SQL templates and parameter definitions.
* `reports` — generated files with parameters.
* `financial_statements` — JSON snapshots of TB, BS, PL. Auditable.

**Use:** store generated output and expose to UI for downloads and audit.

---

### 6.11 `31_fin_reports` — Financial statement functions

* Functions:
  `rpt.trial_balance(company_id, start_date, end_date)`
  `rpt.profit_and_loss(company_id, start_date, end_date)`
  `rpt.balance_sheet(company_id, as_of)`
* Views: `v_trial_balance_current`, `v_profit_and_loss_current`, `v_balance_sheet_today`.
* Snapshot helper: `rpt.snapshot_trial_balance(company_id, period_id)` → writes to `rpt.financial_statements`.

**Use:** backend calls functions for live pages; cache heavy periods with snapshots.

---

### 6.12 `crm` — Master data

* `customers`, `vendors` — parties.
* `contacts`, `interactions` — people and activity history.

**Use:** link AR to customers and AP to vendors via conditional FKs.

---

### 6.13 `pay` — Payroll core

* `employees` — staff master.
* `payroll_periods`, `payroll_runs` — pay cycle control.
* `payroll_details`, `payroll_deductions` — run outcomes.
* `payroll_gl_mappings` — optional GL mapping table.

---

### 6.14 `51_payroll_extended` — Leave, benefits, payslips

* `earning_types`, `deduction_types` — normalized codes.
* `benefit_plans`, `employee_benefits` — contributions.
* `leave_types`, `leave_requests` — entitlement and requests.
* `payslips`, `payslip_lines` — calculated payslips. Trigger rolls up totals.

**Use:** generate payslips per period, then post to GL with your template mapping.

---

### 6.15 `vms` — Visitor Management (travel vertical)

* `groups` — group trips.
* `visitors` — travelers.
* `services` — visas, hotels, flights, tours.
* `bookings`, `booking_items` — order header and lines; optional link to AR invoices.
* `vouchers`, `itineraries`, `itinerary_items` — documents and daily plans.

**Use:** build bookings, convert to AR invoices if needed.

---

### 6.16 `sys` — Platform utilities

* `settings` — per-company configuration key/value.
* `api_keys` — tokens and scopes.
* `webhooks` — event endpoints.
* `audit_log` — coarse audit trail.
* `jobs` — general purpose job queue.
* `notifications`, `error_log` — user notices and errors.

---

### 6.17 `91_webhook_deliveries`

* `webhook_deliveries` — delivery header with status and counters.
* `webhook_delivery_events` — per attempt details.
* Function `sys.log_webhook_attempt(...)` — append and roll up.

**Use:** worker scans pending, posts, records attempt, schedules retries.

---

### 6.18 `92_failed_jobs`

* `failed_jobs` — persistent record of failures with payloads.
* Functions: `sys.log_failed_job`, `sys.retry_failed_job`, `sys.resolve_failed_job`.

**Use:** build dashboards and retry flows.

---

### 6.19 `93_schema_version`

* `schema_versions` — registry of applied modules.
* Function `sys.register_migration(...)` — idempotent upsert.

**Use:** record every migration in CI/CD.

---

## 7. Enabling Tax and Auto-Posting

Enable tax for a company:

```sql
INSERT INTO tax.jurisdictions(country_id,code,name)
VALUES ((SELECT country_id FROM core.countries WHERE code='PAK'),'PK','Pakistan')
ON CONFLICT DO NOTHING;

INSERT INTO tax.company_tax_settings(company_id,enabled,default_jurisdiction_id,price_includes_tax,rounding_mode)
VALUES (1,true,(SELECT jurisdiction_id FROM tax.jurisdictions WHERE code='PK'),false,'half_up')
ON CONFLICT (company_id) DO UPDATE SET enabled=EXCLUDED.enabled, default_jurisdiction_id=EXCLUDED.default_jurisdiction_id;
```

Create posting templates and post as in 6.7.

---

## 8. Reporting Execution Patterns

### 8.1 Live compute

Backend calls the `rpt.*` functions with parameters. Paginate in code.

### 8.2 Snapshot on close

Call `rpt.snapshot_trial_balance(company_id, period_id)` on period close. Display `rpt.financial_statements` in UI.

### 8.3 Materialization (optional)

Create materialized views for fixed windows and refresh nightly.

---

## 9. Operational Runbooks

### 9.1 Month-end close

* Verify unbalanced transactions query returns zero.
* Set `is_closed=true` on prior periods.
* Snapshot TB and store in `rpt.financial_statements`.

### 9.2 Bank reconciliation

* Load `bank.bank_transactions`.
* Match to AR/AP payments.
* Mark reconciliation complete.

### 9.3 Costing

* For WA, confirm `inv.cost_policies.method='WA'`.
* Monitor `inv.cogs_entries` vs negative movements.

### 9.4 Webhook worker

* Pull pending rows from `sys.webhook_deliveries` by `status='pending'` and `next_attempt_at`.
* Deliver. Call `sys.log_webhook_attempt(...)`.
* Reschedule with exponential backoff.

### 9.5 Failed job sweeper

* Dashboard by `queue`, `job_type`, `status`.
* Call `sys.retry_failed_job(job_id, NOW() + interval '5 min')`.

---

## 10. Performance and Monitoring

### 10.1 Autovacuum tuning (heavy tables)

```sql
ALTER TABLE acct.journal_entries SET (
  autovacuum_vacuum_scale_factor=0.05,
  autovacuum_analyze_scale_factor=0.05
);
ALTER TABLE acct.transactions SET (
  autovacuum_vacuum_scale_factor=0.05,
  autovacuum_analyze_scale_factor=0.05
);
```

### 10.2 Indexing patterns

* `(company_id, status)`, `(company_id, transaction_date)`.
* Partial indexes for hot statuses.
* `REINDEX CONCURRENTLY` off-peak for bloat.

### 10.3 Partitioning

* Range partition `acct.transactions` and related facts by month when sizes warrant.

### 10.4 Pooling

* Use pgbouncer transaction pooling. Keep transactions short.

---

## 11. Security and Access

* Grant `USAGE` on module schemas to app roles.
* Grant CRUD on tables as needed. Use default privileges for future tables.
* Enforce `company_id` filters in all queries. Add unit tests that assert tenant isolation.

---

## 12. Backups and DR

* Nightly `pg_dump -Fc` per database.
* WAL archiving for PITR.
* Restore drills: verify trigger presence and health queries post-restore.

---

## 13. CI/CD

* Run migrations with `ON_ERROR_STOP=1`.
* Register each module version in `sys.schema_versions`.
* Health check query:

```sql
SELECT t.transaction_id
FROM acct.transactions t
LEFT JOIN (
  SELECT transaction_id, SUM(debit_amount) d, SUM(credit_amount) c
  FROM acct.journal_entries GROUP BY 1
) s USING (transaction_id)
WHERE COALESCE(d,0)<>COALESCE(total_debit,0)
   OR COALESCE(c,0)<>COALESCE(total_credit,0);
```

---

## 14. Future Expansions

### Accounting

* **Credit notes**: AR/AP reversal doc types and posting roles.
* **Accruals and deferrals**: schedule-based postings.
* **Multi-currency remeasurement**: unrealized FX at period end.

### Inventory

* **FIFO engine**: job to maintain `cost_layers` and consume on outbound.
* **GL hooks**: inventory and COGS postings on AR/AP events.

### Reporting

* **Materialized TB/BS/PL**: by month and company.
* **Cash-flow**: indirect method function using GL activity.

### System

* **Bank imports**: `13_bank_imports.sql` for formats and matching rules.
* **Data retention**: purge policies for logs and jobs.
* **Fine-grained audit**: row-level audit triggers.

### Travel vertical

* **61\_vms\_catalog**: hotels, airlines, cities, vendors.
* **62\_supplier\_contracts**: rate cards and seasons.

---

## 15. Appendix: Common API-SQL Bridges

### Reports

* TB endpoint calls `rpt.trial_balance`.
* PL endpoint calls `rpt.profit_and_loss`.
* BS endpoint calls `rpt.balance_sheet`.

### Posting

* Set invoice status to `posted` to trigger autopost.
* Or call `acct_post.post_ar_invoice` / `post_ap_bill` directly.

### Payslips

* Insert lines, totals roll up automatically. Transition to `approved`, then record payment in AP or bank.

---

This guide gives deployment, table purposes, how to use each table set effectively, operations, and expansion paths. Financial reports are computed in the backend via `31_fin_reports.sql` functions and can be snapshotted in `rpt.financial_statements`.

## 2. API

The backend exposes a REST-style API for interacting with the system.

* `POST /api/v1/auth/login` – authenticate and receive an access token.
* `GET  /api/v1/status` – simple health check.
* `GET  /api/v1/users` – list existing users (authentication required).

### Payment Processing & Batch Operations

* `POST /api/accounting/payment-batches` – create and process payment batches from CSV or manual entries
* `GET  /api/accounting/payment-batches/{id}` – get batch status and processing progress
* `GET  /api/accounting/payment-batches` – list batches with filtering options
* `POST /api/payments/{id}/allocations` – allocate payments to invoices with multiple strategies
* `GET  /api/payments/{id}/receipt` – generate payment receipts in JSON or PDF format

### Key Features

- **Batch Processing**: Bulk payment processing from CSV files, manual entries, or bank feeds
- **Auto-Allocation**: Intelligent payment allocation across customer invoices (FIFO, proportional, overdue_first, etc.)
- **Real-time Monitoring**: Live batch processing status with progress tracking and error reporting
- **Idempotent Operations**: Safe retry mechanisms with idempotency keys
- **Multi-tenant Support**: Row-level security ensures complete tenant isolation

Additional routes and sample requests are available in the API documentation within the `docs/` directory:
- [Payment Allocations API Guide](./docs/api-allocation-guide.md)
- [Batch Processing Quick Start](./docs/payment-batch-quickstart.md)

## 3. Laravel Setup and initialization

1. **Environment**
   - Copy `.env.example` to `.env`.
   - Configure database credentials and other environment variables.
   - Set `DEV_CONSOLE_ENABLED=true` in `.env` to enable the development CLI when needed.
   - Install dependencies with `composer install`.
   - Generate the application key: `php artisan key:generate`.
2. **Migrations**
   - Run database migrations: `php artisan migrate`.
3. **Seed Data**
   - Populate reference data and defaults: `php artisan db:seed`.
## Contributing (MVP Phase)

- Read team priorities in `docs/TEAM_MEMORY.md`.
- Use the PR checklist in `.github/pull_request_template.md` to keep changes MVP‑focused, use Reka UI components, and ensure CLI freeform parity for guided features.

