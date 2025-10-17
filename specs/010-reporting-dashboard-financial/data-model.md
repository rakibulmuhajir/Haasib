# Data Model – Reporting Dashboard (Financial & KPI)

## Schema Overview

- `rpt.report_templates`: Saved report definitions, filters, and layout metadata for financial statements, KPIs, and dashboards (per `docs/schemas/30_reporting.sql`).
- `rpt.reports`: Execution log + cached payload for generated reports (trial balance, statements, exports) with retention/expiry controls.
- `rpt.financial_statements`: Auditable statement snapshots tied to fiscal periods with JSON column payloads for rows/columns and comparative data.
- `rpt.financial_statement_lines`: Denormalized rows for drill-down support, linked to source `ledger.journal_lines` and `acct.*` documents.
- `rpt.kpi_definitions`: Configurable KPI formulas, thresholds, and visualization metadata for dashboard cards.
- `rpt.kpi_snapshots`: Time-series store of computed KPI values keyed by company, period granularity, and definition.
- `rpt.dashboard_layouts`: User/company scoped dashboard presets (card ordering, sizing, filters).
- `rpt.report_schedules`: Saved schedules for recurring report generation and distribution.
- `rpt.report_deliveries`: Delivery log (email/export) including status, recipients, and generated artifact references.
- Materialized views in `rpt` (e.g., `mv_trial_balance_current`, `mv_income_statement_monthly`, `mv_budget_vs_actual`) feed dashboards with <5s freshness; refreshed via command bus jobs.
- Cross-schema dependencies: reads from `ledger.journal_entries`/`journal_lines`, `acct.customers`, `acct.invoices`, `acct.payments`, `ops.bank_statement_lines`, `public.exchange_rates`, and reference data in `auth.*` for companies/users. All writes remain inside `rpt` to satisfy Multi-Schema Domain Separation.

## Entities

### ReportTemplate (`rpt.report_templates`)
- **Fields**
  - `template_id` (bigserial PK)
  - `company_id` (uuid FK → `auth.companies.id`, RLS scope)
  - `name` (varchar 255, unique per company)
  - `description` (text, nullable)
  - `report_type` (enum: `income_statement`, `balance_sheet`, `cash_flow`, `trial_balance`, `kpi_dashboard`, `custom`)
  - `category` (enum: `financial`, `operational`, `analytical`)
  - `configuration` (jsonb) — column groupings, row ordering, KPI card definitions
  - `filters` (jsonb) — default dimension/date filters
  - `parameters` (jsonb) — saved comparison periods, currency override, etc.
  - `is_system_template` (boolean, default false)
  - `is_public` (boolean, default false) — visible to all users with access
  - `sort_order` (integer, default 0)
  - `created_by` / `updated_by` (uuid FK → `auth.users.id`)
  - `created_at` / `updated_at` (timestamptz)
- **Relationships**
  - Has many `ReportRun` (`rpt.reports`) via `template_id`
  - Referenced by `ReportSchedule`
- **Validation Rules**
  - `name` unique within (`company_id`, `report_type`)
  - `configuration` must include schema version + visualization metadata for KPI dashboards
  - `is_system_template` only true for Haasib-provided templates (guarded by permission)

### ReportRun (`rpt.reports`)
- **Fields**
  - `report_id` (bigserial PK)
  - `company_id` (uuid FK)
  - `template_id` (bigint FK → `rpt.report_templates.template_id`, nullable for ad-hoc runs)
  - `report_type` (enum synchronized with templates)
  - `name` (varchar 255)
  - `parameters` (jsonb) — resolved runtime params (date range, segments, currency)
  - `filters` (jsonb) — normalized filters actually applied
  - `date_range_start` / `date_range_end` (date, nullable)
  - `status` (enum: `queued`, `running`, `generated`, `failed`, `expired`)
  - `payload` (jsonb, nullable) — computed data for dashboards/exports (when not file-backed)
  - `file_path` (varchar 500, nullable) — storage path for PDF/Excel exports
  - `file_size` (bigint, nullable)
  - `mime_type` (varchar 100, nullable)
  - `generated_at` (timestamptz, nullable)
  - `expires_at` (timestamptz, nullable)
  - `created_by` (uuid FK → `auth.users.id`)
  - `created_at` / `updated_at`
