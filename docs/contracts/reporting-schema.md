# Schema Contract — Reporting (rpt)

Single source of truth for report templates, generated reports, and financial statements. This module provides metadata-driven reporting and auditable financial snapshots.

## Guardrails
- Schema: `rpt` on `pgsql`.
- UUID primary keys with `public.gen_random_uuid()` default.
- Soft deletes via `deleted_at` on templates only.
- RLS required with company isolation + super-admin override.
- Models must set `$connection = 'pgsql'`, schema-qualified `$table`.
- Financial statements are immutable once finalized (audit trail).
- Report functions query GL data; no separate data storage.
- JSONB used for flexible report configuration and data storage.

## Tables

### rpt.report_templates
- Purpose: metadata-driven report definitions.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `name` varchar(255) not null.
  - `description` text nullable.
  - `report_type` varchar(50) not null. Enum: trial_balance, profit_loss, balance_sheet, cash_flow, ar_aging, ap_aging, general_ledger, journal, custom.
  - `category` varchar(50) not null default 'financial'. Enum: financial, operational, analytical, compliance.
  - `configuration` jsonb not null default '{}'. Keys: columns[], filters[], grouping[], sorting[], comparisons[], formatting.
  - `sql_query` text nullable (for custom reports).
  - `parameters` jsonb not null default '[]'. Array of {name, type, label, default, required}.
  - `output_formats` jsonb not null default '["html","pdf","xlsx"]'.
  - `is_system_template` boolean not null default false.
  - `is_public` boolean not null default false (visible to all users).
  - `is_favorite` boolean not null default false.
  - `sort_order` integer not null default 0.
  - `last_run_at` timestamp nullable.
  - `run_count` integer not null default 0.
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `updated_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at`, `deleted_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Index: `company_id`; (`company_id`, `report_type`); (`company_id`, `category`); (`company_id`, `is_favorite`).
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'rpt.report_templates'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','name','description','report_type','category','configuration','sql_query','parameters','output_formats','is_system_template','is_public','is_favorite','sort_order','last_run_at','run_count','created_by_user_id','updated_by_user_id'];`
  - `$casts = ['company_id'=>'string','configuration'=>'array','parameters'=>'array','output_formats'=>'array','is_system_template'=>'boolean','is_public'=>'boolean','is_favorite'=>'boolean','sort_order'=>'integer','last_run_at'=>'datetime','run_count'=>'integer','created_by_user_id'=>'string','updated_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime','deleted_at'=>'datetime'];`
- Relationships: belongsTo Company; hasMany Report.
- Validation:
  - `name`: required|string|max:255.
  - `report_type`: required|in:trial_balance,profit_loss,balance_sheet,cash_flow,ar_aging,ap_aging,general_ledger,journal,custom.
  - `category`: required|in:financial,operational,analytical,compliance.
  - `configuration`: array.
  - `parameters`: array.
- Business rules:
  - System templates seeded on company creation.
  - System templates cannot be deleted.
  - Custom reports require sql_query (validated for safety).
  - Parameters define user inputs when running report.

