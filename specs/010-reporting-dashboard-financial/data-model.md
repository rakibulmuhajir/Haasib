# Data Model: Reporting Dashboard - Financial & KPI

## Core Entities

### Report (reports table)
```php
Report {
    id: UUID (primary)
    company_id: UUID (foreign, RLS scope)
    user_id: UUID (foreign, who created)
    type: enum ('income_statement', 'balance_sheet', 'cash_flow', 'trial_balance', 'aging_report', 'kpi_dashboard')
    title: string
    date_from: date
    date_to: date
    filters: json (stored filter criteria)
    parameters: json (report parameters)
    status: enum ('pending', 'processing', 'completed', 'failed')
    file_path: string (path to generated file)
    generated_at: timestamp
    expires_at: timestamp
    created_at: timestamp
    updated_at: timestamp
}
```

### ReportSchedule (report_schedules table)
```php
ReportSchedule {
    id: UUID (primary)
    company_id: UUID (foreign, RLS scope)
    user_id: UUID (foreign, who owns)
    report_type: enum (same as Report.type)
    title: string
    frequency: enum ('daily', 'weekly', 'monthly', 'quarterly', 'yearly')
    parameters: json
    recipients: json (email addresses)
    next_run_at: timestamp
    last_run_at: timestamp
    is_active: boolean
    created_at: timestamp
    updated_at: timestamp
}
```

### ReportTemplate (report_templates table)
```php
ReportTemplate {
    id: UUID (primary)
    company_id: UUID (foreign, RLS scope)
    user_id: UUID (foreign, who created)
    name: string
    report_type: enum (same as Report.type)
    layout: json (template configuration)
    filters: json (default filters)
    is_default: boolean
    is_public: boolean (shared across company)
    created_at: timestamp
    updated_at: timestamp
}
```

### DashboardKPI (dashboard_kpis table)
```php
DashboardKPI {
    id: UUID (primary)
    company_id: UUID (foreign, RLS scope)
    name: string
    type: enum ('revenue', 'expenses', 'profit', 'cash', 'receivables', 'payables', 'custom')
    formula: string (SQL or calculation logic)
    format: enum ('currency', 'percentage', 'number', 'days')
    display_order: integer
    is_active: boolean
    created_at: timestamp
    updated_at: timestamp
}
```

### ExchangeRate (exchange_rates table)
```php
ExchangeRate {
    id: UUID (primary)
    from_currency: string (3-letter ISO)
    to_currency: string (3-letter ISO)
    rate: decimal(20, 8)
    date: date
    source: string (e.g., ' ECB', 'central_bank')
    created_at: timestamp
    updated_at: timestamp

    Indexes:
    - unique(from_currency, to_currency, date)
}
```

## Relationships

```
Company 1--N Report (company_id)
User 1--N Report (user_id)
Report 1--1 ReportFile (file_path)

Company 1--N ReportSchedule (company_id)
User 1--N ReportSchedule (user_id)
ReportSchedule 1--N Report (scheduled reports)

Company 1--N ReportTemplate (company_id)
User 1--N ReportTemplate (user_id)

Company 1--N DashboardKPI (company_id)
```

## Views & Virtual Tables

### TrialBalanceView
```sql
CREATE VIEW trial_balance_view AS
SELECT
    a.id as account_id,
    a.code,
    a.name,
    a.type,
    COALESCE(SUM(j.amount), 0) as balance,
    j.currency,
    j.company_id
FROM accounts a
LEFT JOIN (
    SELECT
        account_id,
        amount,
        currency,
        company_id
    FROM journal_entries
    WHERE status = 'posted'
) j ON a.id = j.account_id
GROUP BY a.id, a.code, a.name, a.type, j.currency, j.company_id;
```

### CashFlowView
```sql
CREATE VIEW cash_flow_view AS
SELECT
    DATE(j.post_date) as date,
    CASE
        WHEN a.type IN ('revenue', 'liability') THEN j.amount
        ELSE -j.amount
    END as cash_flow,
    j.company_id
FROM journal_entries j
JOIN accounts a ON j.account_id = a.id
WHERE a.category = 'cash'
AND j.status = 'posted';
```

## Indexes for Performance

```sql
-- Reports table
CREATE INDEX idx_reports_company_type_date ON reports(company_id, type, date_from, date_to);
CREATE INDEX idx_reports_status ON reports(status) WHERE status IN ('pending', 'processing');

-- Journal entries (existing, but ensuring)
CREATE INDEX idx_journal_entries_company_date ON journal_entries(company_id, post_date);
CREATE INDEX idx_journal_entries_account ON journal_entries(account_id);

-- Exchange rates
CREATE INDEX idx_exchange_rates_date ON exchange_rates(date);
CREATE INDEX idx_exchange_rates_currency ON exchange_rates(from_currency, to_currency);

-- Report schedules
CREATE INDEX idx_report_schedules_next_run ON report_schedules(next_run_at) WHERE is_active = true;
```

## Data Integrity Rules

1. **Tenant Isolation**: All queries must include company_id filter
2. **Date Validation**: date_to must be >= date_from
3. **Currency Conversion**: Exchange rates must exist for transaction dates
4. **Report Expiration**: Reports expire after 30 days (configurable)
5. **Permission Checks**: Users can only access reports they created or public templates

## Caching Strategy

1. **Dashboard KPIs**: Cache key `kpi:{company_id}:{date}` with 30s TTL
2. **Trial Balance**: Cache key `trial_balance:{company_id}:{date_from}:{date_to}` with 5m TTL
3. **Exchange Rates**: Cache key `rate:{from}:{to}:{date}` with 1h TTL
4. **Report Results**: Cache key `report:{report_id}` with 5m TTL