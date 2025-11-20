# 🚚 HAASIB MIGRATION PLAN: /stack → /build

**Migration Date**: 2025-11-18  
**Source**: `/home/banna/projects/Haasib/stack`  
**Target**: `/home/banna/projects/Haasib/build`  
**Objective**: Module-driven clean rebuild with constitutional compliance

---

## 📋 MIGRATION OVERVIEW

### Strategy
- **Module-Driven**: Migrate complete self-contained modules (Core → Accounting → Future modules)
- **Complete Module Testing**: Test full module functionality after migration
- **Constitutional Compliance**: All code follows CLAUDE.md standards  
- **Zero Drift**: Use exact templates from CLAUDE.md
- **Module Isolation**: Each module can be independently enabled/disabled
- **Constitution as Source of Truth**: At the start of EVERY phase run `cat CLAUDE.md | head -n 120` and skim the relevant sections to confirm no instructions changed; log the timestamp in `migration-journal.md`.
- **Change Journal**: Maintain `/home/banna/projects/Haasib/migration-journal.md` with phase name, commands executed, and blockers so later agents can audit the exact steps.

### Current Architecture Analysis
**Mixed Architecture Problem**: Current `/stack` mixes traditional Laravel structure with modular:
- ❌ Controllers in both `/app/Http/Controllers/Accounting/` AND `/modules/Accounting/Http/Controllers/`
- ❌ Models in both `/app/Models/` AND `/modules/Accounting/Models/`
- ❌ Routes scattered across `/routes/web.php` AND `/modules/Accounting/Routes/`
- ❌ Frontend all in `/resources/js/Pages/` instead of module-specific

### Target Architecture
**Pure Modular Structure**: Everything belongs to its module:
- ✅ All Accounting components in `/modules/Accounting/`
- ✅ Module-specific frontend in `/modules/Accounting/Resources/js/`
- ✅ Self-contained routes, controllers, models, views per module
- ✅ Clear module dependencies and boundaries

### Success Criteria
- ✅ Each module works as complete business unit
- ✅ Modules can be enabled/disabled independently
- ✅ Constitutional compliance verified per module
- ✅ No 403 permission errors
- ✅ Clean module boundaries and dependencies

---

## 🏗️ PHASE 1: FOUNDATION SETUP

### Step 1.1: Clean Laravel Installation
```bash
# Create build directory
mkdir -p /home/banna/projects/Haasib/build
cd /home/banna/projects/Haasib/build

# Fresh Laravel install with exact dependencies
php -v # ensure PHP 8.2
composer create-project laravel/laravel . "^12.0"
# Freeze composer + npm metadata so future agents reuse exact lockfiles
composer config minimum-stability stable
composer config preferred-install dist
npm pkg set type="module"
# Record install metadata
php artisan --version >> /home/banna/projects/Haasib/migration-journal.md
```

### Step 1.2: Core Dependencies & Configuration
```bash
# Backend dependencies (exact versions)
composer require inertiajs/inertia-laravel:^2.0
composer require laravel/sanctum:^4.0
composer require spatie/laravel-permission:^6.0
composer require spatie/laravel-activitylog:^4.0
composer require predis/predis

# Frontend dependencies  
npm install @inertiajs/vue3@^2.0 vue@^3.0
npm install primevue@^4.0.0
npm install @primevue/themes
npm install tailwindcss@^3.0
npm install @vitejs/plugin-vue

# Copy essential config files only
cp stack/config/app.php build/config/
cp stack/config/database.php build/config/
cp stack/config/inertia.php build/config/
cp stack/config/command-bus.php build/config/
cp stack/config/permission.php build/config/
cp stack/config/theme.php build/config/ || echo "# theme.php exists only if added later"
cp stack/package.json build/
cp stack/vite.config.js build/
cp stack/tailwind.config.js build/
cp stack/validate-migration.sh build/
cp -r stack/scripts build/

# Environment setup
cp stack/.env.example build/.env
sed -i 's/DB_DATABASE=.*/DB_DATABASE=haasib_build/' build/.env
```

### Step 1.3: Database & Basic Structure Setup
```bash
# Create clean database
PGPASSWORD="AppP@ss123" createdb -h localhost -U postgres haasib_build

# Install dependencies
cd build && composer install && npm install
cd build && composer validate
cd build && npm run lint -- --max-warnings=0 || true # Document any lint blockers

# Create basic schema structure
cd build && php artisan migrate:fresh
# Ensure core schemas exist with correct owner + RLS defaults
PGPASSWORD="AppP@ss123" psql -h localhost -U postgres haasib_build <<'SQL'
CREATE SCHEMA IF NOT EXISTS auth AUTHORIZATION postgres;
CREATE SCHEMA IF NOT EXISTS acct AUTHORIZATION postgres;
CREATE SCHEMA IF NOT EXISTS crm AUTHORIZATION postgres;
CREATE SCHEMA IF NOT EXISTS hsp AUTHORIZATION postgres;
SQL
# Seed baseline policies (see CLAUDE constitution refs)
cd build && php artisan db:seed --class=PermissionSeeder
cd build && php artisan migrate --path=database/migrations/2025_10_11_110306_enhance_company_rls_policies.php
```

