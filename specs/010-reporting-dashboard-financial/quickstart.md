# Quickstart Guide: Reporting Dashboard

## Prerequisites
- Laravel 12 with PHP 8.2+
- PostgreSQL 16 with RLS enabled
- Redis for caching
- Vue 3 + Inertia.js v2 + PrimeVue v4

## Module Installation

```bash
# Create Reporting module
php artisan module:make Reporting

# Register module in config/modules.php
'modules' => [
    // ... existing modules
    'Reporting',
],
```

## Database Setup

```bash
# Run migrations
php artisan migrate

# Create module-specific tables
php artisan module:migrate Reporting

# Seed exchange rates (sample data)
php artisan db:seed --class=ExchangeRatesSeeder
```

## Configuration

1. **Add to `config/command-bus.php`**:
```php
'registered_actions' => [
    // ... existing
    App\Modules\Reporting\Actions\GenerateReport::class,
    App\Modules\Reporting\Actions\CreateDashboard::class,
    App\Modules\Reporting\Actions\ExportReport::class,
],
```

2. **Add permissions to database**:
```sql
INSERT INTO permissions (name, guard_name) VALUES
('view_dashboard', 'web'),
('generate_reports', 'web'),
('export_reports', 'web'),
('manage_templates', 'web'),
('manage_schedules', 'web');

-- Assign to roles
INSERT INTO role_has_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE p.name IN ('view_dashboard', 'generate_reports')
AND r.name IN ('Owner', 'Accountant');
```

## Quick Test Scenario

### 1. Access Dashboard (GUI)
```
URL: /reporting/dashboard
Method: GET
Expected: Dashboard with KPIs, charts, and period comparison
```

### 2. Generate Report (API)
```bash
curl -X POST http://localhost/api/v1/reports/types/income-statement \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "date_from": "2025-01-01",
    "date_to": "2025-01-31",
    "comparison_period": "previous_month",
    "currency": "USD"
  }'
```

### 3. CLI Command Test
```bash
# Test dashboard command
php artisan report dashboard --company={company-id}

# Test report generation
php artisan report generate income-statement \
    --from="2025-01-01" \
    --to="2025-01-31" \
    --export=pdf
```

## Verification Steps

1. **Dashboard Loads**:
   - [ ] KPIs display correctly
   - [ ] Charts render with data
   - [ ] Period comparison shows variance

2. **Report Generation**:
   - [ ] Income statement generates in <10 seconds
   - [ ] Balance sheet balances (assets = liabilities + equity)
   - [ ] Cash flow matches bank movements
   - [ ] Trial balance debits = credits

3. **Multi-tenancy**:
   - [ ] Reports only show company data
   - [ ] RLS policies prevent cross-tenant access
   - [ ] User permissions enforced

4. **Performance**:
   - [ ] Dashboard loads in <2 seconds
   - [ ] KPI cache refreshes in <5 seconds
   - [ ] Large reports stream properly

5. **Export Functionality**:
   - [ ] PDF generates with proper formatting
   - [ ] Excel file contains all data
   - [ ] CSV exports correctly

6. **CLI Parity**:
   - [ ] All GUI features available in CLI
   - [ ] Natural language commands work
   - [ ] Output formats consistent

## Sample Data for Testing

```bash
# Seed sample transactions
php artisan db:seed --class=ReportingSeeder

# Creates:
# - 3 months of transactions
# - Sample invoices and payments
# - Exchange rates for USD/EUR/GBP
# - Dashboard KPI configurations
```

## Manual QA Checklist

1. Login as different user roles
2. Verify role-based access to reports
3. Test currency conversion accuracy
4. Verify date range filtering
5. Test scheduled reports
6. Check report expiration
7. Validate audit trail entries

## Common Issues & Solutions

1. **Slow report generation**:
   - Check database indexes
   - Verify Redis caching is active
   - Review query execution plans

2. **Empty dashboard**:
   - Verify company_id context
   - Check if transactions exist
   - Ensure user has permissions

3. **Export failures**:
   - Check file permissions
   - Verify memory limits
   - Check PDF library installation

## Rollback Plan

```bash
# Remove module
php artisan module:delete Reporting

# Rollback migrations
php artisan module:rollback Reporting

# Remove permissions
DELETE FROM permissions WHERE name LIKE '%reporting%';
```