### rpt.reports
- Purpose: generated report instances (results/files).
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `template_id` uuid nullable FK → `rpt.report_templates.id` (SET NULL/CASCADE).
  - `name` varchar(255) not null.
  - `report_type` varchar(50) not null.
  - `parameters_used` jsonb not null default '{}' (actual values used).
  - `filters_used` jsonb not null default '{}'.
  - `date_range_start` date nullable.
  - `date_range_end` date nullable.
  - `as_of_date` date nullable (for point-in-time reports).
  - `status` varchar(20) not null default 'pending'. Enum: pending, generating, generated, failed.
  - `file_path` varchar(500) nullable.
  - `file_size` bigint nullable.
  - `mime_type` varchar(100) nullable.
  - `data` jsonb nullable (cached report data for quick re-render).
  - `row_count` integer nullable.
  - `error_message` text nullable.
  - `generated_at` timestamp nullable.
  - `expires_at` timestamp nullable.
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Index: `company_id`; `template_id`; (`company_id`, `report_type`); (`company_id`, `created_at`); `expires_at`.
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'rpt.reports'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','template_id','name','report_type','parameters_used','filters_used','date_range_start','date_range_end','as_of_date','status','file_path','file_size','mime_type','data','row_count','error_message','generated_at','expires_at','created_by_user_id'];`
  - `$casts = ['company_id'=>'string','template_id'=>'string','parameters_used'=>'array','filters_used'=>'array','date_range_start'=>'date','date_range_end'=>'date','as_of_date'=>'date','file_size'=>'integer','data'=>'array','row_count'=>'integer','generated_at'=>'datetime','expires_at'=>'datetime','created_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo ReportTemplate.
- Business rules:
  - Expired reports can be cleaned up by scheduled job.
  - Large reports stored as files; small ones in `data` JSONB.
  - Status = 'failed' stores error_message for debugging.

### rpt.financial_statements
- Purpose: auditable financial statement snapshots.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `fiscal_year_id` uuid not null FK → `acct.fiscal_years.id` (RESTRICT/CASCADE).
  - `period_id` uuid nullable FK → `acct.accounting_periods.id` (SET NULL/CASCADE).
  - `statement_type` varchar(30) not null. Enum: balance_sheet, profit_loss, cash_flow, equity, trial_balance.
  - `name` varchar(255) not null.
  - `statement_date` date not null (as-of date).
  - `date_range_start` date nullable.
  - `date_range_end` date nullable.
  - `data` jsonb not null default '{}'. Structure depends on statement_type.
  - `totals` jsonb not null default '{}'. Summary figures.
  - `comparative_data` jsonb not null default '{}'. Prior period comparison.
  - `notes` text nullable.
  - `status` varchar(20) not null default 'draft'. Enum: draft, finalized, published.
  - `version` integer not null default 1.
  - `finalized_at` timestamp nullable.
  - `finalized_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `published_at` timestamp nullable.
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `updated_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Index: `company_id`; (`company_id`, `statement_type`, `statement_date`); `fiscal_year_id`; `period_id`.
  - Check: version > 0.
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'rpt.financial_statements'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','fiscal_year_id','period_id','statement_type','name','statement_date','date_range_start','date_range_end','data','totals','comparative_data','notes','status','version','finalized_at','finalized_by_user_id','published_at','created_by_user_id','updated_by_user_id'];`
  - `$casts = ['company_id'=>'string','fiscal_year_id'=>'string','period_id'=>'string','statement_date'=>'date','date_range_start'=>'date','date_range_end'=>'date','data'=>'array','totals'=>'array','comparative_data'=>'array','version'=>'integer','finalized_at'=>'datetime','finalized_by_user_id'=>'string','published_at'=>'datetime','created_by_user_id'=>'string','updated_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime'];`
- Relationships: belongsTo Company; belongsTo FiscalYear; belongsTo AccountingPeriod.
- Validation:
  - `fiscal_year_id`: required|uuid|exists:acct.fiscal_years,id.
  - `statement_type`: required|in:balance_sheet,profit_loss,cash_flow,equity,trial_balance.
  - `statement_date`: required|date.
  - `status`: in:draft,finalized,published.
- Business rules:
  - Finalized statements are immutable; create new version if changes needed.
  - Published statements visible to external users (if applicable).
  - data structure varies by statement_type (see below).

## Report Functions (SQL)

### rpt.trial_balance(company_id, start_date, end_date)
```sql
-- Returns: account_id, account_code, account_name, balance_type, opening, period_debit, period_credit, closing
-- Logic:
--   opening = sum(debit - credit) for transactions before start_date
--   period_debit = sum(debit) for transactions in date range
--   period_credit = sum(credit) for transactions in date range
--   closing = opening + period_debit - period_credit (adjusted for normal balance)
```

### rpt.profit_and_loss(company_id, start_date, end_date)
```sql
-- Returns: section (revenue/expense), account_id, account_code, account_name, amount
-- Logic:
--   Filter accounts where type IN ('revenue', 'expense', 'other_income', 'other_expense', 'cogs')
--   Sum (credit - debit) for revenue types
--   Sum (debit - credit) for expense types
--   Group by account
```

### rpt.balance_sheet(company_id, as_of_date)
```sql
-- Returns: section (asset/liability/equity), account_id, account_code, account_name, amount
-- Logic:
--   Filter accounts where type IN ('asset', 'liability', 'equity')
--   Sum all transactions up to as_of_date
--   Apply normal balance sign
```

### rpt.cash_flow(company_id, start_date, end_date)
```sql
-- Returns: section (operating/investing/financing), description, amount
-- Logic:
--   Operating: net income + non-cash adjustments + working capital changes
--   Investing: fixed asset purchases/sales
--   Financing: loan proceeds/payments, equity transactions
-- Note: Requires account classification or transaction tagging
```