- **Relationships**
  - Belongs to `ReportTemplate`
  - Has many `ReportDelivery` records
  - Links to `FinancialStatement` snapshots when `report_type` is statement-related
- **State Transitions**
  ```
  queued → running → generated → expired
             ↘ failed
  ```
  - Transition to `generated` requires persisted payload/file metadata.
  - `expired` is set by scheduled cleanup once `expires_at` is reached.
- **Validation Rules**
  - `status` transitions enforced via command bus using transactional middleware.
  - `file_path` required for export MIME types (`pdf`, `xlsx`, `csv`).
  - `expires_at` default 30 days unless overridden by retention policy.

### FinancialStatement (`rpt.financial_statements`)
- **Fields**
  - `statement_id` (bigserial PK)
  - `company_id` (uuid FK)
  - `fiscal_year_id` (uuid FK → `acct.fiscal_years.id`)
  - `period_id` (uuid FK → `acct.accounting_periods.id`, nullable for YTD)
  - `statement_type` (enum: `balance_sheet`, `income_statement`, `cash_flow`, `equity`)
  - `name` (varchar 255)
  - `statement_date` (date)
  - `date_range_start` / `date_range_end` (date)
  - `data` (jsonb) — hierarchical representation (sections, rows, totals)
  - `totals` (jsonb) — aggregated values for quick access
  - `comparative_data` (jsonb) — prior period/year metrics
  - `currency` (char(3))
  - `exchange_rate_snapshot` (jsonb) — rates used when converting multi-currency figures
  - `notes` (text, nullable)
  - `status` (enum: `draft`, `finalized`, `published`)
  - `version` (integer default 1, increment when regenerated post-finalization)
  - `created_by` / `updated_by` / `finalized_by` (uuid FK → `auth.users.id`)
  - `created_at` / `updated_at` / `finalized_at`
- **Relationships**
  - Has many `FinancialStatementLine`
  - Linked to `ReportRun` (one-to-one optional) for traceability
- **Validation Rules**
  - `statement_type` + `period_id` unique per company + version
  - `currency` must equal company base currency unless explicitly converted (requires recorded rate)
  - Transition to `finalized` requires balanced totals (assets = liabilities + equity, etc.)

### FinancialStatementLine (`rpt.financial_statement_lines`)
- **Fields**
  - `line_id` (bigserial PK)
  - `statement_id` (bigint FK → `rpt.financial_statements.statement_id`)
  - `company_id` (uuid)
  - `section` (varchar 100) — e.g., `Revenue`, `Operating Expenses`
  - `display_order` (integer)
  - `account_code` (varchar 50, nullable) — when tied to chart of accounts
  - `label` (varchar 255)
  - `amount` (numeric(20,4))
  - `currency` (char(3))
  - `comparative_amount` (numeric(20,4), nullable)
  - `variance_amount` / `variance_percent` (numeric, nullable)
  - `source_reference_type` (varchar 100, nullable) — e.g., `ledger.journal_lines`
  - `source_reference_id` (uuid, nullable)
  - `drilldown_context` (jsonb) — filters to fetch detailed transactions
  - `created_at` / `updated_at`
- **Relationships**
  - Belongs to `FinancialStatement`
  - Indirectly references `ledger.journal_lines`, `acct.invoices`, etc. via `source_reference_type`
- **Validation Rules**
  - `display_order` continuous per (`statement_id`, `section`)
  - `amount` precision validated at DB level
  - `source_reference_type` value constrained to whitelisted tables for drill-down security

### KpiDefinition (`rpt.kpi_definitions`)
- **Fields**
  - `kpi_id` (uuid PK, default `gen_random_uuid()`)
  - `company_id` (uuid FK, nullable when `is_global = true`)
  - `code` (varchar 64, unique per company/global) — e.g., `dso`, `cash_runway`
  - `name` (varchar 255)
  - `description` (text, nullable)
  - `formula` (jsonb) — expression tree referencing metric builders (e.g., `calc.dso(lookback:30)`)
  - `visual_type` (enum: `stat`, `trend`, `chart`, `gauge`)
  - `value_format` (enum: `currency`, `percentage`, `days`, `number`)
  - `thresholds` (jsonb, nullable) — target bands for coloring/alerts
  - `default_granularity` (enum: `daily`, `weekly`, `monthly`)
  - `allow_drilldown` (boolean)
  - `is_global` (boolean default false)
  - `created_by` / `updated_by` (uuid FK)
  - `created_at` / `updated_at`