**✅ Testing Checkpoint**: 
- Laravel welcome page loads at `npm run dev`
- Database connection successful
- Basic Laravel installation functional
- `php artisan test tests/Feature/Security/MultiTenantDataIsolationTest.php` passes (verifies RLS scaffold)

---

## 🏛️ PHASE 2: CORE MODULE SETUP

### Step 2.1: Core System Infrastructure

**⚠️ ARCHITECTURAL UPDATE (2025-11-19)**: Following **Hybrid Core Architecture** - Core/shared components remain in root directory for multi-module accessibility, only module-specific business logic goes in `/modules/`.

```bash
# Create Core module structure (for module-specific services/commands only)
mkdir -p build/modules/Core/{Services,CLI,Database}

# Copy Core module-specific components
cp -r stack/modules/Core/* build/modules/Core/

# Copy shared models to ROOT (used across all modules)
cp stack/app/Models/User.php build/app/Models/
cp stack/app/Models/Company.php build/app/Models/
cp stack/app/Models/Concerns/* build/app/Models/Concerns/

# Copy authentication migrations
cp stack/database/migrations/*_create_companies_table.php build/database/migrations/
cp stack/database/migrations/*_create_users_table.php build/database/migrations/
cp stack/database/migrations/*_create_permissions_tables.php build/database/migrations/
cp stack/database/migrations/2025_10_11_110306_enhance_company_rls_policies.php build/database/migrations/

# Copy RBAC system (critical for 403 fixes)
mkdir -p build/app/Constants
cp stack/app/Constants/Permissions.php build/app/Constants/
cp stack/app/Http/Requests/BaseFormRequest.php build/app/Http/Requests/
cp stack/database/seeders/PermissionSeeder.php build/database/seeders/
```

### Step 2.2: Module Loading Infrastructure
```bash
# Copy module configuration and providers
cp stack/config/modules.php build/config/ || echo "# Will create modules.php config"

# Copy module service provider
cp stack/app/Providers/ModuleServiceProvider.php build/app/Providers/ || echo "# Will create ModuleServiceProvider"
cp stack/app/Providers/CommandBusServiceProvider.php build/app/Providers/

# Register modules in app.php
echo "# Update bootstrap/providers.php with ModuleServiceProvider"
# Register command bus + module providers
echo "# Ensure config/app.php providers array includes CommandBusServiceProvider + ModuleServiceProvider"
sed -i "s#App\\\\Providers\\\\RouteServiceProvider::class,#App\\\\Providers\\\\RouteServiceProvider::class,\n        App\\\\Providers\\\\ModuleServiceProvider::class,\n        App\\\\Providers\\\\CommandBusServiceProvider::class,#" build/config/app.php
cd build && php artisan optimize:clear
# Verify providers wired correctly
cd build && php artisan about | rg -n "ModuleServiceProvider" || echo "Provider missing"
```

### Step 2.3: Core Authentication & Company Frontend
```bash
# Copy essential frontend structure (shared across modules)
mkdir -p build/resources/js/Components
cp stack/resources/js/app.js build/resources/js/
cp stack/resources/js/bootstrap.js build/resources/js/

# Copy layout components (strict compliance)
cp stack/resources/js/Components/UniversalPageHeader.vue build/resources/js/Components/
cp -r stack/resources/js/styles/ build/resources/js/

# Copy authentication pages (core functionality)
mkdir -p build/resources/js/Pages/Auth
cp stack/resources/js/Pages/Auth/* build/resources/js/Pages/Auth/

# Copy core company management pages
mkdir -p build/resources/js/Pages/Companies
cp stack/resources/js/Pages/Companies/* build/resources/js/Pages/Companies/
```

### Step 2.4: Core Routes & Controllers

**✅ HYBRID ARCHITECTURE**: Controllers remain in ROOT as they serve multiple modules and are part of shared infrastructure.

```bash
# Copy core authentication controllers to ROOT (shared across modules)
mkdir -p build/app/Http/Controllers/Auth
cp stack/app/Http/Controllers/Auth/* build/app/Http/Controllers/Auth/

# Copy core company controllers to ROOT (used by all modules)
cp stack/app/Http/Controllers/CompanyController.php build/app/Http/Controllers/

# Extract and copy core routes only
cp stack/routes/web.php build/routes/
cp stack/routes/api.php build/routes/
# Manual cleanup: Remove module-specific routes, keep only auth & core
rg -n "Accounting" -n "Invoice" build/routes/web.php # mark sections to move later
sed -i '/Accounting/,+20d' build/routes/web.php # iterative removal; confirm diff
cd build && php artisan route:list --columns=Method,URI,Name,Action | tee /tmp/core-routes.txt
```

**✅ Core Module Testing Checkpoint**: 
- [ ] User authentication works
- [ ] Company management functional
- [ ] Module loading infrastructure works
- [ ] No 403 permission errors
- [ ] Blue-whale theme applied correctly
- [ ] RBAC system operational