### rpt.ar_aging(company_id, as_of_date)
```sql
-- Returns: customer_id, customer_name, current, 1_30, 31_60, 61_90, over_90, total
-- Logic:
--   Calculate days overdue = as_of_date - due_date
--   Bucket invoice balances by days overdue
```

### rpt.ap_aging(company_id, as_of_date)
```sql
-- Returns: vendor_id, vendor_name, current, 1_30, 31_60, 61_90, over_90, total
-- Logic:
--   Same as AR aging but for bills
```

## Data Structures

### Trial Balance Data
```json
{
  "rows": [
    {
      "account_id": "uuid",
      "account_code": "1000",
      "account_name": "Cash",
      "account_type": "asset",
      "normal_balance": "debit",
      "opening_debit": 10000.00,
      "opening_credit": 0.00,
      "period_debit": 5000.00,
      "period_credit": 3000.00,
      "closing_debit": 12000.00,
      "closing_credit": 0.00
    }
  ],
  "totals": {
    "opening_debit": 50000.00,
    "opening_credit": 50000.00,
    "period_debit": 25000.00,
    "period_credit": 25000.00,
    "closing_debit": 75000.00,
    "closing_credit": 75000.00
  }
}
```

### Profit & Loss Data
```json
{
  "revenue": {
    "accounts": [...],
    "total": 100000.00
  },
  "cost_of_sales": {
    "accounts": [...],
    "total": 40000.00
  },
  "gross_profit": 60000.00,
  "operating_expenses": {
    "accounts": [...],
    "total": 35000.00
  },
  "operating_income": 25000.00,
  "other_income": {...},
  "other_expenses": {...},
  "net_income": 23000.00
}
```

### Balance Sheet Data
```json
{
  "assets": {
    "current_assets": {
      "accounts": [...],
      "total": 50000.00
    },
    "fixed_assets": {
      "accounts": [...],
      "total": 100000.00
    },
    "total": 150000.00
  },
  "liabilities": {
    "current_liabilities": {...},
    "long_term_liabilities": {...},
    "total": 80000.00
  },
  "equity": {
    "accounts": [...],
    "retained_earnings": 45000.00,
    "current_year_earnings": 25000.00,
    "total": 70000.00
  },
  "total_liabilities_equity": 150000.00
}
```

## Enums Reference

### Report Type
| Type | Description | Period Type |
|------|-------------|-------------|
| trial_balance | Trial Balance | Date range |
| profit_loss | Income Statement | Date range |
| balance_sheet | Balance Sheet | Point in time |
| cash_flow | Cash Flow Statement | Date range |
| ar_aging | AR Aging | Point in time |
| ap_aging | AP Aging | Point in time |
| general_ledger | GL Detail | Date range |
| journal | Journal Entries | Date range |
| custom | Custom Query | Varies |

### Statement Status
| Status | Description | Editable |
|--------|-------------|----------|
| draft | Work in progress | Yes |
| finalized | Approved, locked | No |
| published | Externally visible | No |

## Form Behaviors

### Run Report Form
- Select template or report type
- Enter parameters (date range, filters)
- Preview in browser
- Export options: PDF, Excel, CSV
- Option to save as generated report

### Financial Statement Form
- Select statement type
- Select fiscal year and optionally period
- Generate from GL data
- Review and adjust notes
- Finalize (locks for audit)
- Compare with prior periods

### Custom Report Builder
- Drag-drop columns from available fields
- Set filters and parameters
- Preview query results
- Save as template
- SQL validation for security

## System Report Templates

Seed these templates on company creation:

| Name | Type | Description |
|------|------|-------------|
| Trial Balance | trial_balance | Standard trial balance |
| Income Statement | profit_loss | P&L by account |
| Balance Sheet | balance_sheet | Standard balance sheet |
| AR Aging Summary | ar_aging | Customer aging buckets |
| AP Aging Summary | ap_aging | Vendor aging buckets |
| General Ledger | general_ledger | GL detail by account |
| Journal Report | journal | Journal entries list |

## Out of Scope (v1)
- Scheduled/automated report generation.
- Report email delivery.
- Dashboard widgets from reports.
- Report drill-down to transactions.
- Multi-company consolidated reports.
- Budgets vs actuals comparison.
- Custom formula fields.

## Extending
- Add new report_type values here first.
- Custom reports use parameterized SQL (prevent injection).
- Consider `rpt.report_schedules` for automated generation.
- Dashboard integration via `rpt.widgets` table.
