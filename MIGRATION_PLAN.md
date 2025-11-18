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
```bash
# Create Core module structure
mkdir -p build/modules/Core/{Http/Controllers,Models,Routes,Database,Services,CLI}

# Copy essential core models and infrastructure
cp -r stack/modules/Core/* build/modules/Core/
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
cd build && php artisan about | rg -n \"ModuleServiceProvider\" || echo \"Provider missing\"
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
```bash
# Copy core authentication controllers
mkdir -p build/app/Http/Controllers/Auth
cp stack/app/Http/Controllers/Auth/* build/app/Http/Controllers/Auth/

# Copy core company controllers 
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

## 📊 PHASE 3: ACCOUNTING MODULE MIGRATION

### Step 3.1: Complete Accounting Module Structure
```bash
# Create complete Accounting module directory structure
mkdir -p build/modules/Accounting/{Http/Controllers,Models,Routes,Database/Migrations,Database/Seeders,Domain,Services,CLI/Commands,Resources/js}

# Copy the entire accounting module from existing modules structure
cp -r stack/modules/Accounting/* build/modules/Accounting/

# Copy any accounting components from traditional Laravel structure
cp -r stack/app/Http/Controllers/Invoicing/* build/modules/Accounting/Http/Controllers/ || true
cp -r stack/app/Http/Controllers/Accounting/* build/modules/Accounting/Http/Controllers/ || true

# Copy accounting models from traditional structure to module
cp stack/app/Models/Customer.php build/modules/Accounting/Models/ || true
cp stack/app/Models/Invoice.php build/modules/Accounting/Models/ || true
cp stack/app/Models/Product.php build/modules/Accounting/Models/ || true
cp stack/app/Models/Acct/* build/modules/Accounting/Models/ || true
# Update model namespaces/tables so every accounting model targets the `acct` schema,
# keeps `HasUuids`, `BelongsToCompany`, `SoftDeletes`, and enforces guarded attributes per CLAUDE.md.
```

### Step 3.2: Accounting Database Layer
```bash
# Copy ALL accounting-related migrations
cp stack/database/migrations/*_create_acct_*.php build/modules/Accounting/Database/Migrations/ || true
cp stack/modules/Accounting/Database/Migrations/* build/modules/Accounting/Database/Migrations/ || true

# Copy accounting seeders
cp stack/database/seeders/*Customer*.php build/modules/Accounting/Database/Seeders/ || true
cp stack/database/seeders/*Invoice*.php build/modules/Accounting/Database/Seeders/ || true

# Recreate accounting schema + RLS (per CLAUDE constitution)
PGPASSWORD="AppP@ss123" psql -h localhost -U postgres haasib_build <<'SQL'
CREATE SCHEMA IF NOT EXISTS acct AUTHORIZATION postgres;
ALTER DEFAULT PRIVILEGES IN SCHEMA acct GRANT ALL ON TABLES TO postgres;
SQL
cd build && php artisan migrate --path=modules/Accounting/Database/Migrations
cd build && php artisan db:seed --class=PermissionSeeder
cd build && php artisan migrate --path=database/migrations/2025_10_11_110306_enhance_company_rls_policies.php

# Run accounting migrations (ensures command-bus dispatched RLS safe operations)
cd build && php artisan migrate
# Validate tenant isolation for accounting models
cd build && php artisan test tests/Feature/Security/MultiTenantDataIsolationTest.php
PGPASSWORD="AppP@ss123" psql -h localhost -U postgres haasib_build -c "\\d acct.customers" | tee /tmp/acct-customers.txt
PGPASSWORD="AppP@ss123" psql -h localhost -U postgres haasib_build -c "SELECT schemaname, tablename, policyname FROM pg_policies WHERE schemaname='acct';" | tee /tmp/acct-policies.txt
```

### Step 3.3: Accounting Services & Business Logic
```bash
# Copy all accounting services
cp -r stack/app/Services/Acct/* build/modules/Accounting/Services/ || true
cp -r stack/app/Actions/Acct/* build/modules/Accounting/Domain/Actions/ || true

# Copy command bus actions
cp -r stack/app/Actions/Invoice* build/modules/Accounting/Domain/Actions/ || true
cp -r stack/app/Actions/Customer* build/modules/Accounting/Domain/Actions/ || true
cp -r stack/app/Actions/Payment* build/modules/Accounting/Domain/Actions/ || true
# After copying, register each action in `config/command-bus.php` and confirm handlers
# inject `ServiceContext`, dispatch via `Bus::dispatch()`, and call `audit_log()` for financial mutations.
# Run `rg -n "auth\(" modules/Accounting` and `rg -n "request\(" modules/Accounting` to ensure no forbidden helpers remain.
```

### Step 3.4: Accounting Frontend (Module-Specific)
```bash
# Create accounting module frontend structure
mkdir -p build/modules/Accounting/Resources/js/{Pages,Components,Composables}

# Copy ALL accounting-related frontend pages to module
cp -r stack/resources/js/Pages/Accounting/* build/modules/Accounting/Resources/js/Pages/ || true
cp -r stack/resources/js/Pages/Invoicing/* build/modules/Accounting/Resources/js/Pages/ || true
cp -r stack/resources/js/Pages/Payments/* build/modules/Accounting/Resources/js/Pages/ || true

# Copy accounting-specific components
mkdir -p build/modules/Accounting/Resources/js/Components
cp -r stack/resources/js/Components/Acct/* build/modules/Accounting/Resources/js/Components/ || true
# Refactor any Options API leftovers to `<script setup lang="ts">` and wrap every page in the blue-whale layout shell per CLAUDE.md.
rg -l "<script>" build/modules/Accounting/Resources/js/Pages | xargs sed -n '1,40p' # inspect
npm run lint -- --fix --rule "{\"vue/no-options-api\": \"error\"}"
rg -n "data-theme" build/modules/Accounting/Resources/js/Pages | wc -l # ensure theme attribute exists everywhere
```

### Step 3.5: Accounting Routes (Self-Contained)
```bash
# Extract accounting routes from main routes file
cp stack/modules/Accounting/Routes/web.php build/modules/Accounting/Routes/ || echo "# Will extract from main web.php"
cp stack/modules/Accounting/Routes/api.php build/modules/Accounting/Routes/ || echo "# Will extract from main api.php"

# Extract all accounting routes from main routes and move to module
# This includes: customers, invoices, payments, products, reports routes
cd build && php artisan route:list --path=accounting | tee /tmp/accounting-routes-core.txt # should be empty after core cleanup
cd build && php artisan module:enable accounting
cd build && php artisan route:list --path=accounting | tee /tmp/accounting-routes-enabled.txt
diff -u /tmp/accounting-routes-core.txt /tmp/accounting-routes-enabled.txt || true
cd build && php artisan module:disable accounting # leave disabled until QA completes
```

### Step 3.6: Configure Accounting Module Loading
```bash
# Update module.json for accounting module
echo '{
  "name": "accounting", 
  "description": "Complete Accounting Module",
  "version": "1.0.0",
  "provider": "Modules\\Accounting\\Providers\\AccountingServiceProvider",
  "enabled": true,
  "dependencies": ["core"]
}' > build/modules/Accounting/module.json

# Register accounting module in modules config
echo "# Update config/modules.php to include accounting module"
echo "# Register accounting command bus actions + bindings in config/command-bus.php"
```

**✅ Accounting Module Testing Checkpoint**: 
- [ ] **Customer Management**: List, create, edit, delete customers
- [ ] **Product Catalog**: Manage products and inventory  
- [ ] **Invoice Management**: Complete invoice lifecycle (draft → sent → paid)
- [ ] **Payment Processing**: Payment allocation and tracking
- [ ] **Reporting**: Financial dashboards and reports
- [ ] **Module Independence**: Can enable/disable accounting module
- [ ] **Module Routes**: All accounting routes work independently
- [ ] **Module Frontend**: All accounting pages render correctly
- [ ] **Constitutional Compliance**: RBAC, layout standards, blue-whale theme
- [ ] **Complete Workflow**: Customer → Invoice → Payment → Reporting works end-to-end

---

## 🏗️ PHASE 4: FUTURE MODULE PREPARATION

### Step 4.1: Additional Module Scaffolding
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

### Step 4.2: Module Interdependencies Setup
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

## ✅ PHASE 5: VALIDATION & TESTING

### Step 5.1: Module Integration Testing
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

### Step 5.2: Complete Module Workflow Testing
**Module-Based Business Workflows**:
- [ ] **Core Module**: Authentication → Company setup → User management
- [ ] **Accounting Module**: Customer → Product → Invoice → Payment → Reporting
- [ ] **Module Independence**: Accounting can be enabled/disabled independently
- [ ] **Module Communication**: Modules interact correctly through defined interfaces
- [ ] **Permission Integration**: RBAC works across all modules

### Step 5.3: Constitutional Compliance & Module Standards
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
- **Phase 3 (Accounting)**: Remove accounting module, revert to core-only
- **Phase 4 (Future Modules)**: Remove future module scaffolding
- **Phase 5 (Testing)**: Fix validation issues, re-test modules

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
├── Customer Management
├── Product Catalog
├── Invoice Management
├── Payment Processing
└── Financial Reporting
    ↓
Future Modules (Phase 4+)
├── CRM Module (depends on Core + Accounting)
├── Reporting Module (depends on Accounting)
├── Hospitality Module (depends on Core)
└── Additional Business Modules
```

**Migration Status**: ⏸️ Ready for Module-Driven Execution  
**Estimated Duration**: 3-4 hours (with thorough module testing)  
**Risk Level**: Low (module isolation + independent rollbacks)  
**Approach**: Complete self-contained modules, not technical layers