---

## 📊 PHASE 3: ACCOUNTING MODULE FOUNDATION

### Step 3.1: Module Skeleton & Shared Assets
```bash
mkdir -p build/modules/Accounting/{Domain,Services,CLI/Commands,Http/Controllers,Models,Routes,Database/Migrations,Database/Seeders,Resources/js/{Pages,Components,Composables,Layouts}}
cp -r stack/modules/Accounting/* build/modules/Accounting/
cp -r stack/app/Http/Controllers/Accounting/* build/modules/Accounting/Http/Controllers/ || true
cp -r stack/app/Http/Controllers/Invoicing/* build/modules/Accounting/Http/Controllers/ || true
cp stack/app/Models/Acct/* build/modules/Accounting/Models/ || true
rg -n "protected $table" build/modules/Accounting/Models | tee /tmp/accounting-models.txt
```

### Step 3.2: Shared Database & RLS Enforcement
```bash
cp stack/database/migrations/*_create_acct_*.php build/modules/Accounting/Database/Migrations/ || true
cp stack/modules/Accounting/Database/Migrations/* build/modules/Accounting/Database/Migrations/ || true
cp stack/database/seeders/*Customer*.php build/modules/Accounting/Database/Seeders/ || true
cp stack/database/seeders/*Invoice*.php build/modules/Accounting/Database/Seeders/ || true
PGPASSWORD="AppP@ss123" psql -h localhost -U postgres haasib_build <<'SQL'
CREATE SCHEMA IF NOT EXISTS acct AUTHORIZATION postgres;
ALTER DEFAULT PRIVILEGES IN SCHEMA acct GRANT ALL ON TABLES TO postgres;
SQL
cd build && php artisan migrate --path=modules/Accounting/Database/Migrations
cd build && php artisan db:seed --class=PermissionSeeder
cd build && php artisan migrate --path=database/migrations/2025_10_11_110306_enhance_company_rls_policies.php
cd build && php artisan migrate
cd build && php artisan test tests/Feature/Security/MultiTenantDataIsolationTest.php
PGPASSWORD="AppP@ss123" psql -h localhost -U postgres haasib_build -c "SELECT schemaname, tablename, policyname FROM pg_policies WHERE schemaname='acct';" | tee /tmp/acct-policies.txt
```

### Step 3.3: Shared Services, Actions & Command Bus Registration
```bash
cp -r stack/app/Services/Acct/* build/modules/Accounting/Services/ || true
cp -r stack/app/Actions/Acct/* build/modules/Accounting/Domain/Actions/ || true
cp -r stack/app/Actions/Customer* build/modules/Accounting/Domain/Actions/ || true
cp -r stack/app/Actions/Invoice* build/modules/Accounting/Domain/Actions/ || true
cp -r stack/app/Actions/Payment* build/modules/Accounting/Domain/Actions/ || true
vim build/config/command-bus.php # register accounting actions
rg -n "auth\(" build/modules/Accounting | grep -v tests
rg -n "request\(" build/modules/Accounting | grep -v tests
```

### Step 3.4: Shared Frontend Shell
```bash
mkdir -p build/modules/Accounting/Resources/js/{Components,Layouts}
cp stack/resources/js/Components/UniversalPageHeader.vue build/modules/Accounting/Resources/js/Components/
cp -r stack/resources/js/styles/ build/modules/Accounting/Resources/js/styles/
cp -r stack/resources/js/Pages/Accounting/* build/modules/Accounting/Resources/js/Pages/ || true
cp -r stack/resources/js/Pages/Invoicing/* build/modules/Accounting/Resources/js/Pages/ || true
cp -r stack/resources/js/Pages/Payments/* build/modules/Accounting/Resources/js/Pages/ || true
rg -l "<script>" build/modules/Accounting/Resources/js/Pages | xargs sed -n '1,20p'
npm run lint -- --fix --rule '{"vue/no-options-api": "error"}'
rg -n "data-theme" build/modules/Accounting/Resources/js/Pages | wc -l
```

### Step 3.5: Module Config + Base Routes
```bash
cp stack/modules/Accounting/Routes/web.php build/modules/Accounting/Routes/ || true
cp stack/modules/Accounting/Routes/api.php build/modules/Accounting/Routes/ || true
rg -n "Accounting" build/routes/web.php
cat > build/modules/Accounting/module.json <<'JSON'
{
  "name": "accounting",
  "description": "Accounting module foundation",
  "version": "1.0.0",
  "provider": "Modules\\Accounting\\Providers\\AccountingServiceProvider",
  "enabled": false,
  "dependencies": ["core"]
}
JSON
cd build && php artisan module:list
cd build && php artisan module:enable accounting
cd build && php artisan route:list --path=accounting | tee /tmp/accounting-foundation-routes.txt
cd build && php artisan module:disable accounting
```

