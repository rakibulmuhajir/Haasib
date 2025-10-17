# Quickstart – Financial Reporting & KPI Dashboard

## Prerequisites
- Feature flag/module `reporting_dashboard` enabled for the company (`modules` table).
- Roles mapped with new permissions: `reporting.dashboard.view`, `reporting.reports.generate`, `reporting.reports.export`, `reporting.reports.schedule`.
- Command bus actions registered in `stack/config/command-bus.php`: `reporting.dashboard.refresh`, `reporting.report.generate`, `reporting.schedule.run`, `reporting.template.manage`.
- Queue workers processing `reporting` and `exports` queues; cache store configured (database or Redis) to honor ≤5s TTL.
- Materialized view refresh job scheduled (`php artisan reporting:refresh-views --company={uuid}`) or equivalent cron entry.

## 1. Open the Reporting Dashboard
- Navigate to **Reporting → Financial Dashboard**. The default layout loads KPI cards, charts, and tables using the current month range.
- Use the date range picker (pre-sets: Today, MTD, QTD, YTD, Custom). Layout filters propagate to all widgets.
- Toggle comparison (Prior Period / Prior Year) to populate trend deltas; cards update in place within SLA (<5s).

### API
```bash
curl -H "Authorization: Bearer $TOKEN" \
     -H "X-Company-Id: $COMPANY_ID" \
     "https://api.haasib.local/api/reporting/dashboard?layout_id=$LAYOUT&date_range[start]=2025-09-01&date_range[end]=2025-09-30"
```

## 2. Refresh live metrics
- Click **Refresh** to enqueue a cache refresh. Progress toaster indicates queue status; widgets reload when the job completes.
- Use **Invalidate Cache** when ledgers were posted externally (e.g., CLI commands) to force view/materialized view regeneration.

### CLI
```bash
php artisan reporting:dashboard:refresh \
  --company=$COMPANY_ID \
  --layout=$LAYOUT \
  --invalidate-cache
```

## 3. Generate financial statements
- Go to **Reporting → Statements** and choose Income Statement, Balance Sheet, Cash Flow, or Trial Balance.
- Select period (single or comparison), currency override, and whether to include unposted transactions.
- Submit to queue; the UI polls until status `generated` then exposes view/download buttons. Heavy reports (YTD, multi-year) execute asynchronously but respect the 10s generation SLA for standard scopes.

### CLI
```bash
php artisan reporting:report \
  --company=$COMPANY_ID \
  --type=income_statement \
  --start=2025-01-01 \
  --end=2025-01-31 \
  --comparison=prior_period \
  --currency=USD
```

## 4. Drill into results
- From dashboard cards or statement rows, click **View details**. A modal opens with lazy-loaded paginated transactions sourced from `rpt.v_transaction_drilldown`.
- Filters inherited from the card (account, customer, project) are editable; exporting the drill-down triggers a lightweight CSV download (download token valid 10 minutes).

## 5. Export & share
- Use the **Export** dropdown to request PDF, Excel, or CSV. Exports appear in the **Downloads** panel with expiration timestamps (default 30 days).
- Share via email by checking recipients (Owner/Accountant roles). Email deliveries log to `rpt.report_deliveries` with audit entries.

### CLI
```bash
php artisan reporting:report \
  --company=$COMPANY_ID \
  --type=trial_balance \
  --start=2025-09-01 \
  --end=2025-09-30 \
  --export=pdf \
  --deliver=email:user@example.com,email:auditor@example.com
```

## 6. Customize templates & layouts
- In **Reporting → Templates**, clone a system template or build a custom view: define columns, KPI cards, filters, and drill-down settings. Save to make it available across the company (if `is_public`).
- For dashboards, select **Customize Layout** to rearrange cards, add KPI widgets, or embed saved reports. Save as private or share with specific roles.

### API
```bash
curl -X POST https://api.haasib.local/api/reporting/templates \
     -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: application/json" \
     -d '{
           "name": "Cash Flow - Weekly",
           "report_type": "cash_flow",
           "category": "financial",
           "configuration": {"sections":[...]},
           "filters": {"granularity":"weekly"},
           "parameters": {"comparison":"prior_period"},
           "is_public": true
         }'
```

## 7. Schedule recurring reports
- Open **Reporting → Schedules** and create a schedule: choose template, frequency (Daily/Weekly/Monthly/Custom CRON), timezone, and recipients.
- The scheduler dispatches `reporting.schedule.run` jobs; completed runs deliver exports and update the activity feed.
- Pause/resume schedules to temporarily suppress deliveries; archiving preserves history but stops future runs.

### CLI
```bash
php artisan reporting:schedule:create \
  --company=$COMPANY_ID \
  --template=42 \
  --name="Month-End Pack" \
  --frequency=monthly \
  --next-run="2025-10-31T02:00:00-05:00" \
  --recipients=email:cfo@example.com,email:controller@example.com
```

## 8. Handle multi-currency
- Switch **Display Currency** to render dashboard values using company base currency with captured exchange rates. Conversion audit details surface in the drill-down sidebar.
- Rate refresh command (`php artisan accounting:exchange-rates:sync`) updates `public.exchange_rates`; the next dashboard refresh recalculates KPIs using the stored snapshot to maintain consistency.

## Permission matrix
- `reporting.dashboard.view` – Access dashboards and KPI cards.
- `reporting.dashboard.refresh` – Trigger cache/materialized view refresh.
- `reporting.reports.generate` – Queue statements/trial balance jobs.
- `reporting.reports.export` – Download exports and deliver to recipients.
- `reporting.templates.manage` – Create/update/archive templates and layouts.
- `reporting.reports.schedule` – Create or manage report schedules.
- `reporting.reports.admin` – Override expired downloads, manage system templates (restricted to Owner role).