- **Relationships**
  - Has many `KpiSnapshot`
  - Included in `dashboard_layouts.cards`
- **Validation Rules**
  - `formula` validated by expression compiler; must reference approved metric functions
  - Company-specific KPIs cannot set `is_global = true`

### KpiSnapshot (`rpt.kpi_snapshots`)
- **Fields**
  - `snapshot_id` (bigserial PK)
  - `company_id` (uuid FK)
  - `kpi_id` (uuid FK → `rpt.kpi_definitions.kpi_id`)
  - `captured_at` (timestamptz) — time of computation
  - `period_start` / `period_end` (date)
  - `granularity` (enum: `intraday`, `daily`, `monthly`)
  - `value` (numeric(20,4))
  - `currency` (char(3), nullable) — present when `value_format = currency`
  - `comparison_value` (numeric(20,4), nullable)
  - `variance_percent` (numeric(10,4), nullable)
  - `meta` (jsonb) — sample data points for trendline
  - `created_at` (timestamptz default now())
- **Relationships**
  - Belongs to `KpiDefinition`
- **Validation Rules**
  - Unique (`kpi_id`, `period_start`, `period_end`, `granularity`)
  - `currency` required when KPI format = currency

### DashboardLayout (`rpt.dashboard_layouts`)
- **Fields**
  - `layout_id` (uuid PK)
  - `company_id` (uuid FK)
  - `owner_id` (uuid FK → `auth.users.id`, nullable when shared)
  - `name` (varchar 150)
  - `is_default` (boolean)
  - `visibility` (enum: `private`, `company`, `role`)
  - `applies_to_roles` (jsonb, nullable) — list of Spatie role names (e.g., `['Owner','Accountant']`)
  - `cards` (jsonb) — layout metadata: card ids, sizing, queries, refresh intervals
  - `filters` (jsonb) — saved filters (date range, segment, entity selection)
  - `created_by` / `updated_by` (uuid)
  - `created_at` / `updated_at`
- **Relationships**
  - Uses `KpiDefinition` codes, `ReportTemplate` IDs inside `cards` JSON
- **Validation Rules**
  - Exactly one `is_default = true` per (`company_id`, `role` scope)
  - `cards` JSON validated via schema (card type must exist, references must resolve)

### ReportSchedule (`rpt.report_schedules`)
- **Fields**
  - `schedule_id` (uuid PK)
  - `company_id` (uuid FK)
  - `template_id` (bigint FK → `rpt.report_templates.template_id`)
  - `name` (varchar 255)
  - `frequency` (enum: `daily`, `weekly`, `monthly`, `quarterly`, `yearly`, `custom`)
  - `custom_cron` (varchar 100, nullable when frequency ≠ `custom`)
  - `next_run_at` (timestamptz)
  - `last_run_at` (timestamptz, nullable)
  - `timezone` (varchar 50, default company timezone)
  - `parameters` (jsonb) — overrides for each run (e.g., relative date window)
  - `delivery_channels` (jsonb) — e.g., email recipients, S3 bucket path
  - `status` (enum: `active`, `paused`, `archived`)
  - `created_by` / `updated_by` (uuid)
  - `created_at` / `updated_at`
- **Relationships**
  - Has many `ReportDelivery`
  - Triggers `ReportRun` creation via scheduled command bus job
- **Validation Rules**
  - `next_run_at` recalculated after every successful run; must be > `now()`
  - `custom_cron` required when frequency = `custom`
  - Delivery emails validated against company membership/allowed recipients list

### ReportDelivery (`rpt.report_deliveries`)
- **Fields**
  - `delivery_id` (bigserial PK)
  - `company_id` (uuid FK)
  - `schedule_id` (uuid FK → `rpt.report_schedules.schedule_id`, nullable for ad-hoc sends)
  - `report_id` (bigint FK → `rpt.reports.report_id`)
  - `channel` (enum: `email`, `sftp`, `webhook`, `in_app`)
  - `target` (jsonb) — e.g., email list, endpoint URL
  - `status` (enum: `pending`, `sent`, `failed`, `retried`)
  - `sent_at` (timestamptz, nullable)
  - `failure_reason` (text, nullable)
  - `created_at` / `updated_at`