### Step 3.6: Foundation QA & Documentation
```bash
# Document all copied files + pending TODOs
echo "Phase 3 complete on $(date)" >> /home/banna/projects/Haasib/migration-journal.md
git status -s modules/Accounting >> /home/banna/projects/Haasib/migration-journal.md

# Snapshot current provider + module registrations
cd build && php artisan about | rg -n "ModuleServiceProvider"
cd build && php artisan about | rg -n "CommandBusServiceProvider"

# Verify there are no browser console errors in base pages
cd build && npm run build && npm run preview &
PREVIEW_PID=$!
sleep 10 && kill $PREVIEW_PID
```

**✅ Accounting Foundation Checkpoint**:
- [ ] Module scaffolding + providers registered
- [ ] Accounting schema + RLS policies verified
- [ ] No forbidden helpers inside accounting services/controllers
- [ ] Module toggle works without touching core routes

---

## 👥 PHASE 4: ACCOUNTING – CUSTOMER MANAGEMENT

### Step 4.1: Customer Data Layer
```bash
cp stack/app/Models/Customer.php build/modules/Accounting/Models/Customer.php
sed -i "s#protected $table = 'customers'#protected $table = 'acct.customers'#g" build/modules/Accounting/Models/Customer.php
cp stack/database/migrations/*customers* build/modules/Accounting/Database/Migrations/
cp stack/database/seeders/*Customer* build/modules/Accounting/Database/Seeders/ || true
cd build && php artisan migrate --path=modules/Accounting/Database/Migrations --step
```

### Step 4.2: Customer Actions, Requests & Controllers
```bash
cp -r stack/modules/Accounting/Http/Controllers/Customers build/modules/Accounting/Http/Controllers/Customers || true
cp stack/app/Http/Requests/CreateCustomerRequest.php build/modules/Accounting/Http/Requests/
cp stack/app/Http/Requests/UpdateCustomerRequest.php build/modules/Accounting/Http/Requests/
cp stack/app/Http/Requests/DeleteCustomerRequest.php build/modules/Accounting/Http/Requests/
rg -n "validateRlsContext" build/modules/Accounting/Http/Requests
cp -r stack/app/Actions/Customer* build/modules/Accounting/Domain/Actions/ || true
vim build/config/command-bus.php # register customer commands
```

### Step 4.3: Customer Frontend & Routes
```bash
mkdir -p build/modules/Accounting/Resources/js/Pages/Customers
cp -r stack/resources/js/Pages/Customers/* build/modules/Accounting/Resources/js/Pages/Customers/ || true
rg -n "Customers" build/modules/Accounting/Resources/js/Pages/Customers
rg -n "customers" build/modules/Accounting/Routes/web.php
cd build && php artisan make:test Modules/Accounting/Customers/CustomerWorkflowTest --pest --unit || true
cd build && php artisan test tests/Feature/Modules/Accounting/Customers/CustomerWorkflowTest.php || true
```

### Step 4.4: Customer QA & Observability
```bash
rg -n "audit_log" build/modules/Accounting -g "*Customer*.php" # ensure every customer mutation logs
rg -n "authorize" build/modules/Accounting/Http/Controllers/Customers
cd build && php artisan tinker --execute="app()->make(Modules\\Accounting\\Domain\\Actions\\CreateCustomer::class);" || true # ensure dependency wiring

PGPASSWORD="AppP@ss123" psql -h localhost -U postgres haasib_build -c "SELECT COUNT(*) FROM acct.customers;"
php artisan route:list --path=customers
```

**✅ Customer Management Checkpoint**:
- [ ] Customers CRUD functional via module routes + Inertia pages
- [ ] Customer commands registered + audited
- [ ] Customer UI uses Composition API + blue-whale theme
- [ ] Pest suite for customers passing

---

## 📦 PHASE 5: ACCOUNTING – PRODUCT CATALOG

### Step 5.1: Product Models & Database Objects
```bash
cp stack/app/Models/Product.php build/modules/Accounting/Models/Product.php
sed -i "s#protected $table = 'products'#protected $table = 'acct.products'#g" build/modules/Accounting/Models/Product.php
cp stack/database/migrations/*products* build/modules/Accounting/Database/Migrations/ || true
cd build && php artisan migrate --path=modules/Accounting/Database/Migrations --step
```

### Step 5.2: Product Services, Actions & Validation
```bash
cp -r stack/app/Services/Acct/Products build/modules/Accounting/Services/Products || true
cp -r stack/app/Actions/Product* build/modules/Accounting/Domain/Actions/ || true
cp stack/app/Http/Requests/Products/* build/modules/Accounting/Http/Requests/Products/ || true
vim build/config/command-bus.php # register product commands
```

### Step 5.3: Product UI & Routes
```bash
mkdir -p build/modules/Accounting/Resources/js/Pages/Products
cp -r stack/resources/js/Pages/Products/* build/modules/Accounting/Resources/js/Pages/Products/ || true
rg -n "Product" build/modules/Accounting/Resources/js/Pages/Products
rg -n "products" build/modules/Accounting/Routes/web.php
cd build && php artisan make:test Modules/Accounting/Products/ProductCatalogTest --pest --unit || true
cd build && php artisan test tests/Feature/Modules/Accounting/Products/ProductCatalogTest.php || true
```

