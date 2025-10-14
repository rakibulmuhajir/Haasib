# Quickstart Guide: Initial Platform Setup

## Prerequisites
- Laravel 12 with PHP 8.2+
- PostgreSQL 16 with RLS enabled
- Redis for caching/queues
- Vue 3 + Inertia.js v2 + PrimeVue v4

## Installation Steps

### 1. Install Required Packages
```bash
# Ensure composer/npm dependencies match salvaged code
composer install
npm install
# Install additional packages if missing from legacy lockfiles
composer require spatie/laravel-permission
npm install @primevue/core primeicons primevue
```

### 2. Register Service Providers
```php
// config/app.php
'providers' => [
    // ...
    Nwidart\Modules\LaravelModulesServiceProvider::class,
    Spatie\Permission\PermissionServiceProvider::class,
],
```

### 3. Configure Module System
```bash
php artisan vendor:publish --provider="Nwidart\Modules\LaravelModulesServiceProvider"
php artisan config:cache
```

### 4. Create Accounting Module
```bash
# Scaffold module only if not yet ported
php artisan module:make Accounting

# Import existing code from legacy branches
cp -r ../rebootstrap-primevue/modules/Accounting/* modules/Accounting/

# Register in app/config/modules.php with correct metadata
```

### 5. Run Database Setup
```bash
# Reuse vetted migrations from legacy app
cp -r ../rebootstrap-primevue/database/migrations/* database/migrations/
cp -r ../rebootstrap-primevue/database/seeders/* database/seeders/

# Compare against docs/schemas SQL before running
php artisan migrate

# Create RLS policies
psql -d your_database -f database/rls/policies.sql

# Seed initial data
php artisan db:seed --class=SetupSeeder
```

## Quick Demo (5 Minutes)

### 1. Initialize System
```bash
# Check if system needs setup
php artisan haasib:status

# Initialize with demo data
php artisan haasib:setup:init --demo-data
```

### 2. Access via Web
```
URL: http://localhost:8000
Expected: User selection screen with 5 predefined users
```

### 3. Login and Explore
```bash
# CLI login
php artisan users:switch system_owner

# List companies
php artisan companies

# Switch company
php artisan companies:switch "Grand Hotel"

# Check modules
php artisan modules:status
```

## Verification Steps

### 1. System Initialization
- [ ] Setup status shows "Initialized: Yes"
- [ ] 3 companies created with different industries
- [ ] 5 users created with correct roles
- [ ] All 3 modules enabled

### 2. User Access Test
- [ ] Can access user selection screen
- [ ] Each user can login successfully
- [ ] Users see only companies they have access to
- [ ] Role-based permissions enforced

### 3. Company Switching Test
- [ ] Company dropdown shows all accessible companies
- [ ] Switching company updates all UI elements
- [ ] CLI context switches with `--company` flag
- [ ] Data isolation enforced (can't see other company data)

### 4. Module Functionality Test
- [ ] Core module: User/company management works
- [ ] Ledger module: Can view chart of accounts
- [ ] Invoicing module: Can create/view invoices
- [ ] Module enable/disable works correctly

### 5. Demo Data Verification
- [ ] Hospitality: Room bookings, restaurant sales
- [ ] Retail: Product sales, inventory transactions
- [ ] Professional Services: Hourly billing, retainers
- [ ] 3 months of progressive data with growth trends

### 6. CLI-GUI Parity Test
- [ ] All UI actions have CLI equivalents
- [ ] Natural language commands work
- [ ] Command palette accessible (Cmd/Ctrl+K)
- [ ] Output formats consistent

## Common Issues & Solutions

### Setup Fails
```bash
# Check database connection
php artisan db:show

# Clear cache
php artisan config:clear
php artisan cache:clear

# Check PostgreSQL RLS
psql -d your_database -c "\d+ company_users"
```

### Module Not Loading
```bash
# Ensure modules are registered
php artisan module:list

# Clear module cache
php artisan module:clear-compiled

# Check autoloader
composer dump-autoload
```

### Permissions Not Working
```bash
# Re-sync permissions
php artisan permission:cache-reset
php artisan db:seed --class=PermissionSeeder
```

### Demo Data Missing
```bash
# Re-run seeder
php artisan db:seed --class=DemoDataSeeder --force

# Check seeder logs
tail -f storage/logs/laravel.log
```

## Rollback Plan

### Complete Reset
```bash
# Reset database (WARNING: deletes all data)
php artisan migrate:fresh --seed

# Remove modules
php artisan module:delete Core
php artisan module:delete Ledger
php artisan module:delete Invoicing

# Remove config
rm config/modules.php
```

### Partial Reset
```bash
# Reset only demo data
php artisan db:seed --class=ResetDemoSeeder

# Reset users/companies only
php artisan db:seed --class=ResetCompaniesSeeder
```

## Performance Checklist

- [ ] Page loads under 200ms
- [ ] Company switching under 100ms
- [ ] Command palette appears instantly
- [ ] Demo data seeding under 5 seconds
- [ ] RLS queries indexed properly

## Security Checklist

- [ ] RLS policies active on all tenant tables
- [ ] Users cannot access other companies' data
- [ ] Role permissions enforced
- [ ] All mutations have audit entries
- [ ] Idempotency keys present on writes

## Next Steps

After successful setup:
1. Explore each module's capabilities
2. Test command palette with natural language
3. Review demo data for each industry type
4. Proceed to next feature: 002-company-registration-multi