- **Relationships**
  - Belongs to `ReportRun`
  - Belongs to `ReportSchedule`
- **Validation Rules**
  - `status` transitions recorded with audit log; `failed` entries trigger retry workflow
  - `channel=webhook` requires signing secret stored in `target` payload (encrypted)

## Views & Materialized Views

- `rpt.mv_trial_balance_current`: Aggregates `ledger.journal_lines` for current open period per company; includes columns for account hierarchy, currency, and balance. Refresh policy: on-demand (pre-refresh queue) + nightly full rebuild.
- `rpt.mv_income_statement_monthly`: Summarizes revenue/expense accounts per month using `ledger.journal_entries`, including comparative columns. Refresh on schedule after posting jobs & when command bus invalidates caches.
- `rpt.mv_cash_flow_daily`: Derives operating/investing/financing cash movement by mapping ledger accounts.
- `rpt.mv_budget_vs_actual`: Joins actual balances with budget targets (once budget tables delivered) to satisfy FR-011.
- `rpt.v_transaction_drilldown`: Parameterized view powering drill-down, ensuring RLS by joining to `auth.company_user` and using `current_setting('app.current_company_id')`.

## Data Integrity & Business Rules

1. **Tenant Isolation**: Every `rpt.*` table enforces row-level security keyed by `company_id`; refresh jobs set `app.current_company_id` before touching data (Constitution II).
2. **Currency Consistency**: When a report uses non-base currency, the exchange rates captured in `exchange_rate_snapshot` must reference `public.exchange_rates` entries for the report date.
3. **Freshness SLA**: Dashboard queries hit materialized views but also populate 5s TTL cache entries (`cache:reporting:company:{id}:widget:{hash}`). Cache invalidation occurs on new postings, imports, or manual refresh.
4. **Auditability**: `ReportRun`, `FinancialStatement`, and `ReportDelivery` mutations emit `audit_log()` entries with context (report type, parameters, user) to satisfy Constitution IV.
5. **Permissions**: Access controlled via Spatie abilities (`reporting.dashboard.view`, `reporting.reports.generate`, `reporting.reports.schedule`, `reporting.reports.export`). Row-level filtering ensures Viewer role cannot access templates marked `visibility = role` without membership.
6. **Performance Guardrails**: Reports limited to 24-month spans unless the command bus job enqueues background generation; synchronous dashboard calls enforce record caps (lazy-loading for >10k rows per FR edge case).
7. **Scheduling Idempotency**: `ReportSchedule` jobs store a `command_bus` idempotency key (`reporting:schedule:{schedule_id}:{run_date}`) to avoid duplicate runs if a worker retries.

## Caching Strategy

- **Hot Path Cache**: `cache:reporting:company:{company_id}:card:{card_id}` (5s TTL) for dashboard cards. Backend invalidates on ledger postings, payment updates, or manual refresh.
- **Trial Balance Snapshot**: `cache:reporting:trial-balance:{company_id}:{period}` (optional 60s TTL) backed by `mv_trial_balance_current`.
- **KPI Trends**: `cache:reporting:kpi-series:{company_id}:{kpi_id}:{granularity}` (5 min TTL) storing serialized sparkline points from `kpi_snapshots`.
- **Export Payloads**: After generating export files, metadata stored in `rpt.reports`; ephemeral cache `cache:reporting:download-token:{report_id}` (10 min) to authorize downloads.

## State & Workflow Summary

- Command bus actions (`reporting.generate`, `reporting.refresh-dashboard`, `reporting.schedule.run`) orchestrate view refresh, cache invalidation, and delivery pipelines.
- Scheduled queue workers refresh materialized views per company, then trigger KPI recompute actions to populate `kpi_snapshots`.
- Controllers/HTTP endpoints fetch from caches first, fall back to materialized views, and stream drill-down data via paginated queries using filter JSON produced by templates/layouts.