### Step 5.4: Product QA & Inventory Hooks
```bash
rg -n "SKU" build/modules/Accounting/Models/Product.php || true
rg -n "stock" build/modules/Accounting/Resources/js/Pages/Products || true
cd build && php artisan tinker --execute="Modules\\Accounting\\Models\\Product::first();"

PGPASSWORD="AppP@ss123" psql -h localhost -U postgres haasib_build -c "SELECT column_name,data_type FROM information_schema.columns WHERE table_schema='acct' AND table_name='products';"
```

**✅ Product Catalog Checkpoint**:
- [ ] Product CRUD + pricing fields scoped to acct schema
- [ ] Product validation uses BaseFormRequest + RLS checks
- [ ] Product Inertia pages theme-compliant
- [ ] Product tests added + passing

---

## 🧾 PHASE 6: ACCOUNTING – INVOICE LIFECYCLE

### Step 6.1: Invoice Data Layer
```bash
cp stack/app/Models/Invoice.php build/modules/Accounting/Models/Invoice.php
sed -i "s#protected $table = 'invoices'#protected $table = 'acct.invoices'#g" build/modules/Accounting/Models/Invoice.php
cp stack/database/migrations/*invoice* build/modules/Accounting/Database/Migrations/ || true
cd build && php artisan migrate --path=modules/Accounting/Database/Migrations --step
```

### Step 6.2: Invoice Commands & Services
```bash
cp -r stack/app/Actions/Invoice* build/modules/Accounting/Domain/Actions/ || true
cp -r stack/app/Services/Acct/Invoices build/modules/Accounting/Services/Invoices || true
cp stack/app/Http/Requests/Invoices/* build/modules/Accounting/Http/Requests/Invoices/ || true
rg -n "validateRlsContext" build/modules/Accounting/Http/Requests/Invoices
vim build/config/command-bus.php # register invoice commands
```

### Step 6.3: Invoice Frontend & Workflows
```bash
mkdir -p build/modules/Accounting/Resources/js/Pages/Invoices
cp -r stack/resources/js/Pages/Invoices/* build/modules/Accounting/Resources/js/Pages/Invoices/ || true
rg -n "Invoice" build/modules/Accounting/Resources/js/Pages/Invoices
rg -n "invoices" build/modules/Accounting/Routes/web.php
cd build && php artisan make:test Modules/Accounting/Invoices/InvoiceLifecycleTest --pest --unit || true
cd build && php artisan test tests/Feature/Modules/Accounting/Invoices/InvoiceLifecycleTest.php || true
```

### Step 6.4: Invoice QA & Notifications
```bash
rg -n "audit_log" build/modules/Accounting/Domain/Actions | rg Invoice
rg -n "notify" build/modules/Accounting/Domain/Actions | rg Invoice || true

# Validate command registration + permissions
php artisan config:cache
php artisan route:list --path=invoices
```

**✅ Invoice Lifecycle Checkpoint**:
- [ ] Command bus drives invoice lifecycle (draft → sent → paid/void)
- [ ] Invoice requests enforce BaseFormRequest + RLS
- [ ] UI meets blue-whale + PrimeVue Steps requirements
- [ ] Invoice tests added + passing

---

## 💳 PHASE 7: ACCOUNTING – PAYMENT PROCESSING

### Step 7.1: Payment Models & Policies
```bash
cp stack/app/Models/Payment.php build/modules/Accounting/Models/Payment.php || true
sed -i "s#protected $table = 'payments'#protected $table = 'acct.payments'#g" build/modules/Accounting/Models/Payment.php || true
cp stack/database/migrations/*payment* build/modules/Accounting/Database/Migrations/ || true
cd build && php artisan migrate --path=modules/Accounting/Database/Migrations --step
PGPASSWORD="AppP@ss123" psql -h localhost -U postgres haasib_build -c "SELECT policyname FROM pg_policies WHERE tablename='payments';"
```

### Step 7.2: Payment Commands & Services
```bash
cp -r stack/app/Actions/Payment* build/modules/Accounting/Domain/Actions/ || true
cp stack/app/Services/PaymentAllocationService.php build/modules/Accounting/Services/Payments/PaymentAllocationService.php
vim build/config/command-bus.php # register payment actions (allocate/refund/void)
rg -n "audit_log" build/modules/Accounting/Services/Payments
```

### Step 7.3: Payment UI + Testing
```bash
mkdir -p build/modules/Accounting/Resources/js/Pages/Payments
cp -r stack/resources/js/Pages/Payments/* build/modules/Accounting/Resources/js/Pages/Payments/ || true
rg -n "Payment" build/modules/Accounting/Resources/js/Pages/Payments
cd build && php artisan test tests/Feature/Payments/BatchProcessingParityTest.php
cd build && php artisan test tests/Feature/Api/Payments/PaymentBatchEndpointTest.php
cd build && php artisan test tests/Feature/Api/Payments/PaymentReversalEndpointTest.php
```

### Step 7.4: Payment QA & Ledger Tie-Out
```bash
PGPASSWORD="AppP@ss123" psql -h localhost -U postgres haasib_build -c "SELECT DISTINCT status FROM acct.payments;"
rg -n "ledger" build/modules/Accounting/Services/Payments || true

cd build && php artisan schedule:run --name="payment-allocation" || true
```

**✅ Payment Processing Checkpoint**:
- [ ] Payment allocation + refunds via command bus
- [ ] Batch + API payment tests green
- [ ] PrimeVue toasts + blue-whale theme enforced
- [ ] audit_log events emitted for each financial mutation

---

## 📜 PHASE 8: ACCOUNTING – VENDORS & BILLS

### Step 8.1: Vendor/Bill Data
```bash
cp stack/app/Models/Vendor.php build/modules/Accounting/Models/Vendor.php || true
cp stack/app/Models/Bill.php build/modules/Accounting/Models/Bill.php || true
sed -i "s#protected $table = 'vendors'#protected $table = 'acct.vendors'#g" build/modules/Accounting/Models/Vendor.php || true
sed -i "s#protected $table = 'bills'#protected $table = 'acct.bills'#g" build/modules/Accounting/Models/Bill.php || true
cp stack/database/migrations/*vendor* build/modules/Accounting/Database/Migrations/ || true
cp stack/database/migrations/*bill* build/modules/Accounting/Database/Migrations/ || true
cd build && php artisan migrate --path=modules/Accounting/Database/Migrations --step
```

### Step 8.2: Vendor/Bill Commands + UI
```bash
cp -r stack/app/Actions/Vendor* build/modules/Accounting/Domain/Actions/ || true
cp -r stack/app/Actions/Bill* build/modules/Accounting/Domain/Actions/ || true
cp stack/app/Http/Requests/Vendors/* build/modules/Accounting/Http/Requests/Vendors/ || true
cp stack/app/Http/Requests/Bills/* build/modules/Accounting/Http/Requests/Bills/ || true
mkdir -p build/modules/Accounting/Resources/js/Pages/Vendors
mkdir -p build/modules/Accounting/Resources/js/Pages/Bills
cp -r stack/resources/js/Pages/Vendors/* build/modules/Accounting/Resources/js/Pages/Vendors/ || true
cp -r stack/resources/js/Pages/Bills/* build/modules/Accounting/Resources/js/Pages/Bills/ || true
rg -n "vendors" build/modules/Accounting/Routes/web.php
rg -n "bills" build/modules/Accounting/Routes/web.php
```

### Step 8.3: Vendor/Bill Testing
```bash
cd build && php artisan make:test Modules/Accounting/Vendors/VendorLifecycleTest --pest --unit || true
cd build && php artisan make:test Modules/Accounting/Bills/BillApprovalTest --pest --unit || true
cd build && php artisan test tests/Feature/Modules/Accounting/Vendors/VendorLifecycleTest.php || true
cd build && php artisan test tests/Feature/Modules/Accounting/Bills/BillApprovalTest.php || true
```

### Step 8.4: Vendor/Bill QA & Payable Controls
```bash
rg -n "payment_terms" build/modules/Accounting/Models/Vendor.php || true
rg -n "due_date" build/modules/Accounting/Resources/js/Pages/Bills || true

PGPASSWORD="AppP@ss123" psql -h localhost -U postgres haasib_build -c "SELECT COUNT(*) FROM acct.bills WHERE status='approved';"
php artisan route:list --path=bills
```

**✅ Vendors & Bills Checkpoint**:
- [ ] Vendor + Bill entities scoped to acct schema with RLS
- [ ] Command bus actions registered + auditable
- [ ] Vendor/Bill UI pages blue-whale compliant
- [ ] Dedicated Pest suites passing

---

## 📈 PHASE 9: ACCOUNTING – REPORTING & ANALYTICS

### Step 9.1: Reporting Data + Services
```bash
cp -r stack/app/Services/Acct/Reporting build/modules/Accounting/Services/Reporting || true
cp -r stack/modules/Accounting/Domain/Reports build/modules/Accounting/Domain/Reports || true
cp stack/database/migrations/*report* build/modules/Accounting/Database/Migrations/ || true
cd build && php artisan migrate --path=modules/Accounting/Database/Migrations --step
```

### Step 9.2: Reporting UI
```bash
mkdir -p build/modules/Accounting/Resources/js/Pages/Reporting
cp -r stack/resources/js/Pages/Reporting/* build/modules/Accounting/Resources/js/Pages/Reporting/ || true
rg -n "blue-whale" build/modules/Accounting/Resources/js/Pages/Reporting
```

### Step 9.3: Reporting Validation
```bash
cd build && php artisan module:enable accounting
cd build && php artisan route:list --path=reporting | tee /tmp/reporting-routes.txt
cd build && php artisan module:disable accounting
cd build && php artisan make:test Modules/Accounting/Reporting/ReportingDashboardTest --pest --unit || true
cd build && php artisan test tests/Feature/Modules/Accounting/Reporting/ReportingDashboardTest.php || true
```

### Step 9.4: Reporting QA & Performance
```bash
rg -n "cache" build/modules/Accounting/Services/Reporting || true
npm run test -- ReportingDashboard.spec.ts || true

PGPASSWORD="AppP@ss123" psql -h localhost -U postgres haasib_build -c "SELECT matviewname FROM pg_matviews WHERE schemaname='rpt';" | tee /tmp/rpt-matviews.txt
```

**✅ Reporting & Analytics Checkpoint**:
- [ ] Reporting services query acct schema via RLS-aware patterns
- [ ] Reporting Inertia pages use PrimeVue charts + blue-whale theme
- [ ] Module toggles leave reporting routes isolated
- [ ] Reporting tests added + passing

---

## 🏗️ PHASE 10: FUTURE MODULE PREPARATION

### Step 10.1: Additional Module Scaffolding
```bash
# Create placeholder structure for future modules
mkdir -p build/modules/Reporting/{Http/Controllers,Models,Routes,Database,Services,Resources/js}
mkdir -p build/modules/CRM/{Http/Controllers,Models,Routes,Database,Services,Resources/js}
mkdir -p build/modules/Hospitality/{Http/Controllers,Models,Routes,Database,Services,Resources/js}

# Copy any existing reporting components from stack
cp -r stack/app/Http/Controllers/Reporting/* build/modules/Reporting/Http/Controllers/ || true
cp -r stack/resources/js/Pages/Reporting/* build/modules/Reporting/Resources/js/Pages/ || true
# Scaffold placeholder providers + module.json files to prevent runtime errors
cat > build/modules/Reporting/module.json <<'JSON'
{
  "name": "reporting",
  "provider": "Modules\\Reporting\\Providers\\ReportingServiceProvider",
  "enabled": false,
  "dependencies": ["accounting"]
}
JSON
cat > build/modules/Reporting/Providers/ReportingServiceProvider.php <<'PHP'
<?php

namespace Modules\Reporting\Providers;

use Illuminate\Support\ServiceProvider;

class ReportingServiceProvider extends ServiceProvider
{
    public function register(): void {}
    public function boot(): void {}
}
PHP
# Repeat for CRM + Hospitality (flag them disabled by default)
for module in CRM Hospitality; do
  cat > build/modules/${module}/module.json <<JSON
{
  "name": "${module,,}",
  "provider": "Modules\\\\${module}\\\\Providers\\\\${module}ServiceProvider",
  "enabled": false,
  "dependencies": ["core"]
}
JSON
  mkdir -p build/modules/${module}/Providers
  cat > build/modules/${module}/Providers/${module}ServiceProvider.php <<PHP
<?php

namespace Modules\\${module}\\Providers;

use Illuminate\\Support\\ServiceProvider;

class ${module}ServiceProvider extends ServiceProvider
{
    public function register(): void {}
    public function boot(): void {}
}
PHP
done
```

### Step 10.2: Module Interdependencies Setup
```bash
# Configure module dependencies in each module.json
echo "# Accounting module can work independently"
echo "# Reporting module depends on Accounting" 
echo "# CRM module depends on Accounting"
echo "# Future modules can plug into this architecture"
jq '.dependencies' build/modules/Accounting/module.json
jq '.dependencies' build/modules/Reporting/module.json
# Update config/modules.php entries for new modules
rg -n "modules" build/config/modules.php
cd build && php artisan module:list
cd build && php artisan module:enable reporting && php artisan module:list && php artisan module:disable reporting
```

**✅ Future Modules Preparation**:
- [ ] Module directories created
- [ ] Module dependency system configured
- [ ] Existing reporting components moved to Reporting module
- [ ] Ready for future module additions

---

## ✅ PHASE 11: VALIDATION & TESTING

### Step 11.1: Module Integration Testing
```bash
cd build

# Run all migrations and seeders
php artisan migrate:fresh --seed

# Test module loading system
php artisan module:list
php artisan module:enable accounting
php artisan module:disable accounting
php artisan module:enable accounting

# Run RBAC + theme validation
bash validate-migration.sh
npm run theme:validate
# Enforce tenant isolation + accounting behaviors
php artisan test tests/Feature/Security/MultiTenantDataIsolationTest.php

# Test all modules
php artisan test
npm run lint
npm run test:e2e
```

### Step 11.2: Complete Module Workflow Testing
**Module-Based Business Workflows**:
- [ ] **Core Module**: Authentication → Company setup → User management
- [ ] **Accounting Module**: Customer → Product → Invoice → Payment → Reporting
- [ ] **Module Independence**: Accounting can be enabled/disabled independently
- [ ] **Module Communication**: Modules interact correctly through defined interfaces
- [ ] **Permission Integration**: RBAC works across all modules

### Step 11.3: Constitutional Compliance & Module Standards
```bash
# Layout compliance validation per module
php artisan layout:validate --module=accounting
php artisan layout:validate --check-theme --json

# RBAC validation across modules
php artisan test tests/Feature/RbacTest.php

# Module-specific frontend validation
npm run build && echo "✅ All modules build successfully"

# Validate module independence
php artisan module:disable accounting
php artisan route:list # Should show only core routes
php artisan module:enable accounting  
php artisan route:list # Should show core + accounting routes
```

**✅ Module Migration Success Criteria**: 
- ✅ Core module handles authentication and company management
- ✅ Accounting module provides complete business functionality independently
- ✅ Modules can be enabled/disabled without breaking the system
- ✅ No 403 permission errors across all modules
- ✅ Every module page follows strict layout standards  
- ✅ Module workflows complete successfully
- ✅ Performance matches or exceeds original system
- ✅ Ready for future module additions (CRM, Hospitality, etc.)

---

## 🚨 ROLLBACK PROCEDURES

### Emergency Rollback
```bash
# Stop build environment  
cd /home/banna/projects/Haasib/build
php artisan down

# Switch back to stack
cd /home/banna/projects/Haasib/stack
php artisan up
```

### Module-Specific Rollbacks
- **Phase 2 (Core)**: Reset to foundation, restart from core infrastructure
- **Phase 3 (Accounting Foundation)**: Remove accounting scaffolding, revert to core-only
- **Phases 4–9 (Accounting Features)**: Disable specific feature modules (customers, products, invoices, payments, vendors/bills, reporting) and re-run targeted migrations/tests
- **Phase 10 (Future Modules)**: Remove future module scaffolding
- **Phase 11 (Testing)**: Fix validation issues, re-run integration + theme checks

### Selective Module Disabling
```bash
# Disable specific module without full rollback
php artisan module:disable accounting
php artisan route:clear
php artisan config:clear

# Module will be disabled but files remain for easy re-enabling
php artisan module:enable accounting
```

---

## 📊 SUCCESS METRICS

### Module Completion Metrics
- ✅ **Core Module**: Authentication, company management, RBAC system working
- ✅ **Accounting Module**: Complete business functionality (customers → invoices → payments → reporting)
- ✅ **Module Independence**: Accounting module can be enabled/disabled independently
- ✅ **Module Integration**: Modules communicate correctly through defined interfaces
- ✅ **Future Module Ready**: Structure prepared for CRM, Hospitality, etc.

### Module Architecture Metrics
- ✅ Migration time: < 4 hours total (module-by-module)
- ✅ Zero 403 permission errors across all modules
- ✅ Every module page follows strict layout standards
- ✅ No HTML form elements (PrimeVue only)
- ✅ Blue-whale theme consistently applied across modules
- ✅ Single-row header design enforced per module

### Module Business Workflow Metrics
- ✅ **Core Module Workflows**: User auth → Company setup → Permission management
- ✅ **Accounting Workflows**: Customer → Product → Invoice → Payment → Reports
- ✅ **Cross-Module Integration**: Permissions work across module boundaries
- ✅ **Module Isolation**: Disabling accounting doesn't break core functionality
- ✅ **Module Scalability**: Easy to add new modules without affecting existing ones

### Constitutional Compliance Per Module
- ✅ All module controllers use Command Bus patterns
- ✅ All module models follow UUID + RLS standards
- ✅ All module FormRequests extend BaseFormRequest
- ✅ All module frontend uses Composition API + PrimeVue
- ✅ Module-specific inline editing rules followed
- ✅ Permission constants used throughout all modules

---

## 🔧 POST-MIGRATION CHECKLIST

### Module Validation
- [ ] Test complete core module functionality (auth, companies, users)
- [ ] Validate accounting module end-to-end workflow
- [ ] Verify module independence (enable/disable works correctly)
- [ ] Test cross-module permission system integration
- [ ] Validate blue-whale theme across all modules
- [ ] Test responsive design on mobile devices per module

### Module Performance Validation
- [ ] Module page load times ≤ original system
- [ ] Module-specific database query performance maintained
- [ ] Module frontend build times acceptable
- [ ] No console errors or warnings per module
- [ ] Module loading/unloading performance

### Documentation Updates
- [ ] Update README with new modular architecture
- [ ] Document module dependencies and interfaces
- [ ] Record any deviations from modular plan
- [ ] Update CLAUDE.md with module-specific patterns
- [ ] Create module development guidelines

---

## 🎯 MODULE DEPENDENCY MAP

```
Foundation (Phase 1)
    ↓
Core Module (Phase 2)
├── Authentication System
├── Company Management  
├── User Management
├── RBAC System
└── Module Loading Infrastructure
    ↓
Accounting Module (Phase 3)
├── Foundation (Phase 3)
├── Customer Management (Phase 4)
├── Product Catalog (Phase 5)
├── Invoice Lifecycle (Phase 6)
├── Payment Processing (Phase 7)
├── Vendors & Bills (Phase 8)
└── Reporting & Analytics (Phase 9)
    ↓
Future Modules (Phase 10+)
├── CRM Module (depends on Core + Accounting)
├── Reporting Module (depends on Accounting)
├── Hospitality Module (depends on Core)
└── Additional Business Modules
```

**Migration Status**: ⏸️ Ready for Module-Driven Execution  
**Estimated Duration**: 3-4 hours (with thorough module testing)  
**Risk Level**: Low (module isolation + independent rollbacks)  
**Approach**: Complete self-contained modules, not technical layers
